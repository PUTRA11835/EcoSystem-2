<?php

namespace App\Services\Ai\Drivers;

use Anthropic\Client;
use Anthropic\Messages\InputJSONDelta;
use Anthropic\Messages\TextDelta;
use Anthropic\Messages\ToolUseBlock;
use App\Services\Ai\Drivers\Contracts\ChatDriver;
use Closure;

/**
 * Claude side of AiChatService's turn — this is exactly the logic that used
 * to live directly in AiChatService before GPT became a second provider.
 * Messages/tools arrive already in Anthropic's own shape, so no translation
 * is needed here (compare OpenAiChatDriver, which does the translating).
 */
class AnthropicChatDriver implements ChatDriver
{
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
        $stream = $this->client->messages->createStream(
            maxTokens: $maxTokens,
            messages: $messages,
            model: $model,
            system: $systemPrompt,
            thinking: $effort ? ['type' => 'adaptive'] : null,
            outputConfig: $effort ? ['effort' => $effort] : null,
            tools: $tools,
        );

        return $this->consumeStream($stream, $onDelta, $isAborted);
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
}
