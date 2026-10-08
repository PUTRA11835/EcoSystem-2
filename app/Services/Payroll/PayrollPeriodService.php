<?php

namespace App\Services\Payroll;

use App\Models\PayrollSetting;
use App\Support\Payroll\Money;
use App\Support\Payroll\PayrollPeriodRules;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Siklus hidup periode payroll: buat, hitung, setujui, buka kembali, tandai dibayar, kunci, hapus,
 * dan penyesuaian manual. Semua perubahan status melewati PayrollPeriodRules::canTransition().
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
        ];
        $others = DB::table('payroll_periods')->get(['name', 'period_start', 'period_end'])
            ->map(fn ($r) => ['name' => $r->name, 'period_start' => (string) $r->period_start, 'period_end' => (string) $r->period_end])->all();
        if ($errors = PayrollPeriodRules::validate($clean, $others)) {
            return $errors;
        }

        $id = DB::table('payroll_periods')->insertGetId($clean + [
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

    /**
     * Pindah status. $action: approve | reopen | pay | lock
     *
     * @return string[]
     */
    public function transition(int $periodId, string $action, ?int $actorId): array
    {
        if ($e = $this->disabledError()) {
            return [$e];
        }
        $map = ['approve' => PayrollPeriodRules::APPROVED, 'reopen' => PayrollPeriodRules::OPEN, 'pay' => PayrollPeriodRules::PAID, 'lock' => PayrollPeriodRules::LOCKED];
        if (!isset($map[$action])) {
            return ['Unknown action.'];
        }

        return DB::transaction(function () use ($periodId, $action, $map, $actorId) {
            $p = DB::table('payroll_periods')->where('id', $periodId)->lockForUpdate()->first();
            if (!$p) {
                return ['Payroll period not found.'];
            }
            $to = $map[$action];
            if (!PayrollPeriodRules::canTransition($p->status, $to)) {
                return ["A {$p->status} period cannot be changed to {$to}."];
            }
            if ($action === 'approve') {
                if (DB::table('payroll_slips')->where('period_id', $periodId)->count() === 0) {
                    return ['Calculate the payroll first — there are no payslips to approve.'];
                }
                if ($p->calculated_at === null) {
                    return ['Calculate the payroll first.'];
                }
            }

            $now = now();
            $set = ['status' => $to, 'updated_at' => $now];
            match ($action) {
                'approve' => $set += ['approved_at' => $now, 'approved_by' => $actorId],
                'reopen'  => $set += ['approved_at' => null, 'approved_by' => null],
                'pay'     => $set += ['paid_at' => $now, 'paid_by' => $actorId],
                'lock'    => $set += ['locked_at' => $now, 'locked_by' => $actorId],
            };
            DB::table('payroll_periods')->where('id', $periodId)->update($set);

            if ($action === 'approve') {
                DB::table('payroll_slips')->where('period_id', $periodId)->update(['status' => 'approved', 'updated_at' => $now]);
            } elseif ($action === 'reopen') {
                DB::table('payroll_slips')->where('period_id', $periodId)->update(['status' => 'draft', 'updated_at' => $now]);
            }

            return [];
        });
    }

    /** Hapus periode terbuka beserta slip & penyesuaiannya (cascade). @return string[] */
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
        DB::table('payroll_periods')->where('id', $periodId)->delete();

        return [];
    }

    /** @return string[] */
    public function addAdjustment(int $periodId, array $in, ?int $actorId): array
    {
        if ($e = $this->disabledError()) {
            return [$e];
        }
        $p = DB::table('payroll_periods')->where('id', $periodId)->first();
        if (!$p || !PayrollPeriodRules::isEditable($p->status)) {
            return ['Adjustments can only be added to an open payroll period.'];
        }

        $employeeId = (int) ($in['employee_id'] ?? 0);
        $kind = (string) ($in['kind'] ?? '');
        $name = trim((string) ($in['name'] ?? ''));
        $amount = Money::parse($in['amount'] ?? null);

        $errors = [];
        if (!DB::table('employee')->where('employee_id', $employeeId)->exists()) { $errors[] = 'Choose an employee.'; }
        if (!in_array($kind, ['earning', 'deduction'], true)) { $errors[] = 'Choose earning or deduction.'; }
        if ($name === '' || mb_strlen($name) > 150) { $errors[] = 'Enter a description (maximum 150 characters).'; }
        if ($amount === null || $amount <= 0 || $amount > 9_999_999_999_999) { $errors[] = 'Enter an amount greater than zero.'; }
        if ($errors) {
            return $errors;
        }

        DB::table('payroll_adjustments')->insert([
            'period_id' => $periodId, 'employee_id' => $employeeId, 'kind' => $kind, 'name' => $name,
            'amount' => round($amount, 2), 'taxable' => $kind === 'earning' && !empty($in['taxable']),
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
            return ['Adjustments can only be changed on an open payroll period.'];
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
