<?php

namespace App\Services\Ai\Drivers\Contracts;

use Closure;

/**
 * Runs one user turn of AiResearchService to completion, whichever provider
 * is behind $model (see AiDriverFactory).
 *
 * Messages travel in the canonical (Anthropic-shaped) format AiResearchService
 * already caches/archives — text/image/document blocks only, since Research
 * has no local tool_use/tool_result (its tools are server-side web search/fetch).
 *
 * Provider-internal continuation rounds that have NO cross-provider analog
 * (Claude's stopReason 'pause_turn', which requires resending raw provider
 * turn content AiResearchService's canonical text-only cache format cannot
 * represent) are fully resolved INSIDE the driver — including emitting the
 * 'notice' onEvent itself if the driver's own resume budget runs out. The
 * hard output-length ceiling is deliberately NOT retried in here:
 * AiResearchService's own MAX_LENGTH_RESUMES loop owns that, since it is a
 * user-facing "Continue" affordance (notice + button), not an implementation
 * detail to hide.
 */
interface ResearchDriver
{
    /**
     * @param array<int, array<string, mixed>> $messages canonical messages ending in the new/continuation user turn
     * @param Closure(string): void $onDelta called with each text chunk as it streams in
     * @param Closure(string, array<string, mixed>): void $onEvent 'status' (and, for Anthropic, 'notice') events
     * @param Closure(): bool $isAborted polled between network steps to allow early stop
     * @return array{0: array<int, array<string, mixed>>|null, 1: ?string, 2: array<int, array{url: string, title: string}>}
     *         [assistantTextBlocks (null if aborted), stopReason ('max_tokens'|'end_turn'), sources found this turn]
     */
    public function ask(
        string $model,
        string $systemPrompt,
        array $messages,
        int $maxTokens,
        ?string $effort,
        Closure $onDelta,
        Closure $onEvent,
        Closure $isAborted,
    ): array;
}
