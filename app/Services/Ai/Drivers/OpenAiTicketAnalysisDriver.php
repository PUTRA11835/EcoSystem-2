<?php

namespace App\Services\Ai\Drivers;

use App\Services\Ai\Drivers\Contracts\TicketAnalysisDriver;
use App\Support\TicketClassification;
use Closure;
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
 *
 * STREAMING sejak 10 Sep 2026 (`createStreamed()`, bukan lagi `create()`) —
 * murni supaya ada status ASLI untuk diteruskan lewat $onEvent, sejajar
 * dengan sisi Claude. `text.format` (Structured Outputs strict mode) dibawa
 * apa adanya ke `createStreamed()`: parameternya identik, SDK cuma
 * menambahkan `stream:true` di baliknya (dikonfirmasi dari sumber SDK, bukan
 * asumsi) — jadi output tetap dijamin valid sesuai skema, cuma cara
 * menerimanya yang berubah dari satu respons utuh jadi rentetan delta teks
 * yang diakumulasi di sini.
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
        Closure $onEvent,
        Closure $isAborted,
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

        $stream = $this->client->responses()->createStreamed($parameters);

        $full = '';
        $sawText = false;

        foreach ($stream as $event) {
            if ($isAborted()) {
                throw new RuntimeException('Analisa dibatalkan (koneksi ditutup).');
            }

            switch ($event->event) {
                case 'response.output_text.delta':
                    // Sekali saja saat delta pertama tiba — sebelum ini,
                    // model masih "berpikir" (reasoning) tanpa ada apa pun
                    // yang bisa dilaporkan sebagai status nyata.
                    if (!$sawText) {
                        $sawText = true;
                        $onEvent('status', ['label' => 'Writing analysis…']);
                    }
                    $full .= $event->response->delta;
                    break;

                case 'response.completed':
                    if ('' === trim($full)) {
                        throw new RuntimeException('GPT tidak mengembalikan teks analisa.');
                    }

                    return $full;

                case 'response.incomplete':
                case 'response.failed':
                    $status = $event->response->response->status ?? $event->event;
                    throw new RuntimeException("OpenAI ticket analysis did not complete (status: {$status}).");

                case 'error':
                    throw new RuntimeException('OpenAI stream error: ' . $event->response->message);
            }
        }

        // Stream berakhir tanpa event terminal (koneksi putus di tengah jalan).
        throw new RuntimeException('OpenAI ticket analysis stream ended unexpectedly.');
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
                    'overview', 'root_cause_hypothesis', 'resolution_steps', 'suggested_module_ids',
                    'suggested_ticket_type', 'suggested_priority', 'suggested_scale', 'confidence', 'risks',
                ],
                'properties' => [
                    'overview' => ['type' => 'string'],
                    'root_cause_hypothesis' => ['type' => 'string'],
                    'resolution_steps' => ['type' => 'array', 'items' => ['type' => 'string']],
                    // Sisa sebelum migrasi multi-modul dulu di sini singular
                    // ('suggested_module_id', integer) — AiTicketAnalyzerService
                    // ::analyze() (baris ~157) sudah lama cuma baca key PLURAL
                    // ini, jadi jalur OpenAI (strict schema override instruksi
                    // teks di buildSystemPrompt()) diam-diam SELALU kembalikan
                    // array kosong tidak peduli modul apa yang sebenarnya
                    // disarankan model. array kosong [] (bukan null) untuk
                    // "tidak ada yang cocok", sama seperti dokumentasi prompt.
                    'suggested_module_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
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
