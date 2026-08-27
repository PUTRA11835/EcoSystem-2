<?php

namespace App\Services\Ai\Drivers;

use Anthropic\Beta\Messages\BetaCodeExecutionTool20260521;
use Anthropic\Beta\Messages\BetaContainerParams;
use Anthropic\Beta\Messages\BetaSkillParams;
use Anthropic\Client;
use App\Services\Ai\Drivers\Contracts\TicketAnalysisDriver;
use RuntimeException;

/**
 * Claude side of Ticket Analyzer — Agent Skills custom "sap-ticket-analyzer"
 * + code execution container, moved here verbatim from what used to live
 * directly in AiTicketAnalyzerService::analyze() before GPT became a second
 * provider. There is no OpenAI equivalent of Agent Skills — see
 * OpenAiTicketAnalysisDriver for how that side compensates instead.
 */
class AnthropicTicketAnalysisDriver implements TicketAnalysisDriver
{
    public function __construct(private Client $client)
    {
    }

    public function analyze(
        string $model,
        string $systemPrompt,
        string $userMessage,
        int $maxTokens,
        ?string $effort,
    ): string {
        $skillId = config('services.anthropic.ticket_analyzer_skill_id');
        if (!$skillId) {
            throw new RuntimeException('ANTHROPIC_TICKET_ANALYZER_SKILL_ID belum diatur di .env.');
        }

        $message = $this->client->beta->messages->create(
            maxTokens: $maxTokens,
            model: $model,
            system: $systemPrompt,
            messages: [['role' => 'user', 'content' => $userMessage]],
            outputConfig: $effort ? ['effort' => $effort] : null,
            container: BetaContainerParams::with(skills: [
                BetaSkillParams::with(skillID: $skillId, type: 'custom', version: 'latest'),
            ]),
            tools: [BetaCodeExecutionTool20260521::with()],
            betas: ['code-execution-2025-08-25', 'skills-2025-10-02'],
        );

        foreach ($message->content as $block) {
            if ('text' === $block->type) {
                return $block->text;
            }
        }

        throw new RuntimeException('Claude tidak mengembalikan teks analisa.');
    }
}
