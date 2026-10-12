<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollSetting;
use App\Services\Payroll\PayrollEngine;
use App\Services\Payroll\PayrollPeriodService;
use App\Support\Payroll\PtkpRules;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Finance → Payroll → Settings (kebijakan perhitungan + saklar aktivasi) dan Simulation (hitung tanpa menyimpan).
 *
 * Settings: `menu:finance.payroll.settings` (+ `menu.can:…,edit` untuk menyimpan kebijakan). Menyalakan/mematikan modul
 * butuh slug fungsi TERSENDIRI `finance.payroll.activate` dan tercatat (audit + siapa/kapan). Simulation tidak menulis
 * apa pun dan tidak bergantung pada saklar — gunanya menguji data sebelum payroll dinyalakan.
 */
class PayrollSettingsController extends Controller
{
    public function __construct(private readonly PayrollEngine $engine, private readonly PayrollPeriodService $periods)
    {
    }

    public function settings(): View
    {
        $me = Employee::find(session('user.id'));
        $row = PayrollSetting::current();

        return view('finance.payroll.settings', [
            'settings' => $this->engine->settings() + $row->only(['hr_signer_name', 'hr_signer_title', 'finance_signer_name', 'finance_signer_title', 'slip_language']),
            'canEdit' => (bool) $me?->hasMenuPermission('finance.payroll.settings', 'can_edit'),
            'enabled' => (bool) $row->module_enabled,
            'enabledAt' => $row->enabled_at,
            'enabledBy' => $row->enabled_by ? DB::table('employee_basic_data')->where('employee_id', $row->enabled_by)->selectRaw("TRIM(CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,''))) as n")->value('n') : null,
            'periodsCount' => DB::table('payroll_periods')->count(),
        ]);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $errors = [];
        $in = [
            'work_days_per_week' => (int) $request->input('work_days_per_week'),
            'overtime_work_days' => $request->input('overtime_work_days') === '' || $request->input('overtime_work_days') === null ? null : (int) $request->input('overtime_work_days'),
            'final_tax_month' => (int) $request->input('final_tax_month'),
            'late_grace_minutes' => (int) $request->input('late_grace_minutes'),
            'workday_base_minutes' => (int) $request->input('workday_base_minutes'),
            'proration_basis' => (string) $request->input('proration_basis'),
            'fixed_divisor' => (int) $request->input('fixed_divisor'),
            'attendance_effective_from' => trim((string) $request->input('attendance_effective_from')) === '' ? null : trim((string) $request->input('attendance_effective_from')),
            'slip_language' => (string) $request->input('slip_language', 'id'),
        ];
        foreach (['hr_signer_name', 'hr_signer_title', 'finance_signer_name', 'finance_signer_title'] as $k) {
            $v = trim((string) $request->input($k));
            if (mb_strlen($v) > 150) { $errors[] = 'Signer name and title are limited to 150 characters.'; }
            $in[$k] = $v === '' ? null : $v;
        }
        if (!in_array($in['slip_language'], ['id', 'en'], true)) { $errors[] = 'Choose Indonesian or English for the payslip.'; }
        if (!in_array($in['work_days_per_week'], [5, 6], true)) { $errors[] = 'Weekly work schedule must be 5 or 6 days.'; }
        if ($in['overtime_work_days'] !== null && !in_array($in['overtime_work_days'], [5, 6], true)) { $errors[] = 'Overtime workweek basis must follow payroll, or be 5 or 6 days.'; }
        if ($in['final_tax_month'] < 1 || $in['final_tax_month'] > 12) { $errors[] = 'The final tax month must be between 1 and 12.'; }
        if ($in['late_grace_minutes'] < 0 || $in['late_grace_minutes'] > 240) { $errors[] = 'Grace period must be between 0 and 240 minutes.'; }
        if ($in['workday_base_minutes'] < 60 || $in['workday_base_minutes'] > 720) { $errors[] = 'Workday base must be between 60 and 720 minutes.'; }
        if (!in_array($in['proration_basis'], ['fixed_divisor', 'calendar'], true)) { $errors[] = 'Choose a proration basis.'; }
        if (!in_array($in['fixed_divisor'], [21, 22, 25, 26], true)) { $errors[] = 'The fixed payroll divisor must be 21, 22, 25 or 26.'; }
        $d = $in['attendance_effective_from'] ? \DateTimeImmutable::createFromFormat('Y-m-d', $in['attendance_effective_from']) : null;
        if ($in['attendance_effective_from'] && (!$d || $d->format('Y-m-d') !== $in['attendance_effective_from'])) { $errors[] = 'Enter a valid date for “Attendance applies from”.'; }
        if ($errors) {
            return redirect()->route('finance.payroll.settings')->withErrors($errors)->withInput();
        }

        // Memakai model (bukan query langsung) supaya setiap perubahan kebijakan tercatat di audit log.
        $row = PayrollSetting::current();
        $row->fill($in + [
            'overtime_enabled' => $request->boolean('overtime_enabled'),
            'prorate_partial_period' => $request->boolean('prorate_partial_period'),
            'attendance_enabled' => $request->boolean('attendance_enabled'),
            'late_penalty_enabled' => $request->boolean('late_penalty_enabled'),
            'absence_deduction_enabled' => $request->boolean('absence_deduction_enabled'),
            'sick_paid' => $request->boolean('sick_paid'),
            'leave_paid' => $request->boolean('leave_paid'),
            'permit_paid' => $request->boolean('permit_paid'),
            'include_reimbursement' => $request->boolean('include_reimbursement'),
            'bpjs_enabled' => $request->boolean('bpjs_enabled'),
            'deduct_bpjs_tk_before_pph21' => $request->boolean('deduct_bpjs_tk_before_pph21'),
            'pph21_enabled' => $request->boolean('pph21_enabled'),
            'apply_no_npwp_surcharge' => $request->boolean('apply_no_npwp_surcharge'),
        ]);
        $row->save();

        return redirect()->route('finance.payroll.settings')->with('success', 'Payroll settings saved. They apply the next time a period is calculated; existing payslips do not change.');
    }

    /** Menyalakan / mematikan modul Payroll. Izin: slug fungsi `finance.payroll.activate` (di rute). */
    public function activation(Request $request): RedirectResponse
    {
        $on = $request->input('state') === 'on';
        $row = PayrollSetting::current();

        if ($on && !$request->boolean('confirm')) {
            return redirect()->route('finance.payroll.settings')->withErrors(['Tick the confirmation box before switching payroll on.']);
        }
        if (!$on && DB::table('payroll_periods')->where('status', 'open')->whereNotNull('calculated_at')->exists()) {
            return redirect()->route('finance.payroll.settings')->withErrors(['Payroll cannot be switched off while a calculated period is still open. Lock it first.']);
        }

        $actor = session('user.id') ? (int) session('user.id') : null;
        $row->forceFill(['module_enabled' => $on, 'enabled_at' => now(), 'enabled_by' => $actor])->save();
        Log::info('Payroll module ' . ($on ? 'switched ON' : 'switched OFF'), ['by' => $actor]);

        return redirect()->route('finance.payroll.settings')->with('success', $on ? 'Payroll is now switched on.' : 'Payroll is switched off. Existing periods are kept.');
    }

    public function simulation(Request $request): View
    {
        $employees = DB::table('employee as e')
            ->leftJoin('employee_basic_data as b', 'b.employee_id', '=', 'e.employee_id')
            ->leftJoin('employee_hr_profile as h', 'h.employee_id', '=', 'e.employee_id')
            ->where('e.is_active', 1)->orderBy('b.first_name')
            ->get(['e.employee_id', 'e.eci', 'b.first_name', 'b.last_name', 'h.payroll_activated']);

        $now = now();
        $in = $request->query() + [
            'employee_id' => '', 'month' => $now->month, 'year' => $now->year, 'base_salary' => '', 'fixed_allowance' => '', 'other_taxable' => '',
            'other_nontaxable' => '', 'manual_overtime' => '', 'overtime_hours' => '', 'overtime_day_type' => 'workday', 'overtime_basis' => '',
            'reimbursement' => '', 'routine_deduction' => '', 'other_deduction' => '', 'loan_installment' => '', 'divisor' => '',
            'days_present' => '', 'days_sick' => '', 'days_leave' => '', 'days_permit' => '', 'days_absent' => '', 'late_count' => '', 'late_minutes' => '',
            'ptkp_code' => 'TK/0',
        ];

        $result = null;
        $error = null;
        if ($request->boolean('run')) {
            try {
                $result = $this->engine->simulateManual($in + [
                    'has_tax_id' => $request->boolean('has_tax_id'), 'bpjs_health' => $request->boolean('bpjs_health'), 'bpjs_employment' => $request->boolean('bpjs_employment'),
                ]);
            } catch (\InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        } else {
            $in += ['has_tax_id' => 1, 'bpjs_health' => 1, 'bpjs_employment' => 1];
        }
        $in['has_tax_id'] = $request->boolean('run') ? $request->boolean('has_tax_id') : true;
        $in['bpjs_health'] = $request->boolean('run') ? $request->boolean('bpjs_health') : true;
        $in['bpjs_employment'] = $request->boolean('run') ? $request->boolean('bpjs_employment') : true;

        return view('finance.payroll.simulation', [
            'employees' => $employees, 'in' => $in, 'result' => $result, 'error' => $error,
            'ptkpCodes' => PtkpRules::CODES, 'settings' => $this->engine->settings(),
        ]);
    }

    /** Isian awal Simulation dari data sebenarnya karyawan (JSON; hanya membaca). */
    public function simulationSnapshot(Request $request): JsonResponse
    {
        $id = (int) $request->query('employee_id', 0);
        $month = (int) $request->query('month', 0);
        $year = (int) $request->query('year', 0);
        if ($id < 1 || $month < 1 || $month > 12 || $year < 2024 || $year > 2100) {
            return response()->json(['success' => false, 'message' => 'Choose an employee, month and year.'], 422);
        }
        $start = sprintf('%04d-%02d-01', $year, $month);

        try {
            $snap = $this->engine->snapshot($id, $start, date('Y-m-t', strtotime($start)));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return $snap
            ? response()->json(['success' => true, 'data' => $snap])
            : response()->json(['success' => false, 'message' => 'Employee not found or inactive.'], 404);
    }
}
