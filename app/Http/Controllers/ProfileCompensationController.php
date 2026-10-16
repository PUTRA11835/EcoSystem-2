<?php

namespace App\Http\Controllers;

use App\Models\EmployeeSalaryComponent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * My Profile → Contract / Basic Payment recaps (read-only, SELF ONLY).
 *
 * The employee id always comes from the session, never from the request, so these endpoints can only ever
 * return the signed-in employee's own figures. They are deliberately NOT part of `employee.section.salary`
 * (HR's editing gate) and there is no `my-profile.section.salary` slug: seeing your own pay is not a section
 * permission, changing it stays with HR in Contracts / Payroll.
 */
class ProfileCompensationController extends Controller
{
    /** Latest published payslips shown in the Basic Payment tab; the full list lives on the Paystub page. */
    private const PAYSTUB_RECAP = 6;

    /** GET /api/profile/salary-summary — the components in force today, and their totals. */
    public function salarySummary(): JsonResponse
    {
        $employeeId = $this->employeeId();
        if (!$employeeId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $today = now()->toDateString();

        $rows = EmployeeSalaryComponent::where('employee_id', $employeeId)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $today)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $today))
            ->orderByRaw("case kind when 'base' then 0 when 'fixed' then 1 else 2 end")
            ->orderBy('effective_from')->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => $rows->map(fn (EmployeeSalaryComponent $row) => [
                'id'             => $row->id,
                'name'           => $row->name,
                'category'       => EmployeeSalaryComponent::KIND_LABELS[$row->kind] ?? $row->kind,
                'amount'         => (float) $row->amount,
                'effective_from' => $row->effective_from?->toDateString(),
            ])->values(),
            'summary' => EmployeeSalaryComponent::summaryOf($rows),
        ]);
    }

    /** GET /api/profile/paystub-summary — latest published payslips (no PDF links; those are on the Paystub page). */
    public function paystubSummary(): JsonResponse
    {
        $employeeId = $this->employeeId();
        if (!$employeeId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $slips = DB::table('payroll_slips as s')
            ->join('payroll_periods as p', 'p.id', '=', 's.period_id')
            ->where('s.employee_id', $employeeId)->whereNotNull('s.published_at')
            ->orderByDesc('p.period_end')->limit(self::PAYSTUB_RECAP)
            ->get(['s.id', 's.slip_no', 's.gross_earnings', 's.total_deductions', 's.take_home_pay', 's.published_at', 'p.name', 'p.period_start', 'p.period_end']);

        return response()->json([
            'success' => true,
            'data'    => $slips->map(fn ($s) => [
                'id'          => $s->id,
                'slip_no'     => str_pad((string) $s->slip_no, 5, '0', STR_PAD_LEFT),
                'period'      => $s->name,
                'period_start' => $s->period_start,
                'period_end'  => $s->period_end,
                'gross'       => (float) $s->gross_earnings,
                'deductions'  => (float) $s->total_deductions,
                'take_home'   => (float) $s->take_home_pay,
                'published_at' => $s->published_at,
            ])->values(),
        ]);
    }

    private function employeeId(): ?int
    {
        $user = session('user');

        return $user && ($user['type'] ?? null) === 'employee' ? (int) ($user['id'] ?? 0) ?: null : null;
    }
}
