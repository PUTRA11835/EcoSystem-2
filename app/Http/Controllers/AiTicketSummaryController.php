<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Ticket;
use App\Services\Ai\AiTicketSummaryService;
use App\Services\Ai\TicketSummaryContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Tombol "AI Summarize" di daftar tiket.
 *
 * Hasilnya di-stream sebagai Server-Sent Events, pola yang sama dengan AI
 * Assistant, lalu disimpan ke cache dengan kunci ticket_id + SIDIK JARI ISI
 * TIKET (lihat TicketSummaryContext::fingerprint).
 *
 * Empat jenis event ke browser: `meta` (dari cache atau tidak), `delta` (potongan
 * teks), `status` (progres riset dokumentasi luar), dan `sources` (daftar rujukan
 * yang dibuka model) — dua yang terakhir berasal dari server tool web_search /
 * web_fetch di AiTicketSummaryService. Karena itu yang masuk cache adalah ARRAY
 * {text, sources}, bukan string; lihat CACHE_VERSION.
 *
 * Kenapa sidik jari dan bukan TTL saja: begitu tiket menerima pembaruan apa pun
 * - pesan baru, status berubah, activity log bertambah, deliverable di-update,
 * member berganti - sidik jarinya ikut berubah, kunci cache lama tidak pernah
 * kena lagi, dan klik berikutnya otomatis meringkas ulang. Tidak ada jalur di
 * mana user membaca ringkasan yang sudah tidak sesuai isi tiket, dan tidak ada
 * satu pun tempat di aplikasi yang perlu ingat untuk menghapus cache ini.
 *
 * Ringkasan lama tetap disimpan sebentar demi klik berulang yang murah; TTL di
 * bawah hanya penjaga agar barisnya tidak menumpuk selamanya.
 */
class AiTicketSummaryController extends Controller
{
    public const PERMISSION_SLUG = 'ui.ticket.btn-ai-summarize';

    private const CACHE_TTL_DAYS = 14;

    /**
     * Ikut masuk kunci cache. Dinaikkan setiap kali BENTUK atau ISI ringkasan
     * berubah secara mendasar — sidik jari tiket tidak menangkap itu, karena
     * yang berubah ada di sisi kita, bukan di tiketnya.
     *
     * v2 (24 Agu 2026): "Cara Penyelesaian" kini hasil riset dokumentasi luar
     * dan ringkasannya membawa daftar rujukan, jadi baris v1 (string polos,
     * penyelesaian dari work log) tidak boleh dipakai lagi.
     */
    private const CACHE_VERSION = 'v2';

    /** Ukuran potongan saat memutar ulang ringkasan dari cache. */
    private const REPLAY_CHUNK = 240;

    public function stream(Request $request, int $ticketId, AiTicketSummaryService $service, TicketSummaryContext $context): StreamedResponse
    {
        $sessionUser = session('user');
        if (!$sessionUser || 'employee' !== ($sessionUser['type'] ?? null)) {
            abort(401);
        }

        $employee = Employee::find($sessionUser['id']);
        if (!$employee) {
            abort(401);
        }

        if (!in_array(self::PERMISSION_SLUG, $employee->allPermissionSlugs(), true)) {
            abort(403);
        }

        $ticket = Ticket::findOrFail($ticketId);

        $fingerprint = $context->fingerprint($ticket);
        $cacheKey = "ai_ticket_summary:" . self::CACHE_VERSION . ":{$ticket->ticket_id}:{$fingerprint}";
        $cached = Cache::get($cacheKey);
        $cached = is_array($cached) ? $cached : null;

        // Lepas kunci file session sebelum stream panjang, supaya tab lain milik
        // user yang sama tidak ikut menunggu.
        $request->session()->save();

        return response()->stream(function () use ($service, $ticket, $cacheKey, $cached) {
            // Satu ringkasan bisa lewat dari max_execution_time bawaan PHP (30s)
            // dan mati sebagai fatal error di tengah stream - yang sampai ke
            // browser cuma HTTP 500 telanjang. Stream ini sudah dibatasi oleh
            // max_tokens dan connection_aborted(), jadi aman dilepas.
            set_time_limit(0);

            $send = function (string $event, array $payload): void {
                echo 'event: ' . $event . "\n";
                echo 'data: ' . json_encode($payload) . "\n\n";
                if (ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };

            $isAborted = fn(): bool => 1 === connection_aborted();

            try {
                if ($cached && '' !== ($cached['text'] ?? '')) {
                    $send('meta', ['cached' => true]);

                    foreach (mb_str_split($cached['text'], self::REPLAY_CHUNK) as $chunk) {
                        if ($isAborted()) {
                            return;
                        }
                        $send('delta', ['text' => $chunk]);
                    }

                    if (!empty($cached['sources'])) {
                        $send('sources', ['items' => $cached['sources']]);
                    }

                    $send('done', ['cached' => true]);

                    return;
                }

                $send('meta', ['cached' => false]);

                $result = $service->streamSummary(
                    $ticket,
                    fn(string $text) => $send('delta', ['text' => $text]),
                    $isAborted,
                    // 'status' (sedang mencari/membaca dokumentasi) dan 'sources'
                    // (daftar rujukan) diteruskan apa adanya ke modal.
                    fn(string $event, array $payload) => $send($event, $payload),
                );

                // Teks kosong = dibatalkan di tengah jalan. Menyimpannya berarti
                // klik berikutnya memutar ulang ringkasan yang terpotong.
                if ('' !== $result['text']) {
                    Cache::put($cacheKey, $result, now()->addDays(self::CACHE_TTL_DAYS));
                }

                $send('done', ['cached' => false]);
            } catch (Throwable $e) {
                Log::error('AI ticket summary failed', [
                    'ticket_id' => $ticket->ticket_id,
                    'message' => $e->getMessage(),
                ]);

                $send('error', ['message' => 'Ringkasan gagal dibuat. Coba lagi sebentar lagi.']);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }
}
