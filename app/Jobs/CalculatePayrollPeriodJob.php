<?php

namespace App\Jobs;

use App\Services\Payroll\PayrollEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * Hitung seluruh slip sebuah periode Payroll di LATAR BELAKANG (opsional — Payroll Settings → "Calculate in the background").
 * Hanya dipakai bila saklar menyala DAN antrean sungguhan dikonfigurasi (QUEUE_CONNECTION bukan `sync`) DAN ada worker
 * (`php artisan queue:work`). Hasilnya identik dengan perhitungan biasa: memanggil PayrollEngine::calculatePeriod() yang sama,
 * di dalam transaksi yang sama. Status tampil di halaman periode (calc_status: queued → running → done | failed).
 */
class CalculatePayrollPeriodJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 900;

    public function __construct(public int $periodId, public ?int $actorId)
    {
    }

    public function handle(PayrollEngine $engine): void
    {
        DB::table('payroll_periods')->where('id', $this->periodId)->update(['calc_status' => 'running', 'calc_error' => null, 'updated_at' => now()]);
        $summary = $engine->calculatePeriod($this->periodId, $this->actorId);
        DB::table('payroll_periods')->where('id', $this->periodId)->update(['calc_status' => 'done', 'calc_error' => null, 'updated_at' => now()]);
        \App\Services\Payroll\PayrollAudit::record(\App\Services\Payroll\PayrollAudit::CALCULATED, $this->periodId,
            'Calculated ' . ($summary['employees'] ?? 0) . ' employee(s) in the background' . (!empty($summary['skipped']) ? ', ' . $summary['skipped'] . ' skipped' : ''), $this->actorId, (array) $summary);
    }

    public function failed(\Throwable $e): void
    {
        DB::table('payroll_periods')->where('id', $this->periodId)->update(['calc_status' => 'failed', 'calc_error' => mb_substr($e->getMessage(), 0, 1000), 'updated_at' => now()]);
    }
}
