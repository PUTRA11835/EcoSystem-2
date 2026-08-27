<?php

namespace App\Jobs;

use App\Models\WordReport;
use App\Services\Ai\ReportGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
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

            $report->update([
                'status' => WordReport::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
