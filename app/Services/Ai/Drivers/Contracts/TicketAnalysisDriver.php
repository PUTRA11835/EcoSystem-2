<?php

namespace App\Services\Ai\Drivers\Contracts;

use Closure;

/**
 * Staging-ticket triage call for AiTicketAnalyzerService, whichever provider
 * is behind $model. Internally streaming (so the caller can surface REAL
 * progress instead of a fake time-based bar — see StagingTicketController::
 * analyze()), but the RESULT contract is still one complete string: this is
 * a structured-JSON response, not prose meant to be read as it arrives, so
 * partial text is never handed back to the caller mid-flight.
 *
 * $systemPrompt already fully specifies the task: the JSON schema the caller
 * expects back (see AiTicketAnalyzerService::buildSystemPrompt()), the SAP
 * module list, and the response contract. Implementations return that
 * response as a raw string for AiTicketAnalyzerService::extractJson() to
 * parse — including implementations that ask their provider to constrain the
 * output to valid JSON directly (see OpenAiTicketAnalysisDriver), so the
 * parsing/sanitizing that already exists in AiTicketAnalyzerService stays
 * unchanged and provider-agnostic either way.
 *
 * @param Closure(string, array): void $onEvent   'status' — a real progress
 *        label derived from the provider's own stream events (which tool/
 *        phase is active right now), NOT a fabricated percentage.
 * @param Closure(): bool              $isAborted polled between stream events
 *        so a closed connection stops the underlying request instead of
 *        burning the rest of the call for nobody.
 */
interface TicketAnalysisDriver
{
    public function analyze(
        string $model,
        string $systemPrompt,
        string $userMessage,
        int $maxTokens,
        ?string $effort,
        Closure $onEvent,
        Closure $isAborted,
    ): string;
}
