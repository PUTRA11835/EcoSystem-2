<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;

/**
 * Uji "apakah queue worker hidup": tombol "Run worker test" (Payroll Settings) mengirim job kecil ini. Bila ada worker, job
 * dikerjakan dan mencatat waktunya di cache; halaman lalu menampilkan "worker responded in N seconds". Tidak menyentuh data apa pun.
 */
class QueueHeartbeatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public function __construct(public string $token)
    {
    }

    public function handle(): void
    {
        Cache::put('queue_test_done_' . $this->token, now()->timestamp, now()->addMinutes(30));
        Cache::put('queue_worker_last_seen', now()->timestamp, now()->addDays(7));
    }
}
