<?php

namespace App\Services\Ai\Drivers\Contracts;

/**
 * One-shot (non-streaming) staging-ticket triage call for
 * AiTicketAnalyzerService, whichever provider is behind $model.
 *
 * $systemPrompt already fully specifies the task: the JSON schema the caller
 * expects back (see AiTicketAnalyzerService::buildSystemPrompt()), the SAP
 * module list, and the response contract. Implementations return that
 * response as a raw string for AiTicketAnalyzerService::extractJson() to
 * parse — including implementations that ask their provider to constrain the
 * output to valid JSON directly (see OpenAiTicketAnalysisDriver), so the
 * parsing/sanitizing that already exists in AiTicketAnalyzerService stays
 * unchanged and provider-agnostic either way.
 */
interface TicketAnalysisDriver
{
    public function analyze(
        string $model,
        string $systemPrompt,
        string $userMessage,
        int $maxTokens,
        ?string $effort,
    ): string;
}
