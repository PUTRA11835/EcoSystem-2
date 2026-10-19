<?php

namespace App\Services\Payroll;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The payroll's own audit trail (payroll_audit_logs): who did what to which period, when — every manual step, with enough detail
 * (amounts, switches before / after) to answer "why did this payslip change". Insert-only; nothing here edits or deletes a row.
 */
class PayrollAudit
{
    public const CREATED = 'period.created';
    public const CALCULATED = 'period.calculated';
    public const LOCKED = 'period.locked';
    public const DELETED = 'period.deleted';
    public const SLIPS_GENERATED = 'slips.generated';
    public const SLIPS_PUBLISHED = 'slips.published';
    public const EMPLOYEE_RECALCULATED = 'employee.recalculated';
    public const SWITCHES_CHANGED = 'employee.switches';
    public const CORRECTION_ADDED = 'correction.added';
    public const CORRECTION_REMOVED = 'correction.removed';
    public const BANK_EXPORTED = 'export.bank';

    public const LABELS = [
        self::CREATED => 'Period created', self::CALCULATED => 'Calculated', self::LOCKED => 'Locked', self::DELETED => 'Deleted',
        self::SLIPS_GENERATED => 'Payslips generated', self::SLIPS_PUBLISHED => 'Payslips published',
        self::EMPLOYEE_RECALCULATED => 'Employee recalculated', self::SWITCHES_CHANGED => 'BPJS / PPh 21 switches changed',
        self::CORRECTION_ADDED => 'Correction added', self::CORRECTION_REMOVED => 'Correction removed',
        self::BANK_EXPORTED => 'Bank transfer file downloaded',
    ];

    /** @param array<string,mixed> $details */
    public static function record(string $action, ?int $periodId, string $summary, ?int $actorId, array $details = [], ?int $employeeId = null): void
    {
        DB::table('payroll_audit_logs')->insert([
            'period_id' => $periodId, 'employee_id' => $employeeId, 'action' => $action,
            'summary' => mb_substr($summary, 0, 500), 'details' => $details ? json_encode($details) : null,
            'actor_id' => $actorId, 'created_at' => now(),
        ]);
    }

    /**
     * The trail of one period, newest first, each row with `actor_name` and decoded `details`.
     *
     * @return Collection<int,object>
     */
    public static function forPeriod(int $periodId, int $limit = 100): Collection
    {
        $rows = DB::table('payroll_audit_logs')->where('period_id', $periodId)->orderByDesc('id')->limit($limit)->get();

        $names = DB::table('employee_basic_data')->whereIn('employee_id', $rows->pluck('actor_id')->filter()->unique())
            ->get(['employee_id', 'first_name', 'last_name'])
            ->mapWithKeys(fn ($b) => [(int) $b->employee_id => trim($b->first_name . ' ' . $b->last_name)]);

        return $rows->map(function ($r) use ($names) {
            $r->actor_name = $r->actor_id ? ($names[(int) $r->actor_id] ?? 'Employee #' . $r->actor_id) : 'System';
            $r->details = $r->details ? (json_decode($r->details, true) ?: []) : [];

            return $r;
        });
    }
}
