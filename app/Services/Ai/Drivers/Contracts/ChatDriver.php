<?php

namespace App\Services\Ai\Drivers\Contracts;

use Closure;

/**
 * Runs exactly one model turn for AiChatService's local-tool loop, whichever
 * provider is behind $model (see AiDriverFactory).
 *
 * Messages and tools travel in the SAME canonical (Anthropic-shaped) format
 * AiChatService already caches — implementations translate to/from their own
 * wire format internally, so runTools(), buildUserContent(), and the chat
 * cache stay provider-agnostic:
 *   text:        ['type' => 'text', 'text' => string]
 *   tool_use:    ['type' => 'tool_use', 'id' => string, 'name' => string, 'input' => array]
 *   tool_result: ['type' => 'tool_result', 'tool_use_id' => string, 'content' => string, 'is_error'?: bool]
 *   image:       ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => string, 'data' => string]]
 * Tool definitions: ['name' => string, 'description' => string, 'input_schema' => array] (JSON Schema).
 */
interface ChatDriver
{
    /**
     * @param array<int, array<string, mixed>> $messages canonical messages, oldest first
     * @param array<int, array<string, mixed>> $tools canonical tool definitions
     * @param Closure(string): void $onDelta called with each text chunk as it streams in
     * @param Closure(): bool $isAborted polled between network steps to allow early stop
     * @return array{0: array<int, array<string, mixed>>|null, 1: array<int, array<string, mixed>>, 2: ?string}
     *         [assistantContentBlocks (null if aborted mid-stream), toolUseBlocks, stopReason]
     *         stopReason is one of 'tool_use' | 'max_tokens' | 'end_turn' (or a raw provider value
     *         for anything the caller only ever compares by inequality to those).
     */
    public function turn(
        string $model,
        string $systemPrompt,
        array $messages,
        array $tools,
        int $maxTokens,
        ?string $effort,
        Closure $onDelta,
        Closure $isAborted,
    ): array;
}
