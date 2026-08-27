<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Drivers\Concerns\TranslatesCanonicalMessages;
use App\Services\Ai\Drivers\Contracts\ChatDriver;
use Closure;
use OpenAI\Client;
use OpenAI\Responses\Responses\CreateResponse;
use OpenAI\Responses\Responses\Output\OutputFunctionToolCall;
use OpenAI\Responses\Responses\Output\OutputMessage;
use OpenAI\Responses\Responses\Output\OutputMessageContentOutputText;
use RuntimeException;

/**
 * GPT side of AiChatService's turn, via OpenAI's Responses API.
 *
 * The service and its cache only ever see Anthropic-shaped canonical blocks
 * (see ChatDriver's docblock) — message/tool translation to OpenAI's
 * `input`/`function_call`/`function_call_output` shapes lives in the shared
 * TranslatesCanonicalMessages trait (also used by OpenAiResearchDriver).
 * Rather than hand-reassembling function-call argument deltas from streamed
 * chunks (error-prone — see the Anthropic driver's InputJSONDelta handling),
 * this class only uses the stream for text deltas and builds the final
 * canonical blocks from the terminal response.completed/incomplete event's
 * already-assembled `output` array (finalize()).
 */
class OpenAiChatDriver implements ChatDriver
{
    use TranslatesCanonicalMessages;

    public function __construct(private Client $client)
    {
    }

    public function turn(
        string $model,
        string $systemPrompt,
        array $messages,
        array $tools,
        int $maxTokens,
        ?string $effort,
        Closure $onDelta,
        Closure $isAborted,
    ): array {
        $parameters = [
            'model' => $model,
            'instructions' => $systemPrompt,
            'input' => $this->toResponsesInput($messages),
            'max_output_tokens' => $maxTokens,
            // Percakapan AI di EcoSystem TIDAK PERNAH masuk database di sisi kita
            // (lihat AiChatService) — store:false menjaga janji yang sama berlaku
            // di sisi OpenAI, bukan cuma di sisi kita.
            'store' => false,
        ];

        if (!empty($tools)) {
            $parameters['tools'] = $this->toResponsesTools($tools);
        }

        if ($effort) {
            $parameters['reasoning'] = ['effort' => $effort];
        }

        $stream = $this->client->responses()->createStreamed($parameters);

        return $this->consumeStream($stream, $onDelta, $isAborted);
    }

    /**
     * @return array{0: ?array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: ?string}
     */
    private function consumeStream($stream, Closure $onDelta, Closure $isAborted): array
    {
        foreach ($stream as $event) {
            if ($isAborted()) {
                return [null, [], null];
            }

            switch ($event->event) {
                case 'response.output_text.delta':
                    $onDelta($event->response->delta);
                    break;

                case 'response.completed':
                case 'response.incomplete':
                case 'response.failed':
                    return $this->finalize($event->response->response);

                case 'error':
                    throw new RuntimeException('OpenAI stream error: ' . $event->response->message);
            }
        }

        // Stream ended without a terminal event (connection dropped mid-flight).
        return [null, [], null];
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: string}
     */
    private function finalize(CreateResponse $response): array
    {
        if ('failed' === $response->status) {
            throw new RuntimeException('OpenAI response failed: ' . ($response->error?->message ?? 'unknown error'));
        }

        $content = [];
        $toolUseBlocks = [];

        foreach ($response->output as $item) {
            if ($item instanceof OutputMessage) {
                foreach ($item->content as $part) {
                    if ($part instanceof OutputMessageContentOutputText && '' !== trim($part->text)) {
                        $content[] = ['type' => 'text', 'text' => $part->text];
                    }
                }

                continue;
            }

            if ($item instanceof OutputFunctionToolCall) {
                $input = json_decode($item->arguments, true);
                $block = [
                    'type' => 'tool_use',
                    'id' => $item->callId,
                    'name' => $item->name,
                    'input' => is_array($input) ? $input : [],
                ];
                $content[] = $block;
                $toolUseBlocks[] = $block;
            }
        }

        $stopReason = !empty($toolUseBlocks)
            ? 'tool_use'
            : ('incomplete' === $response->status ? 'max_tokens' : 'end_turn');

        return [$content, $toolUseBlocks, $stopReason];
    }
}
