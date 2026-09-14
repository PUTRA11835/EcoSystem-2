<?php

namespace App\Support;

use App\Models\Ticket;

/**
 * Konteks awal room AI Research BERSAMA — dibangun dari hasil AI Analyzer
 * (staging_tickets.ai_analysis, lihat AiTicketAnalyzerService::analyze())
 * saat StagingTicketController::approve() membuat room-nya, BUKAN dari
 * TicketSummaryContext seperti AiResearchController::ticketContextSeed()
 * (yang dipakai jalur privat/legacy — tombol "Ask AI Research" untuk tiket
 * lama). Konten di sini SUDAH kerja analisa, bukan cuma ringkasan status
 * tiket: overview, dugaan akar masalah, dan langkah penyelesaian yang
 * disarankan — supaya konsultan yang buka room langsung tahu titik mula
 * pengerjaan, bukan mulai dari nol.
 *
 * Prefiks baris pertama SENGAJA sama persis dengan AIR_TICKET_CONTEXT_PREFIX
 * (resources/views/ai/research.blade.php) supaya pesan ini otomatis dirender
 * sebagai kartu collapsed yang sudah ada — tidak perlu ubah JS.
 *
 * @param array<string, mixed> $analysis bentuk persis StagingTicket::ai_analysis
 *        (lihat AiTicketAnalyzerService::analyze()) — overview,
 *        root_cause_hypothesis, resolution_steps[], risks[],
 *        suggested_assignees[], dll.
 */
class TicketAnalysisSeed
{
    public static function build(Ticket $ticket, array $analysis): string
    {
        $sections = [];

        $overview = trim((string) ($analysis['overview'] ?? ''));
        if ('' !== $overview) {
            $sections[] = "**Overview**\n{$overview}";
        }

        $rootCause = trim((string) ($analysis['root_cause_hypothesis'] ?? ''));
        if ('' !== $rootCause) {
            $sections[] = "**Dugaan akar masalah**\n{$rootCause}";
        }

        $steps = array_values(array_filter((array) ($analysis['resolution_steps'] ?? [])));
        if (!empty($steps)) {
            $list = collect($steps)->map(fn ($step, $i) => ($i + 1) . '. ' . trim((string) $step))->implode("\n");
            $sections[] = "**Langkah penyelesaian yang disarankan**\n{$list}";
        }

        $risks = array_values(array_filter((array) ($analysis['risks'] ?? [])));
        if (!empty($risks)) {
            $list = collect($risks)->map(fn ($risk) => '- ' . trim((string) $risk))->implode("\n");
            $sections[] = "**Risiko yang perlu diperhatikan**\n{$list}";
        }

        $assignees = array_values(array_filter((array) ($analysis['suggested_assignees'] ?? [])));
        if (!empty($assignees)) {
            $list = collect($assignees)->take(5)->map(function ($a) {
                $name = $a['name'] ?? 'Unknown';
                $tags = array_filter([
                    ($a['is_module_lead'] ?? false) ? 'Module Lead' : null,
                    isset($a['similar_issues_handled']) && $a['similar_issues_handled'] > 0
                        ? $a['similar_issues_handled'] . ' isu serupa pernah ditangani'
                        : null,
                ]);
                $suffix = $tags ? ' (' . implode(', ', $tags) . ')' : '';

                return '- ' . $name . $suffix;
            })->implode("\n");
            $sections[] = "**Kandidat paling cocok mengerjakan tiket ini**\n{$list}";
        }

        $body = implode("\n\n", $sections) ?: 'Analisa AI tidak memberi rincian tambahan untuk tiket ini.';

        return <<<TEXT
            📋 Konteks tiket (otomatis — hasil AI Analyzer saat tiket ini divalidasi, lihat halaman tiket untuk detail lengkap):

            Ticket #{$ticket->ticket_number}: {$ticket->description}

            {$body}

            ---
            Catatan: ini hasil analisa OTOMATIS saat validasi, bukan jawaban final —
            kalau saya bertanya, fokuskan jawaban pada memvalidasi/memperdalam
            langkah penyelesaian di atas dengan solusi teknis konkret, cari
            dokumentasi resmi (web search) kalau perlu.
            TEXT;
    }
}
