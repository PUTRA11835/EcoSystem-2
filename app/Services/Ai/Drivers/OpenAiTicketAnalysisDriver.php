<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Drivers\Contracts\TicketAnalysisDriver;
use App\Support\TicketClassification;
use OpenAI\Client;
use RuntimeException;

/**
 * GPT side of Ticket Analyzer. OpenAI has no Agent Skills/code-execution
 * container equivalent to Anthropic's "sap-ticket-analyzer" skill, so this
 * sends the same fully self-contained prompt AiTicketAnalyzerService already
 * builds (schema, module list, and ticket text all inlined — see
 * AiTicketAnalyzerService::buildSystemPrompt()/buildUserMessage()) and asks
 * the Responses API to constrain output to that JSON schema directly, rather
 * than relying on prompt instructions + regex extraction the way the Claude
 * path does. The enums are read from App\Support\TicketClassification — the
 * single source of truth shared with AiTicketAnalyzerService's sanitizeEnum()
 * calls — so this schema can't silently drift from what the caller accepts.
 */
class OpenAiTicketAnalysisDriver implements TicketAnalysisDriver
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
        $parameters = [
            'model' => $model,
            'instructions' => $systemPrompt,
            'input' => [
                ['role' => 'user', 'content' => [['type' => 'input_text', 'text' => $userMessage]]],
            ],
            'max_output_tokens' => $maxTokens,
            'store' => false,
            'text' => ['format' => $this->jsonSchema()],
        ];

        if ($effort) {
            $parameters['reasoning'] = ['effort' => $effort];
        }

        $response = $this->client->responses()->create($parameters);

        if ('completed' !== $response->status) {
            throw new RuntimeException(
                'OpenAI ticket analysis did not complete (status: ' . $response->status . ').'
            );
        }

        $text = (string) $response->outputText;
        if ('' === trim($text)) {
            throw new RuntimeException('GPT tidak mengembalikan teks analisa.');
        }

        return $text;
    }

    /**
     * Structured Outputs (strict mode) — setiap properti WAJIB masuk
     * `required` dan field nullable ditulis sebagai union type ['X','null'],
     * bukan lewat opsional/omit, itu aturan mode strict OpenAI.
     *
     * @return array<string, mixed>
     */
    private function jsonSchema(): array
    {
        return [
            'type' => 'json_schema',
            'name' => 'sap_ticket_triage',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => [
                    'overview', 'root_cause_hypothesis', 'resolution_steps', 'suggested_module_id',
                    'suggested_ticket_type', 'suggested_priority', 'suggested_scale', 'confidence', 'risks',
                ],
                'properties' => [
                    'overview' => ['type' => 'string'],
                    'root_cause_hypothesis' => ['type' => 'string'],
                    'resolution_steps' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'suggested_module_id' => ['type' => ['integer', 'null']],
                    'suggested_ticket_type' => [
                        'type' => ['string', 'null'],
                        'enum' => [...TicketClassification::TYPES, null],
                    ],
                    'suggested_priority' => [
                        'type' => ['string', 'null'],
                        'enum' => [...TicketClassification::PRIORITIES, null],
                    ],
                    'suggested_scale' => [
                        'type' => ['string', 'null'],
                        'enum' => [...TicketClassification::SCALES, null],
                    ],
                    'confidence' => ['type' => 'number'],
                    'risks' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
            ],
        ];
    }
}
