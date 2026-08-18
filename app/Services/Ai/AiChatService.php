<?php

namespace App\Services\Ai;

use Anthropic\Client;
use Anthropic\Messages\InputJSONDelta;
use Anthropic\Messages\TextDelta;
use Anthropic\Messages\ToolUseBlock;
use App\Models\Employee;
use App\Services\Ai\Tools\AiTool;
use App\Services\Ai\Tools\ExplainWorkflowTool;
use App\Services\Ai\Tools\GetDeliveryProjectsTool;
use App\Services\Ai\Tools\GetSlaSummaryTool;
use App\Services\Ai\Tools\GetTicketsTool;
use App\Services\SlaService;
use Closure;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Orchestrates one assistant turn: loads/saves ephemeral conversation state
 * from cache, drives the Claude tool-use loop, and streams text deltas back
 * to the caller as they arrive.
 *
 * No conversation content is ever written to the database — state lives only
 * in the 'ai_chat' cache store for a sliding 1-hour window (see cacheKey()).
 */
class AiChatService
{
    private const CACHE_STORE = 'ai_chat';
    private const CACHE_TTL_MINUTES = 60;
    private const MAX_TOOL_ITERATIONS = 6;

    /** @var array<string, AiTool> */
    private array $tools;

    public function __construct(private Client $client, SlaService $slaService)
    {
        $this->tools = [
            'get_tickets' => new GetTicketsTool(),
            'get_sla_summary' => new GetSlaSummaryTool($slaService),
            'get_delivery_projects' => new GetDeliveryProjectsTool(),
            'explain_workflow' => new ExplainWorkflowTool(),
        ];
    }

    /**
     * @param array<int, array{type: string, media_type: string, data: string}> $attachments
     * @param Closure(string): void $onDelta called with each text chunk as it streams in
     * @param Closure(): bool $isAborted polled between network steps to allow early stop
     */
    public function streamReply(
        Employee $employee,
        string $conversationId,
        string $userText,
        array $attachments,
        string $modelTier,
        Closure $onDelta,
        Closure $isAborted,
    ): void {
        $cacheKey = $this->cacheKey($employee, $conversationId);
        $state = Cache::store(self::CACHE_STORE)->get($cacheKey, ['messages' => []]);
        $messages = $state['messages'] ?? [];

        $messages[] = [
            'role' => 'user',
            'content' => $this->buildUserContent($userText, $attachments),
        ];

        [$model, $config] = $this->modelConfigFor($modelTier);

        $iterations = 0;

        while (true) {
            if ($isAborted()) {
                return;
            }

            if (++$iterations > self::MAX_TOOL_ITERATIONS) {
                $onDelta("\n\nI wasn't able to finish gathering everything needed to answer this fully — could you try a narrower question?");
                break;
            }

            $stream = $this->client->messages->createStream(
                maxTokens: $config['max_tokens'],
                messages: $messages,
                model: $model,
                system: $this->systemPrompt(),
                thinking: $config['thinking'],
                outputConfig: $config['output_config'],
                tools: $this->toolDefinitions(),
            );

            [$assistantContent, $toolUseBlocks, $stopReason] = $this->consumeStream($stream, $onDelta, $isAborted);

            if (null === $assistantContent) {
                // Aborted mid-stream.
                return;
            }

            $messages[] = ['role' => 'assistant', 'content' => $assistantContent];

            if ('tool_use' !== $stopReason || empty($toolUseBlocks)) {
                break;
            }

            $messages[] = ['role' => 'user', 'content' => $this->runTools($employee, $toolUseBlocks)];
        }

        Cache::store(self::CACHE_STORE)->put($cacheKey, [
            'messages' => $messages,
            'model_tier' => $modelTier,
            'updated_at' => now()->toIso8601String(),
        ], now()->addMinutes(self::CACHE_TTL_MINUTES));
    }

    /**
     * Consume one model turn's stream: forward text deltas via $onDelta and
     * reassemble the full assistant content-block array (including tool_use
     * blocks, whose input arrives as incremental JSON deltas).
     *
     * @return array{0: ?array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: ?string}
     */
    private function consumeStream($stream, Closure $onDelta, Closure $isAborted): array
    {
        /** @var array<int, array<string, mixed>> $blocks */
        $blocks = [];
        $stopReason = null;

        foreach ($stream as $event) {
            if ($isAborted()) {
                $stream->close();

                return [null, [], null];
            }

            switch ($event->type) {
                case 'content_block_start':
                    $block = $event->contentBlock;
                    $blocks[$event->index] = $block instanceof ToolUseBlock
                        ? ['type' => 'tool_use', 'id' => $block->id, 'name' => $block->name, 'input_json' => '']
                        : ['type' => 'text', 'text' => ''];
                    break;

                case 'content_block_delta':
                    $delta = $event->delta;
                    if ($delta instanceof TextDelta) {
                        $blocks[$event->index]['text'] .= $delta->text;
                        $onDelta($delta->text);
                    } elseif ($delta instanceof InputJSONDelta) {
                        $blocks[$event->index]['input_json'] .= $delta->partialJSON;
                    }
                    break;

                case 'message_delta':
                    $stopReason = $event->delta->stopReason;
                    break;

                case 'message_stop':
                    break 2;
            }
        }

        ksort($blocks);

        $assistantContent = [];
        $toolUseBlocks = [];

        foreach ($blocks as $block) {
            if ('text' === $block['type']) {
                if ('' !== $block['text']) {
                    $assistantContent[] = ['type' => 'text', 'text' => $block['text']];
                }

                continue;
            }

            $input = json_decode($block['input_json'], true);
            $toolUseBlock = [
                'type' => 'tool_use',
                'id' => $block['id'],
                'name' => $block['name'],
                'input' => is_array($input) ? $input : [],
            ];
            $assistantContent[] = $toolUseBlock;
            $toolUseBlocks[] = $toolUseBlock;
        }

        return [$assistantContent, $toolUseBlocks, $stopReason];
    }

    /**
     * @param array<int, array{id: string, name: string, input: array<string, mixed>}> $toolUseBlocks
     * @return array<int, array<string, mixed>>
     */
    private function runTools(Employee $employee, array $toolUseBlocks): array
    {
        $results = [];

        foreach ($toolUseBlocks as $call) {
            $tool = $this->tools[$call['name']] ?? null;

            if (!$tool) {
                $results[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $call['id'],
                    'content' => json_encode(['error' => "Unknown tool: {$call['name']}"]),
                    'is_error' => true,
                ];
                continue;
            }

            try {
                $data = $tool->run($employee, $call['input']);
                $results[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $call['id'],
                    'content' => json_encode($data),
                ];
            } catch (Throwable $e) {
                report($e);
                $results[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $call['id'],
                    'content' => json_encode(['error' => 'Something went wrong while running this tool.']),
                    'is_error' => true,
                ];
            }
        }

        return $results;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function toolDefinitions(): array
    {
        return array_map(fn (AiTool $tool) => $tool->definition(), array_values($this->tools));
    }

    /**
     * @param array<int, array{type: string, media_type: string, data: string}> $attachments
     * @return array<int, array<string, mixed>>
     */
    private function buildUserContent(string $text, array $attachments): array
    {
        $content = [];

        foreach ($attachments as $attachment) {
            $content[] = [
                'type' => $attachment['type'],
                'source' => [
                    'type' => 'base64',
                    'media_type' => $attachment['media_type'],
                    'data' => $attachment['data'],
                ],
            ];
        }

        if ('' !== $text) {
            $content[] = ['type' => 'text', 'text' => $text];
        }

        return $content;
    }

    /**
     * @return array{0: string, 1: array{max_tokens: int, thinking: ?array, output_config: ?array}}
     */
    private function modelConfigFor(string $tier): array
    {
        return match ($tier) {
            'fast' => ['claude-haiku-4-5', [
                'max_tokens' => 2048,
                'thinking' => null,
                'output_config' => null,
            ]],
            'reasoning' => ['claude-opus-5', [
                'max_tokens' => 8000,
                'thinking' => ['type' => 'adaptive'],
                'output_config' => ['effort' => 'high'],
            ]],
            default => ['claude-sonnet-5', [
                'max_tokens' => 4096,
                'thinking' => ['type' => 'adaptive'],
                'output_config' => ['effort' => 'medium'],
            ]],
        };
    }

    private function cacheKey(Employee $employee, string $conversationId): string
    {
        $safeId = preg_replace('/[^a-zA-Z0-9\-]/', '', $conversationId);
        $safeId = $safeId ?: 'default';

        return "ai_chat:emp{$employee->employee_id}:{$safeId}";
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are the EcoSystem Assistant, built into EcoSystem — the internal operations app Eclectic's employees use for
tickets, SLA tracking, and delivery project management.

Scope: help the current user understand EcoSystem and their own data in it — explain how the system works, answer
questions about their tickets/SLA/delivery projects using the provided tools, summarize tickets or reports, and draft
customer-facing reply text when asked. Politely decline requests that are unrelated to EcoSystem or this user's work
(e.g. general coding help, unrelated trivia).

Tools: use get_tickets, get_sla_summary, and get_delivery_projects to answer questions about live data — never guess
or fabricate ticket numbers, dates, or statuses. Use explain_workflow for "how do I..." / "what's the process for..."
questions about how a business process works (mandays proposals, ticket creation/assignment, delivery project
planning, SLA configuration) — don't describe these processes from general knowledge or assumption, always call the
tool. If a tool result includes a "note" or "error" explaining a permission limitation, relay that to the user plainly
rather than ignoring it or working around it.

Drafting replies: when asked to draft a reply to a customer, first use the relevant tool to get accurate context about
the ticket if one is referenced, then write the draft as plain reply text. Make clear when what you're writing is a
draft the user should review, not something already sent.

Be concise and direct. Do not fabricate data under any circumstances — if a tool doesn't have the information needed,
say so.
PROMPT;
    }
}
