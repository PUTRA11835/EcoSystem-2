<?php

namespace App\Services\Ai\Drivers\Concerns;

/**
 * Shared by every OpenAi*Driver: translates the canonical Anthropic-shaped
 * messages/tools (see ChatDriver's docblock) into OpenAI Responses API
 * `input` items and function tool definitions.
 *
 * Anthropic embeds tool_use/tool_result INSIDE a message's content array;
 * the Responses API instead uses separate top-level `function_call` /
 * `function_call_output` input items addressed by `call_id` — so a canonical
 * message with mixed content gets split into sibling items in the flat
 * `input` array, in original order.
 */
trait TranslatesCanonicalMessages
{
    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    private function toResponsesInput(array $messages): array
    {
        $input = [];

        foreach ($messages as $message) {
            $role = $message['role'] ?? 'user';
            $blocks = is_array($message['content'] ?? null) ? $message['content'] : [];
            $contentParts = [];

            $flush = function () use (&$contentParts, &$input, $role): void {
                if (!empty($contentParts)) {
                    $input[] = ['role' => $role, 'content' => $contentParts];
                    $contentParts = [];
                }
            };

            foreach ($blocks as $block) {
                if (!is_array($block)) {
                    continue;
                }

                switch ($block['type'] ?? null) {
                    case 'text':
                        $contentParts[] = [
                            'type' => 'assistant' === $role ? 'output_text' : 'input_text',
                            'text' => (string) ($block['text'] ?? ''),
                        ];
                        break;

                    case 'image':
                        $source = $block['source'] ?? [];
                        $mediaType = $source['media_type'] ?? 'image/png';
                        $data = $source['data'] ?? '';
                        $contentParts[] = [
                            'type' => 'input_image',
                            'image_url' => "data:{$mediaType};base64,{$data}",
                        ];
                        break;

                    case 'document':
                        $source = $block['source'] ?? [];
                        $mediaType = $source['media_type'] ?? 'application/pdf';
                        $data = $source['data'] ?? '';
                        $contentParts[] = [
                            'type' => 'input_file',
                            'file_data' => "data:{$mediaType};base64,{$data}",
                            'filename' => 'attachment',
                        ];
                        break;

                    case 'tool_use':
                        $flush();
                        $input[] = [
                            'type' => 'function_call',
                            'call_id' => (string) ($block['id'] ?? ''),
                            'name' => (string) ($block['name'] ?? ''),
                            'arguments' => json_encode($block['input'] ?? [], JSON_UNESCAPED_UNICODE) ?: '{}',
                        ];
                        break;

                    case 'tool_result':
                        $flush();
                        $input[] = [
                            'type' => 'function_call_output',
                            'call_id' => (string) ($block['tool_use_id'] ?? ''),
                            'output' => (string) ($block['content'] ?? ''),
                        ];
                        break;
                }
            }

            $flush();
        }

        return $input;
    }

    /**
     * Canonical {name, description, input_schema} tool defs -> Responses API function tools.
     *
     * @param array<int, array<string, mixed>> $tools
     * @return array<int, array<string, mixed>>
     */
    private function toResponsesTools(array $tools): array
    {
        return array_map(static fn (array $tool): array => [
            'type' => 'function',
            'name' => $tool['name'],
            'description' => $tool['description'] ?? '',
            'parameters' => $tool['input_schema'] ?? ['type' => 'object', 'properties' => []],
        ], $tools);
    }
}
