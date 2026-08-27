<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Drivers\Contracts\ChatDriver;
use App\Services\Ai\Drivers\Contracts\ReportGenerationDriver;
use App\Services\Ai\Drivers\Contracts\ResearchDriver;
use App\Services\Ai\Drivers\Contracts\TicketAnalysisDriver;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves the provider driver for whatever AiModelSettings::resolve()
 * returned as 'provider' ('anthropic'|'openai'). Kept as a thin container
 * lookup — not a `new`-er — so Anthropic\Client/OpenAI\Client keep coming
 * from their existing AppServiceProvider singletons (and any constructor
 * dependency a future driver needs resolves the normal Laravel way).
 *
 * Unknown/legacy provider values fall back to Anthropic: DEFAULTS in
 * AiModelSettings are all 'anthropic', and app_configs rows written before
 * this class existed have no 'provider' key at all — self-healing the same
 * way AiModelSettings::sanitize() already self-heals unknown models.
 */
class AiDriverFactory
{
    public function __construct(private Container $container)
    {
    }

    public function chat(string $provider): ChatDriver
    {
        return $this->container->make('openai' === $provider ? OpenAiChatDriver::class : AnthropicChatDriver::class);
    }

    public function research(string $provider): ResearchDriver
    {
        return $this->container->make('openai' === $provider ? OpenAiResearchDriver::class : AnthropicResearchDriver::class);
    }

    public function ticketAnalysis(string $provider): TicketAnalysisDriver
    {
        return $this->container->make(
            'openai' === $provider ? OpenAiTicketAnalysisDriver::class : AnthropicTicketAnalysisDriver::class
        );
    }

    public function report(string $provider): ReportGenerationDriver
    {
        return $this->container->make(
            'openai' === $provider ? OpenAiReportDriver::class : AnthropicReportDriver::class
        );
    }
}
