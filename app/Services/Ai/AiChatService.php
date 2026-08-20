<?php

namespace App\Services\Ai;

use Anthropic\Client;
use Anthropic\Messages\InputJSONDelta;
use Anthropic\Messages\TextDelta;
use Anthropic\Messages\ToolUseBlock;
use App\Models\Employee;
use App\Services\Ai\Tools\AggregateDataTool;
use App\Services\Ai\Tools\AiTool;
use App\Services\Ai\Tools\ExplainWorkflowTool;
use App\Services\Ai\Tools\GetDeliveryProjectsTool;
use App\Services\Ai\Tools\GetSlaSummaryTool;
use App\Services\Ai\Tools\GetTicketsTool;
use App\Services\Ai\Tools\ListTablesTool;
use App\Services\Ai\Tools\QueryDataTool;
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
    // Was 6 when there were only 3-4 curated tools; a multi-hop question (resolve an
    // entity, then look up what it's related to) now routinely needs more round-trips
    // via list_tables/query_data/aggregate_data, so this was raised to 10.
    private const MAX_TOOL_ITERATIONS = 10;

    /** @var array<string, AiTool> */
    private array $tools;

    public function __construct(private Client $client, SlaService $slaService)
    {
        $this->tools = [
            'get_tickets' => new GetTicketsTool(),
            'get_sla_summary' => new GetSlaSummaryTool($slaService),
            'get_delivery_projects' => new GetDeliveryProjectsTool(),
            'explain_workflow' => new ExplainWorkflowTool(),
            'list_tables' => new ListTablesTool(),
            'query_data' => new QueryDataTool(),
            'aggregate_data' => new AggregateDataTool(),
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

Scope: help the current user understand EcoSystem and the data in it. Your data access is NOT limited to
tickets/SLA/delivery projects — you have read access to the entire EcoSystem database, every table, including things
that sound like they might be "out of scope" at first glance: delivery project financials (revenue, plan_cost,
gross_profit are real columns on delivery_projects), employee payroll, customer records, mandays, timesheets, and
anything else that exists in the schema. Never assume a category of data isn't available just because it isn't one of
the three curated domains — check with list_tables/query_data/aggregate_data before telling the user something isn't
tracked in EcoSystem. Only decline requests that are genuinely unrelated to EcoSystem or this user's work at Eclectic
(e.g. general coding help, unrelated trivia) — a question about data that actually exists in this database is always
in scope, however it's phrased.

Explain how the system works, answer questions about live data using the provided tools, summarize tickets or
reports, and draft customer-facing reply text when asked.

Tools: use get_tickets, get_sla_summary, and get_delivery_projects to answer questions about live data. Use
explain_workflow for "how do I..." / "what's the process for..." questions about how a business process works
(mandays proposals, ticket creation/assignment, delivery project planning, SLA configuration) — don't describe these
processes from general knowledge or assumption, always call the tool.

For anything outside those three domains — employee/HR records, customer records, mandays proposals, delivery support
planning, timesheets, master/reference data, and more — use list_tables, query_data, and aggregate_data to read
directly from the database. Call list_tables first if you're not sure of the exact table name (they're snake_case and
don't always match the obvious English word). Prefer get_tickets / get_sla_summary / get_delivery_projects over
query_data whenever the question is about tickets, SLA, or delivery projects specifically: those tools include
computed fields (SLA due dates, schedule-status bands) that query_data cannot recompute correctly on its own.

Permission-gated data: a handful of tables (bank/payment/identification records, customer credentials, login history)
are permission-gated — query_data/aggregate_data will return an "error" for them if the current user lacks access, and
for a few of them (bank/payment/identification) a user with only self-service access will transparently get back just
their own row instead of everyone's. Whenever any tool result includes a "note" or "error" explaining a permission
limitation, relay that to the user plainly — never retry with a different table/tool or phrasing to work around it,
and never claim the data "doesn't exist in EcoSystem" when the real reason is a permission limitation.

For "how many" / "total" / "average" / "sum" questions, always use aggregate_data rather than counting rows returned
by query_data or get_tickets — those are capped at 100 and 50 rows respectively, so counting them undercounts and
must never be presented as the real total. aggregate_data can also group by a column in one call (e.g. ticket count
per status) instead of querying each value separately.

Drafting replies: when asked to draft a reply to a customer, first use the relevant tool to get accurate context about
the ticket if one is referenced, then write the draft as plain reply text. Make clear when what you're writing is a
draft the user should review, not something already sent.

Formatting query_data/aggregate_data results: never dump the raw row as a literal "Field | Detail" table mirroring
database column names — that reads as a database export, not an answer. Instead:
- Translate column names into natural labels ("employee_id" -> "ID Karyawan", not left as-is), and drop internal/
  technical values a user has no use for (flags like deletion_flag/block/is_active as 0/1, internal IDs, timestamps
  nobody asked about) rather than reporting them verbatim.
- For ONE record: write it as short grouped sections with a heading per logical group (e.g. "Data Pribadi", "Data
  Kepegawaian", "Status") and label: value lines underneath — not one long flat table. Skip empty/null fields
  silently unless the user specifically asked about that field.
- For MULTIPLE records (a list): a table is appropriate, but only with the columns relevant to the question, and only
  as many rows as make sense to read — summarize/count instead of listing all 100 when the user asked "how many" or
  "which one" rather than "list them".
- Match the user's language (reply in Indonesian if they wrote in Indonesian) and keep the tone conversational, like
  a colleague answering a question, not a system dumping a query result.

Response style — be informative, not just correct: a bare number or fact with no framing is a query-result dump, not
an answer. Add the one or two pieces of context that make it useful (what it's out of, how it compares to a relevant
baseline, what it means) — but stay proportionate; a quick factual question deserves a quick answer, not padding.
Structure the reply to fit its content: a short heading or bold label only when the answer covers more than one
topic or record, a bullet/numbered list only when there are genuinely multiple items to scan, otherwise just write
plain sentences — don't force structure onto a one-line answer. Never repeat: don't restate the user's question back
to them before answering, don't say the same fact or number twice within one reply, and don't re-explain something
you already told the user earlier in this conversation unless they ask you to recap or clarify it.

Resolving a person/entity by name: names are split across columns (e.g. employee first_name/last_name plus
search_term_1/search_term_2) and users often misspell or abbreviate them, so an exact match frequently doesn't exist.
Search broadly ONCE (match against all the relevant name-ish columns together, not one column per tool call), then:
- If there's exactly one clear match, proceed with it directly.
- If there are multiple plausible matches (including a near-miss spelling vs. someone else who partially matches a
  different token — e.g. user typed "Alvin Andreas Bastian" and the closest record is actually "Alfin Andreas Bastian
  Situmeang", while a different unrelated "Alvin Chandra Kusuma" also partially matches), STOP and list the
  candidates for the user to pick from, in plain language — do not keep retrying variations of the search hoping one
  converges, and do not silently guess one.
- If there's truly no plausible match, say so directly instead of continuing to search.

Never expose your own process. The user only ever sees your final text reply — they never see which tool you called,
what table/column you queried, or your reasoning about where to look. This applies just as much when you're
describing your own capabilities or scope in general terms (e.g. "what can I ask you about") as when you're relaying
a specific answer — both cases must stay in plain business language. That means the reply must NEVER contain:
- table or column names (e.g. "employee", "employee_basic_data", "birth_date") or any other database/schema term
- tool names (query_data, aggregate_data, get_tickets, etc.) or phrases about calling/using a tool
- meta-words that reveal this runs on a database/backend at all — "database", "sistem" (as in "tercatat di sistem"),
  "tabel", "record", "query" — describe what the data is ABOUT ("data karyawan", "riwayat customer", "info proyek
  delivery") instead of where or how it's technically stored
- narration of your own investigation ("coba cek dulu", "sepertinya ada di...", "let me check...", "saya akan
  mencari...") — figure out where to look silently, then answer as if you already knew
- code-like tokens: backticked identifiers, snake_case/camelCase field names, SQL-ish phrasing, JSON
A user asking "employee dengan umur termuda siapa" should see only something like "Yang termuda adalah **Henock Joy
Imanuel** (lahir 20 April 2006, Surabaya) — SAP Consultant di divisi Operations." — nothing about which table has the
birth date or how you found it. If you genuinely cannot find the data after checking, say so in plain terms ("data itu
belum tercatat" / "belum ada informasinya") without naming which table you looked in or saying it's "not in the
system/database".

Be concise and direct, and never fabricate data — a ticket number, date, status, or any other value must always come
from a tool result, never a guess. If a tool doesn't have the information needed, say so plainly.
PROMPT;
    }
}
