<?php

namespace App\Jobs;

use App\Models\WordReport;
use App\Services\Ai\ReportGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Jalankan ReportGeneratorService::generate() di background — bisa beberapa
 * kali round-trip tool-calling + code execution/code interpreter di sandbox
 * provider (Claude atau GPT, lihat AiModelSettings::WORD_REPORT), jadi tidak
 * layak dijalankan synchronous di request HTTP (lihat keputusan di
 * ReportGeneratorController::generate()).
 */
class GenerateWordReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Satu retry otomatis: generate sekarang dipecah jadi 3 fase (lihat
     * ReportGeneratorService), hasil tiap fase disimpan ke WordReport
     * SEBELUM lanjut ke fase berikutnya -- jadi kalau satu attempt gagal di
     * tengah fase (mis. rate limit / timeout provider), attempt berikutnya
     * otomatis resume dari fase terakhir yang tersimpan, TIDAK mengulang
     * fase yang sudah sukses dari nol.
     */
    public int $tries = 2;

    /**
     * Jeda sebelum retry -- beri waktu rate limit/gangguan sesaat provider
     * (atau DNS/jaringan lokal, yang juga pernah kejadian) reda. 30s, bukan
     * 15s -- pernah kejadian 2 attempt berturut-turut (15s terpisah) sama-sama
     * kena blip DNS yang sama.
     */
    public int $backoff = 30;

    /** Baca template + beberapa tool call + code execution bisa makan waktu. */
    public int $timeout = 900;

    public function __construct(private int $wordReportId)
    {
        // Antrean SENDIRI, dikerjakan worker terpisah (lihat docker/supervisord.conf)
        // -- satu generate laporan bisa 5-10 menit; kalau menumpang antrean
        // 'default' ia memblokir semua job lain (email, notifikasi, event SLA)
        // di belakangnya selama itu.
        $this->onQueue('reports');
    }

    /**
     * Kunci per-laporan: HARAM ada dua job untuk WordReport yang sama jalan
     * bersamaan. Tanpa ini, `retry_after` queue yang lebih pendek dari durasi
     * job (job bisa 5-10 menit, default retry_after 90s) membuat queue menaruh
     * ulang job yang "kelihatan nyangkut" padahal masih jalan -- satu laporan
     * jadi di-generate berkali-kali paralel, tiap salinan bikin rantai
     * panggilan AI sendiri, dan rate limit provider langsung meledak.
     *
     * expireAfter(1800): kalau proses pemegang kunci mati mendadak (OOM /
     * container restart) tanpa sempat melepas, kunci otomatis kedaluwarsa 30
     * menit kemudian -- di atas $timeout job (900s) + jeda retry, jadi tidak
     * pernah memblokir attempt yang sah. dontRelease(): job yang kena kunci
     * tidak diantre ulang (yang sah sudah jalan / akan retry sendiri).
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->wordReportId))
                ->expireAfter(1800)
                ->dontRelease(),
        ];
    }

    public function handle(ReportGeneratorService $service): void
    {
        $report = WordReport::findOrFail($this->wordReportId);
        $report->update(['status' => WordReport::STATUS_PROCESSING]);

        try {
            $service->generate($report);

            // generate() sendiri yang set status jadi awaiting_input kalau AI
            // minta klarifikasi (lihat ReportGeneratorService) -- itu BUKAN
            // gagal, jangan ditimpa jadi completed.
            $report->refresh();
            if (WordReport::STATUS_AWAITING_INPUT !== $report->status) {
                $report->update(['status' => WordReport::STATUS_COMPLETED]);
            }
        } catch (Throwable $e) {
            Log::error('GenerateWordReportJob failed', [
                'word_report_id' => $this->wordReportId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            // Masih ada percobaan tersisa -> JANGAN tandai failed (status tetap
            // "processing" di mata user), rethrow supaya Laravel requeue
            // otomatis lewat $tries/$backoff. Fase yang sudah tersimpan di
            // $report (lihat ReportGeneratorService) TIDAK diulang di attempt
            // berikutnya. Baru catat sebagai gagal beneran di attempt terakhir
            // -- sebelum ini exception selalu ditelan di sini (tidak pernah
            // rethrow), jadi Laravel menganggap job SUKSES di attempt pertama
            // dan $tries tidak pernah benar-benar dipakai.
            if ($this->attempts() < $this->tries) {
                throw $e;
            }

            // Rate limit provider yang masih habis setelah SEMUA retry (internal
            // driver + $tries job): bukan kegagalan permanen -- fase yang sudah
            // sukses tetap tersimpan, user tinggal menekan "Lanjutkan". Ditandai
            // "paused", bukan "failed", supaya UI tidak menampilkan pesan error
            // mentah (lihat WordReport::STATUS_PAUSED).
            if ($this->isResumableRateLimit($e)) {
                $report->update([
                    'status' => WordReport::STATUS_PAUSED,
                    'error_message' => 'Layanan AI sedang sibuk (batas pemakaian sesaat tercapai). '
                        . 'Progres yang sudah selesai tersimpan — klik "Lanjutkan" untuk meneruskan.',
                ]);

                return;
            }

            $report->update([
                'status' => WordReport::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dipanggil Laravel saat job benar-benar menyerah: attempt terakhir
     * melempar exception, ATAU job kena timeout keras ($timeout) / dianggap
     * "attempted too many times". Tanpa handler ini, kasus timeout membuat
     * status nyangkut selamanya di "processing" (handle()::catch tidak pernah
     * jalan) -- user melihat loading yang tidak pernah selesai.
     */
    public function failed(?Throwable $e): void
    {
        $report = WordReport::find($this->wordReportId);

        if (!$report || in_array($report->status, [WordReport::STATUS_COMPLETED, WordReport::STATUS_AWAITING_INPUT], true)) {
            return;
        }

        if ($e && $this->isResumableRateLimit($e)) {
            $report->update([
                'status' => WordReport::STATUS_PAUSED,
                'error_message' => 'Layanan AI sedang sibuk (batas pemakaian sesaat tercapai). '
                    . 'Progres yang sudah selesai tersimpan — klik "Lanjutkan" untuk meneruskan.',
            ]);

            return;
        }

        $report->update([
            'status' => WordReport::STATUS_FAILED,
            'error_message' => $e?->getMessage()
                ?? 'Proses berhenti sebelum selesai (kemungkinan melebihi batas waktu). Coba lagi.',
        ]);
    }

    /**
     * Apakah exception ini rate limit provider (aman dilanjutkan)? Cek kelas
     * exception-nya sendiri DAN `previous`-nya -- OpenAiReportDriver membungkus
     * OpenAI\Exceptions\RateLimitException jadi RuntimeException setelah retry
     * internalnya habis, jadi yang menjalar ke sini adalah wrapper-nya.
     */
    private function isResumableRateLimit(Throwable $e): bool
    {
        for ($cur = $e; null !== $cur; $cur = $cur->getPrevious()) {
            $class = $cur::class;
            if (str_contains($class, 'RateLimit') || str_contains($class, 'Overloaded')) {
                return true;
            }
        }

        return str_contains($e->getMessage(), 'Rate limit');
    }
}
