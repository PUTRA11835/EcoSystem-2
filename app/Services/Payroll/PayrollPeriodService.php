<?php

namespace App\Services\Payroll;

use App\Models\PayrollSetting;
use App\Support\Payroll\Money;
use App\Support\Payroll\PayrollPeriodRules;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Siklus hidup periode payroll (alur ESH): buat → hitung → Kunci; Generate Slip (nomor slip + kode verifikasi),
 * hapus, saklar BPJS/PPh 21 per karyawan, dan Koreksi Payroll. Perubahan status periode melewati
 * PayrollPeriodRules::canTransition().
 *
 * Saklar aktivasi ada di Payroll → Settings (`payroll_settings.module_enabled`, HC-D21), MATI secara bawaan: selama mati,
 * tidak ada periode yang bisa dibuat/dihitung/diubah statusnya. Membaca, Settings, dan Simulasi tetap boleh.
 * Menyalakannya butuh slug `finance.payroll.activate` dan tercatat (siapa, kapan, jejak audit).
 */
class PayrollPeriodService
{
    public function __construct(private readonly PayrollEngine $engine)
    {
    }

    public function isEnabled(): bool
    {
        return PayrollSetting::isEnabled();
    }

    /** @return string[] galat; kosong = berhasil. $id diisi bila sukses. */
    public function create(array $in, ?int $actorId, ?int &$id = null): array
    {
        if ($e = $this->disabledError()) {
            return [$e];
        }

        $clean = [
            'name' => trim((string) ($in['name'] ?? '')), 'period_start' => (string) ($in['period_start'] ?? ''),
            'period_end' => (string) ($in['period_end'] ?? ''), 'pay_date' => (string) ($in['pay_date'] ?? ''),
            'attendance_start' => (string) ($in['attendance_start'] ?? ''), 'attendance_end' => (string) ($in['attendance_end'] ?? ''),
            'cutoff_date' => (string) ($in['cutoff_date'] ?? ''),
        ];
        $others = DB::table('payroll_periods')->get(['name', 'period_start', 'period_end'])
            ->map(fn ($r) => ['name' => $r->name, 'period_start' => (string) $r->period_start, 'period_end' => (string) $r->period_end])->all();
        if ($errors = PayrollPeriodRules::validate($clean, $others)) {
            return $errors;
        }

        $id = DB::table('payroll_periods')->insertGetId(array_map(fn ($v) => $v === '' ? null : $v, $clean) + [
            'status' => PayrollPeriodRules::OPEN, 'notes' => isset($in['notes']) ? mb_substr(trim((string) $in['notes']), 0, 1000) : null,
            'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [];
    }

    /** @return string[] */
    public function calculate(int $periodId, ?int $actorId, ?array &$summary = null): array
    {
        if ($e = $this->disabledError()) {
            return [$e];
        }
        try {
            $summary = $this->engine->calculatePeriod($periodId, $actorId);
        } catch (InvalidArgumentException $e) {
            return [$e->getMessage()];
        }

        return [];
    }

    /** Kunci periode (open → locked). Periode terkunci tidak bisa dihitung ulang, dikoreksi, atau dihapus. @return string[] */
    public function lock(int $periodId, ?int $actorId): array
    {
        if ($e = $this->disabledError()) {
            return [$e];
        }

        return DB::transaction(function () use ($periodId, $actorId) {
            $p = DB::table('payroll_periods')->where('id', $periodId)->lockForUpdate()->first();
            if (!$p) {
                return ['Payroll period not found.'];
            }
            if (!PayrollPeriodRules::canTransition($p->status, PayrollPeriodRules::LOCKED)) {
                return ["A {$p->status} period cannot be locked."];
            }
            if ($p->calculated_at === null || DB::table('payroll_slips')->where('period_id', $periodId)->count() === 0) {
                return ['Calculate the payroll first — there is nothing to lock.'];
            }
            DB::table('payroll_periods')->where('id', $periodId)->update([
                'status' => PayrollPeriodRules::LOCKED, 'locked_at' => now(), 'locked_by' => $actorId, 'updated_at' => now(),
            ]);

            return [];
        });
    }

    /**
     * Generate Slip: memberi NOMOR SLIP (urutan berjalan global) dan kode verifikasi pada slip yang belum punya;
     * slip yang sudah dibuat tidak diubah. Boleh pada periode open maupun locked (dokumen, bukan perubahan angka).
     *
     * @return string[] galat; $count = jumlah slip yang baru diberi nomor
     */
    public function generateSlips(int $periodId, ?int $actorId, ?int &$count = null): array
    {
        $count = 0;
        if ($e = $this->disabledError()) {
            return [$e];
        }

        return DB::transaction(function () use ($periodId, $actorId, &$count) {
            $p = DB::table('payroll_periods')->where('id', $periodId)->lockForUpdate()->first();
            if (!$p) {
                return ['Payroll period not found.'];
            }
            if (DB::table('payroll_slips')->where('period_id', $periodId)->count() === 0) {
                return ['Calculate the payroll first — there are no payslips to generate.'];
            }
            $slips = DB::table('payroll_slips')->where('period_id', $periodId)->whereNull('slip_no')->orderBy('employee_name')->lockForUpdate()->get();
            $next = (int) DB::table('payroll_slips')->max('slip_no');
            $now = now();
            foreach ($slips as $s) {
                $no = ++$next;
                DB::table('payroll_slips')->where('id', $s->id)->update([
                    'slip_no' => $no, 'status' => 'generated', 'slip_generated_at' => $now, 'slip_generated_by' => $actorId,
                    'verification_code' => PayrollEngine::verificationCode($periodId, (int) $s->employee_id, $no, (float) $s->take_home_pay, (float) $s->gross_earnings),
                    'updated_at' => $now,
                ]);
                $count++;
            }
            DB::table('payroll_periods')->where('id', $periodId)->update(['slips_generated_at' => $now, 'slips_generated_by' => $actorId, 'updated_at' => $now]);

            return [];
        });
    }

    /**
     * Publish Payslip: membuat slip yang sudah di-Generate terlihat oleh karyawan di ESS → Paystub. Slip yang belum
     * di-Generate tidak ikut; slip yang dihitung ulang kembali menjadi "belum dipublikasikan".
     *
     * @return string[] galat; $count = jumlah slip yang baru dipublikasikan
     */
    public function publish(int $periodId, ?int $actorId, ?int &$count = null): array
    {
        $count = 0;
        if ($e = $this->disabledError()) {
            return [$e];
        }

        return DB::transaction(function () use ($periodId, $actorId, &$count) {
            $p = DB::table('payroll_periods')->where('id', $periodId)->lockForUpdate()->first();
            if (!$p) {
                return ['Payroll period not found.'];
            }
            if (!DB::table('payroll_slips')->where('period_id', $periodId)->whereNotNull('slip_no')->exists()) {
                return ['Generate the payslips first — there is nothing to publish.'];
            }
            $now = now();
            $count = DB::table('payroll_slips')->where('period_id', $periodId)->whereNotNull('slip_no')->whereNull('published_at')
                ->update(['published_at' => $now, 'published_by' => $actorId, 'updated_at' => $now]);
            DB::table('payroll_periods')->where('id', $periodId)->update(['published_at' => $now, 'published_by' => $actorId, 'updated_at' => $now]);

            return [];
        });
    }

    /**
     * "Terapkan & Hitung Ulang": simpan saklar BPJS Kes / BPJS TK / PPh 21 satu karyawan untuk periode ini lalu hitung ulang
     * slipnya. $toggles = ['bpjs_health'=>bool,'bpjs_employment'=>bool,'pph21'=>bool], atau null = hanya hitung ulang.
     *
     * @return string[]
     */
    public function recalculateEmployee(int $periodId, int $employeeId, ?array $toggles, ?int $actorId, ?string &$name = null): array
    {
        if ($e = $this->disabledError()) {
            return [$e];
        }
        $p = DB::table('payroll_periods')->where('id', $periodId)->first();
        if (!$p || !PayrollPeriodRules::isEditable($p->status)) {
            return ['Only an open payroll period can be recalculated.'];
        }
        try {
            DB::transaction(function () use ($periodId, $employeeId, $toggles, $actorId, &$name) {
                if ($toggles !== null) {
                    $vals = [];
                    foreach (['bpjs_health', 'bpjs_employment', 'pph21'] as $k) {
                        $vals[$k] = !empty($toggles[$k]);
                    }
                    $key = ['period_id' => $periodId, 'employee_id' => $employeeId];
                    if (DB::table('payroll_employee_overrides')->where($key)->exists()) {
                        DB::table('payroll_employee_overrides')->where($key)->update($vals + ['updated_by' => $actorId, 'updated_at' => now()]);
                    } else {
                        DB::table('payroll_employee_overrides')->insert($key + $vals + ['updated_by' => $actorId, 'created_at' => now(), 'updated_at' => now()]);
                    }
                }
                $name = $this->engine->calculateEmployee($periodId, $employeeId, $actorId)['name'];
            });
        } catch (InvalidArgumentException $e) {
            return [$e->getMessage()];
        }

        return [];
    }

    /** Hapus periode terbuka beserta slip & koreksinya (cascade). @return string[] */
    public function delete(int $periodId): array
    {
        if ($e = $this->disabledError()) {
            return [$e];
        }
        $p = DB::table('payroll_periods')->where('id', $periodId)->first();
        if (!$p) {
            return ['Payroll period not found.'];
        }
        if (!PayrollPeriodRules::isEditable($p->status)) {
            return ['Only an open payroll period can be deleted.'];
        }
        if (DB::table('payroll_slips')->where('period_id', $periodId)->whereNotNull('slip_no')->exists()) {
            return ['Payslips have already been generated for this period, so it cannot be deleted.'];
        }
        DB::table('payroll_periods')->where('id', $periodId)->delete();

        return [];
    }

    /**
     * Koreksi Payroll: kurang bayar (underpaid → penghasilan kena pajak), lebih bayar (overpaid → potongan) dari periode
     * sebelumnya, atau penyesuaian biasa (kind earning|deduction).
     *
     * @return string[]
     */
    public function addAdjustment(int $periodId, array $in, ?int $actorId): array
    {
        if ($e = $this->disabledError()) {
            return [$e];
        }
        $p = DB::table('payroll_periods')->where('id', $periodId)->first();
        if (!$p || !PayrollPeriodRules::isEditable($p->status)) {
            return ['Corrections can only be added to an open payroll period.'];
        }

        $employeeId = (int) ($in['employee_id'] ?? 0);
        $category = (string) ($in['category'] ?? 'adjustment');
        $kind = $category === 'underpaid' ? 'earning' : ($category === 'overpaid' ? 'deduction' : (string) ($in['kind'] ?? ''));
        $component = trim((string) ($in['component'] ?? ''));
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '' && $component !== '') {
            $name = $component;
        }
        $amount = Money::parse($in['amount'] ?? null);
        $source = (int) ($in['source_period_id'] ?? 0) ?: null;

        $errors = [];
        if (!DB::table('employee')->where('employee_id', $employeeId)->exists()) { $errors[] = 'Choose an employee.'; }
        if (!in_array($category, ['adjustment', 'underpaid', 'overpaid'], true)) { $errors[] = 'Choose a correction type.'; }
        if (!in_array($kind, ['earning', 'deduction'], true)) { $errors[] = 'Choose earning or deduction.'; }
        if ($category !== 'adjustment' && (!$source || $source === $periodId || !DB::table('payroll_periods')->where('id', $source)->exists())) {
            $errors[] = 'Choose the earlier period this correction comes from.';
        }
        if ($category === 'adjustment') { $source = null; }
        if ($name === '' || mb_strlen($name) > 150) { $errors[] = 'Enter a description (maximum 150 characters).'; }
        if (mb_strlen($component) > 150) { $errors[] = 'The component name is too long (maximum 150 characters).'; }
        if ($amount === null || $amount <= 0 || $amount > 9_999_999_999_999) { $errors[] = 'Enter an amount greater than zero.'; }
        if ($errors) {
            return $errors;
        }

        DB::table('payroll_adjustments')->insert([
            'period_id' => $periodId, 'employee_id' => $employeeId, 'kind' => $kind, 'category' => $category,
            'source_period_id' => $source, 'component' => $component !== '' ? $component : null, 'name' => $name,
            'amount' => round($amount, 2), 'taxable' => $kind === 'earning' && ($category === 'underpaid' || !empty($in['taxable'])),
            'notes' => isset($in['notes']) ? mb_substr(trim((string) $in['notes']), 0, 500) : null,
            'created_by' => $actorId, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [];
    }

    /** @return string[] */
    public function deleteAdjustment(int $periodId, int $adjustmentId): array
    {
        if ($e = $this->disabledError()) {
            return [$e];
        }
        $p = DB::table('payroll_periods')->where('id', $periodId)->first();
        if (!$p || !PayrollPeriodRules::isEditable($p->status)) {
            return ['Corrections can only be changed on an open payroll period.'];
        }
        DB::table('payroll_adjustments')->where('id', $adjustmentId)->where('period_id', $periodId)->delete();

        return [];
    }

    private function disabledError(): ?string
    {
        return $this->isEnabled() ? null
            : 'The payroll module is not switched on yet. Switch it on in Payroll → Settings (needs the "Switch payroll on/off" permission). Settings and Simulation work in the meantime.';
    }
}
