<?php

namespace App\Services\Payroll;

use App\Models\PayrollSetting;
use App\Services\HolidayService;
use App\Support\Payroll\Money;
use App\Support\Payroll\PayrollCalculator;
use App\Support\Payroll\PayrollCalendar;
use App\Support\Payroll\PayrollPeriodRules;
use App\Support\Payroll\SalaryComponentRules;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Mesin Payroll: mengumpulkan data dari master (komponen gaji, profil HR, lembur & reimbursement disetujui,
 * absensi, cuti, rekening, NPWP), memanggil PayrollCalculator (murni) per karyawan, lalu menyimpan slip — atau
 * hanya menghitung (simulasi).
 *
 * Aturan keselamatan:
 *  - Calculate/Recalculate hanya untuk periode `open`; slip lama periode itu diganti dalam satu transaksi.
 *  - Karyawan yang ikut: `employee.is_active`, tidak diblokir/dihapus di master, `payroll_activated` menyala.
 *  - Slip menyimpan SNAPSHOT identitas dan tarif (BPJS, PPh 21, kebijakan) — mengubah pengaturan sesudahnya
 *    tidak mengubah slip lama.
 *  - Mesin tidak menulis ke tabel modul lain (lembur, absensi, cuti, reimbursement): hanya membaca.
 *  - Potongan absensi: modul absensi TIDAK menulis status "alpa", jadi tidak-hadir DITURUNKAN di sini
 *    (hari kerja − hari ada presensi − hari cuti/izin disetujui). Karyawan tanpa SATU pun presensi pada
 *    periode itu tidak dipotong (dianggap tidak memakai absensi) dan diberi peringatan.
 */
class PayrollEngine
{
    public function __construct(
        private readonly Pph21RateRepository $pph21,
        private readonly BpjsSettingsService $bpjs,
        private readonly HolidayService $holidays,
    ) {
    }

    /** @return array<string,mixed> kebijakan (payroll_settings) dengan tipe yang benar */
    public function settings(): array
    {
        $r = PayrollSetting::current();

        return [
            'module_enabled'              => (bool) $r->module_enabled,
            'overtime_enabled'            => (bool) $r->overtime_enabled,
            'work_days_per_week'          => (int) $r->work_days_per_week,
            'prorate_partial_period'      => (bool) $r->prorate_partial_period,
            'final_tax_month'             => (int) $r->final_tax_month,
            'attendance_enabled'          => (bool) $r->attendance_enabled,
            'attendance_effective_from'   => $r->attendance_effective_from?->toDateString(),
            'late_penalty_enabled'        => (bool) $r->late_penalty_enabled,
            'late_grace_minutes'          => (int) $r->late_grace_minutes,
            'workday_base_minutes'        => (int) $r->workday_base_minutes,
            'absence_deduction_enabled'   => (bool) $r->absence_deduction_enabled,
            'sick_paid'                   => (bool) $r->sick_paid,
            'leave_paid'                  => (bool) $r->leave_paid,
            'permit_paid'                 => (bool) $r->permit_paid,
            'include_reimbursement'       => (bool) $r->include_reimbursement,
            'overtime_work_days'          => $r->overtime_work_days === null ? null : (int) $r->overtime_work_days,
            'proration_basis'             => (string) $r->proration_basis,
            'fixed_divisor'               => (int) $r->fixed_divisor,
            'bpjs_enabled'                => (bool) $r->bpjs_enabled,
            'deduct_bpjs_tk_before_pph21' => (bool) $r->deduct_bpjs_tk_before_pph21,
            'pph21_enabled'               => (bool) $r->pph21_enabled,
            'apply_no_npwp_surcharge'     => (bool) $r->apply_no_npwp_surcharge,
        ];
    }

    /**
     * Apakah absensi memengaruhi payroll pada periode yang dimulai $start? Saklar induk menyala DAN (tanpa tanggal mulai
     * ATAU periode dimulai pada/sesudah tanggal itu). Dua toggle rinci (alpa, terlambat) baru berlaku bila ini true.
     *
     * @param array<string,mixed> $settings
     */
    public static function attendanceAppliesTo(array $settings, string $start): bool
    {
        if (empty($settings['attendance_enabled'])) {
            return false;
        }
        $from = $settings['attendance_effective_from'] ?? null;

        return $from === null || $from === '' || $start >= $from;
    }

    /**
     * Hitung ulang seluruh slip sebuah periode `open`.
     *
     * @return array{employees:int,skipped:int,totals:array<string,float>,warnings:string[]}
     */
    public function calculatePeriod(int $periodId, ?int $actorId): array
    {
        $period = DB::table('payroll_periods')->where('id', $periodId)->first();
        if (!$period) {
            throw new InvalidArgumentException('Payroll period not found.');
        }
        if (!PayrollPeriodRules::isEditable($period->status)) {
            throw new InvalidArgumentException('Only an open payroll period can be calculated.');
        }

        $start = (string) $period->period_start;
        $end   = (string) $period->period_end;
        $ctx   = $this->context($start, $end);

        $employees = $this->eligibleEmployees();
        $ids       = array_column($employees, 'employee_id');
        $data      = $this->loadEmployeeData($ids, $start, $end, $periodId, $ctx);

        $slips = [];
        $warnings = [];
        $skipped = 0;
        if (!$employees) {
            $warnings[] = 'No employee is included in payroll yet. In Master → Employee → Compensation, set the PTKP status, add a Base Salary, and tick “Include this employee when payroll is calculated”.';
        }
        foreach ($employees as $e) {
            $id = (int) $e['employee_id'];
            $out = $this->computeEmployee($e, $data, $ctx, $start, $end, $id);
            if ($out === null) {
                $skipped++;
                $warnings[] = $e['name'] . ': skipped — no Base Salary covers this period and there are no adjustments.';
                continue;
            }
            foreach (array_merge($out['result']['warnings'], $out['attendance_warnings'], $this->dataWarnings($e, $data)) as $w) {
                $warnings[] = $e['name'] . ': ' . $w;
            }
            $slips[] = $out;
        }

        DB::transaction(function () use ($periodId, $slips, $actorId, $warnings, $ctx) {
            DB::table('payroll_slips')->where('period_id', $periodId)->delete();      // item ikut terhapus (cascade)
            $now = now();
            $tot = ['gross' => 0, 'ded' => 0, 'thp' => 0, 'bpjs_ee' => 0, 'bpjs_er' => 0, 'pph' => 0];

            foreach ($slips as $s) {
                $r = $s['result']; $sum = $r['summary']; $e = $s['employee'];
                $slipId = DB::table('payroll_slips')->insertGetId([
                    'period_id' => $periodId, 'employee_id' => $e['employee_id'],
                    'employee_name' => $e['name'], 'employee_eci' => $e['eci'], 'position' => $e['position'], 'department' => $e['department'],
                    'ptkp_code' => $e['ptkp_code'], 'bank_name' => $s['bank']['bank_name'] ?? null, 'bank_account' => $s['bank']['account_number'] ?? null,
                    'gross_earnings' => $sum['gross_earnings'], 'overtime' => $sum['overtime'], 'component_deductions' => $sum['component_deductions'],
                    'bpjs_employee' => $sum['bpjs_employee'], 'bpjs_employer' => $sum['bpjs_employer'], 'pph21' => $sum['pph21'],
                    'total_deductions' => $sum['total_deductions'], 'take_home_pay' => $sum['take_home_pay'],
                    'tax_gross' => $r['pph21']['tax_gross'], 'employee_pension' => $r['bpjs']['totals']['employee_pension'],
                    'pph21_refund' => $r['pph21']['refund'], 'status' => 'draft',
                    'breakdown' => json_encode([
                        'bpjs' => $r['bpjs'], 'pph21' => $r['pph21'], 'summary' => $sum, 'warnings' => $r['warnings'],
                        'attendance' => $s['attendance'], 'rates' => $ctx['snapshot'],
                    ]),
                    'created_at' => $now, 'updated_at' => $now,
                ]);

                $sort = 0;
                $rows = [];
                foreach ($r['items'] as $i) {
                    $rows[] = [
                        'slip_id' => $slipId, 'type' => $i['type'], 'code' => $i['code'], 'name' => mb_substr($i['name'], 0, 200),
                        'amount' => $i['amount'], 'taxable' => (bool) $i['taxable'],
                        'source_type' => $i['source_type'] ?? null, 'source_id' => $i['source_id'] ?? null, 'sort' => $sort++,
                    ];
                }
                if ($rows) {
                    DB::table('payroll_slip_items')->insert($rows);
                }

                $tot['gross'] += $sum['gross_earnings']; $tot['ded'] += $sum['total_deductions']; $tot['thp'] += $sum['take_home_pay'];
                $tot['bpjs_ee'] += $sum['bpjs_employee']; $tot['bpjs_er'] += $sum['bpjs_employer']; $tot['pph'] += $sum['pph21'];
            }

            DB::table('payroll_periods')->where('id', $periodId)->update([
                'employee_count' => count($slips),
                'total_gross' => round($tot['gross'], 2), 'total_deductions' => round($tot['ded'], 2), 'total_take_home' => round($tot['thp'], 2),
                'total_bpjs_employee' => round($tot['bpjs_ee'], 2), 'total_bpjs_employer' => round($tot['bpjs_er'], 2), 'total_pph21' => round($tot['pph'], 2),
                'warnings' => json_encode(array_values($warnings)),
                'calculated_at' => $now, 'calculated_by' => $actorId, 'updated_at' => $now,
            ]);
        });

        return [
            'employees' => count($slips), 'skipped' => $skipped, 'warnings' => $warnings,
            'totals' => ['gross' => round(array_sum(array_map(fn ($s) => $s['result']['summary']['gross_earnings'], $slips)), 2),
                         'take_home' => round(array_sum(array_map(fn ($s) => $s['result']['summary']['take_home_pay'], $slips)), 2)],
        ];
    }

    /**
     * Isian awal Simulation untuk satu karyawan pada satu bulan: gaji pokok & tunjangan tetap penuh, PTKP, penanda BPJS,
     * ada-tidaknya NPWP, dan ringkasan kehadiran sebenarnya (hari kerja, hadir, sakit, cuti, izin, alpa, terlambat).
     *
     * @return array<string,mixed>|null null bila karyawan tidak ditemukan / tidak aktif
     */
    public function snapshot(int $employeeId, string $start, string $end): ?array
    {
        $e = collect($this->employees([$employeeId], false))->first();
        if (!$e) {
            return null;
        }
        $ctx  = $this->context($start, $end);
        $data = $this->loadEmployeeData([$employeeId], $start, $end, null, $ctx);
        $rows = $data['components'][$employeeId] ?? [];
        $date = SalaryComponentRules::effectiveOn($rows, $end) ? $end : $start;
        $base = $fixed = 0.0;
        foreach (SalaryComponentRules::effectiveOn($rows, $date) as $r) {
            if ($r['category'] === SalaryComponentRules::BASE) { $base += $r['amount']; }
            if ($r['category'] === SalaryComponentRules::FIXED_ALLOWANCE) { $fixed += $r['amount']; }
        }
        $att = $this->attendanceSummary($employeeId, $data, $ctx, $start, $end, $rows);

        return [
            'name' => $e['name'], 'eci' => $e['eci'], 'ptkp_code' => $e['ptkp_code'],
            'bpjs_health_active' => (bool) $e['bpjs_health_active'], 'bpjs_employment_active' => (bool) $e['bpjs_employment_active'],
            'has_tax_id' => isset($data['npwp'][$employeeId]), 'base' => $base, 'fixed' => $fixed,
            'workdays' => count($ctx['workdays']), 'attendance' => $att,
        ];
    }

    /**
     * Simulasi dari isian manual (tanpa menyimpan). Karyawan opsional: bila dipilih dan periodenya masa pajak terakhir,
     * pajak setahun memakai slip sebelumnya milik karyawan itu; selain itu isian manual yang berlaku.
     *
     * @param array<string,mixed> $f
     * @return array<string,mixed>
     * @throws InvalidArgumentException
     */
    public function simulateManual(array $f): array
    {
        $month = (int) ($f['month'] ?? 0);
        $year  = (int) ($f['year'] ?? 0);
        if ($month < 1 || $month > 12 || $year < 2024 || $year > 2100) {
            throw new InvalidArgumentException('Choose a valid payroll month and tax year.');
        }
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = date('Y-m-t', strtotime($start));

        $ctx = $this->context($start, $end);
        $set = $ctx['settings'];

        $n = fn (string $k) => max(0.0, (float) (Money::parse($f[$k] ?? 0) ?? 0));
        $base = $n('base_salary');
        $fixed = $n('fixed_allowance');

        $comp = fn (string $name, string $cat, float $amt) => ['id' => null, 'name' => $name, 'category' => $cat, 'amount' => $amt,
            'effective_from' => $start, 'effective_to' => null, 'is_active' => true, 'taxable' => true, 'bpjs_base' => true];
        $components = [];
        if ($base > 0) { $components[] = $comp('Base Salary', 'base', $base); }
        if ($fixed > 0) { $components[] = $comp('Fixed allowance', 'fixed_allowance', $fixed); }

        $adj = [];
        $add = function (string $kind, string $name, float $amt, bool $taxable) use (&$adj) {
            if ($amt > 0) { $adj[] = ['id' => null, 'kind' => $kind, 'name' => $name, 'amount' => $amt, 'taxable' => $taxable]; }
        };
        $add('earning', 'Other taxable income', $n('other_taxable'), true);
        $add('earning', 'Non-taxable income', $n('other_nontaxable'), false);
        $add('earning', 'Overtime (manual)', $n('manual_overtime'), true);
        $add('earning', 'Reimbursement', $n('reimbursement'), false);
        $add('deduction', 'Routine deduction', $n('routine_deduction'), false);
        $add('deduction', 'Other deduction', $n('other_deduction'), false);
        $add('deduction', 'Loan installment', $n('loan_installment'), false);

        $overtime = [];
        $otHours = (float) (Money::parse($f['overtime_hours'] ?? 0) ?? 0);
        if ($otHours > 0 && $n('manual_overtime') <= 0 && !empty($set['overtime_enabled'])) {
            $day = in_array($f['overtime_day_type'] ?? '', ['workday', 'weekend', 'public_holiday'], true) ? $f['overtime_day_type'] : 'workday';
            $overtime[] = ['id' => null, 'date' => $start, 'duration_minutes' => (int) round($otHours * 60), 'day_type' => $day];
        }
        $sim = $set;
        $basisWeek = (int) ($f['overtime_basis'] ?? 0);
        if (in_array($basisWeek, [5, 6], true)) { $sim['overtime_work_days'] = $basisWeek; }
        $divisor = (int) ($f['divisor'] ?? 0);
        if ($divisor > 0) { $sim['proration_basis'] = 'fixed_divisor'; $sim['fixed_divisor'] = min(31, $divisor); }

        // Kehadiran manual → hari yang dipotong & menit terlambat yang dikenai denda
        $absent = max(0.0, (float) ($f['days_absent'] ?? 0));
        $deductible = $absent
            + (empty($set['sick_paid']) ? max(0.0, (float) ($f['days_sick'] ?? 0)) : 0)
            + (empty($set['leave_paid']) ? max(0.0, (float) ($f['days_leave'] ?? 0)) : 0)
            + (empty($set['permit_paid']) ? max(0.0, (float) ($f['days_permit'] ?? 0)) : 0);
        $lateCount = max(0, (int) ($f['late_count'] ?? 0));
        $lateMinutes = max(0, (int) ($f['late_minutes'] ?? 0));
        $chargeable = max(0, $lateMinutes - $lateCount * (int) $set['late_grace_minutes']);

        $employeeId = (int) ($f['employee_id'] ?? 0);
        $ytd = ['gross' => 0, 'employee_pension' => 0, 'pph21_withheld' => 0, 'months' => 0];
        $dependents = 0;
        if ($employeeId > 0) {
            $emp = collect($this->employees([$employeeId], false))->first();
            $dependents = (int) ($emp['bpjs_dependents'] ?? 0);
            $ytd = $this->loadEmployeeData([$employeeId], $start, $end, null, $ctx)['ytd'][$employeeId] ?? $ytd;
        }

        $ptkp = (string) ($f['ptkp_code'] ?? '');
        $result = PayrollCalculator::calculate([
            'start' => $start, 'end' => $end, 'components' => $components, 'overtime' => $overtime, 'adjustments' => $adj,
            'reimbursements' => [], 'workdays' => $ctx['workdays'],
            // Saklar induk absensi mati / belum berlaku pada bulan ini → isian kehadiran diabaikan.
            'attendance' => self::attendanceAppliesTo($set, $start) ? ['deductible_days' => $deductible, 'late_minutes_chargeable' => $chargeable] : null,
            'employee' => ['ptkp_code' => $ptkp === '' ? null : $ptkp, 'bpjs_health_active' => !empty($f['bpjs_health']), 'bpjs_employment_active' => !empty($f['bpjs_employment']),
                'bpjs_dependents' => $dependents, 'has_tax_id' => !empty($f['has_tax_id'])],
            'settings' => $sim, 'bpjs' => $ctx['bpjs'], 'pph21' => $ctx['pph21'], 'final_tax_period' => $ctx['final'], 'ytd' => $ytd,
        ]);

        return ['result' => $result, 'rates' => $ctx['snapshot'], 'settings' => $sim, 'attendance_applies' => self::attendanceAppliesTo($set, $start), 'period' => ['start' => $start, 'end' => $end],
            'workdays' => count($ctx['workdays']), 'hours' => $otHours];
    }

    // ── internal ─────────────────────────────────────────────────────────────

    /** Pengaturan yang berlaku untuk periode: BPJS, PPh 21 tahun pajak, kebijakan payroll, kalender kerja. */
    private function context(string $start, string $end): array
    {
        $bpjs = $this->bpjs->effectiveOn($end);             // melempar bila belum ada versi berlaku
        $year = PayrollPeriodRules::taxYear($end);
        $settings = $this->settings();

        try {
            $pph = $this->pph21->bundle($year);
        } catch (\RuntimeException) {
            $pph = ['year' => $year, 'ptkp' => [], 'ter' => [], 'brackets' => [], 'settings' => [
                'occupational_cost_rate' => 5, 'occupational_cost_monthly_max' => 500000, 'include_employer_premiums' => true,
            ]];
        }

        $holidays = $this->holidays->getHolidayDates((int) substr($start, 0, 4), (int) substr($end, 0, 4));

        return [
            'bpjs' => $bpjs, 'pph21' => $pph, 'settings' => $settings,
            'workdays' => PayrollCalendar::workdays($start, $end, $settings['work_days_per_week'], $holidays),
            'final' => PayrollPeriodRules::isFinalTaxPeriod($end, $settings['final_tax_month']),
            'snapshot' => ['bpjs_effective_date' => $bpjs['effective_date'], 'pph21_year' => $year, 'settings' => $settings,
                           'pph21_settings' => $pph['settings'] ?? null],
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private function eligibleEmployees(): array
    {
        $ids = DB::table('employee_hr_profile')->where('payroll_activated', 1)->pluck('employee_id')->all();

        return $this->employees($ids, true);
    }

    /**
     * @param int[] $ids
     * @return array<int,array<string,mixed>>
     */
    private function employees(array $ids, bool $onlyActivated): array
    {
        if (!$ids) {
            return [];
        }
        $rows = DB::table('employee as e')
            ->leftJoin('employee_basic_data as b', 'b.employee_id', '=', 'e.employee_id')
            ->leftJoin('employee_hr_profile as h', 'h.employee_id', '=', 'e.employee_id')
            ->whereIn('e.employee_id', $ids)
            ->where('e.is_active', 1)
            ->where(fn ($q) => $q->whereNull('b.block')->orWhere('b.block', 0))
            ->where(fn ($q) => $q->whereNull('b.deletion_flag')->orWhere('b.deletion_flag', 0))
            ->when($onlyActivated, fn ($q) => $q->where('h.payroll_activated', 1))
            ->orderBy('b.first_name')->orderBy('e.employee_id')
            ->get(['e.employee_id', 'e.eci', 'b.first_name', 'b.last_name', 'b.position', 'b.department',
                   'h.ptkp_code', 'h.bpjs_health_active', 'h.bpjs_employment_active', 'h.bpjs_dependents_count', 'h.payroll_activated']);

        return $rows->map(fn ($r) => [
            'employee_id' => (int) $r->employee_id, 'eci' => $r->eci,
            'name' => trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')) ?: ('Employee #' . $r->employee_id),
            'position' => $r->position, 'department' => $r->department, 'ptkp_code' => $r->ptkp_code,
            'bpjs_health_active' => $r->bpjs_health_active === null ? null : (bool) $r->bpjs_health_active,
            'bpjs_employment_active' => $r->bpjs_employment_active === null ? null : (bool) $r->bpjs_employment_active,
            'bpjs_dependents' => (int) ($r->bpjs_dependents_count ?? 0), 'payroll_activated' => (bool) $r->payroll_activated,
        ])->all();
    }

    /** @param int[] $ids @return array<string,mixed> data per karyawan, diindeks employee_id */
    private function loadEmployeeData(array $ids, string $start, string $end, ?int $periodId, array $ctx): array
    {
        $components = DB::table('employee_salary_components')->whereIn('employee_id', $ids)
            ->where('effective_from', '<=', $end)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $start))
            ->get()->groupBy('employee_id')
            ->map(fn ($g) => $g->map(fn ($r) => [
                'id' => (int) $r->id, 'name' => $r->name, 'category' => $r->category, 'amount' => (float) $r->amount,
                'effective_from' => (string) $r->effective_from, 'effective_to' => $r->effective_to ? (string) $r->effective_to : null,
                'is_active' => (bool) $r->is_active, 'taxable' => (bool) $r->taxable, 'bpjs_base' => (bool) $r->bpjs_base,
            ])->all())->all();

        $overtime = DB::table('overtime_requests')->whereIn('employee_id', $ids)->where('status', 'approved')
            ->whereBetween('overtime_date', [$start, $end])->orderBy('overtime_date')->get()
            ->groupBy('employee_id')->map(fn ($g) => $g->map(fn ($r) => [
                'id' => (int) $r->id, 'date' => (string) $r->overtime_date, 'duration_minutes' => (int) $r->duration_minutes, 'day_type' => $r->day_type,
            ])->all())->all();

        $adjustments = $periodId === null ? [] : DB::table('payroll_adjustments')->where('period_id', $periodId)->whereIn('employee_id', $ids)->orderBy('id')->get()
            ->groupBy('employee_id')->map(fn ($g) => $g->map(fn ($r) => [
                'id' => (int) $r->id, 'kind' => $r->kind, 'name' => $r->name, 'amount' => (float) $r->amount, 'taxable' => (bool) $r->taxable,
            ])->all())->all();

        $banks = DB::table('employee_bank')->whereIn('employee_id', $ids)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $end))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $end))
            ->orderByDesc('valid_from')->get()->groupBy('employee_id')->map(fn ($g) => (array) $g->first())->all();

        $npwp = DB::table('employee_identification')->whereIn('employee_id', $ids)->where('identification_type', 'NPWP')
            ->pluck('employee_id')->flip()->all();

        // Absensi & cuti: dibaca hanya bila kebijakan memerlukannya (atau untuk isian Simulation).
        $attendance = DB::table('attendance_records')->whereIn('employee_id', $ids)->whereBetween('attendance_date', [$start, $end])
            ->get(['employee_id', 'attendance_date', 'late_minutes'])->groupBy('employee_id')
            ->map(fn ($g) => $g->mapWithKeys(fn ($r) => [substr((string) $r->attendance_date, 0, 10) => (int) $r->late_minutes])->all())->all();

        $leaves = DB::table('leave_permit_applications as a')->join('leave_permit_types as t', 't.id', '=', 'a.leave_permit_type_id')
            ->whereIn('a.employee_id', $ids)->where('a.status', 'approved')
            ->where('a.start_date', '<=', $end)->where('a.end_date', '>=', $start)
            ->get(['a.employee_id', 'a.start_date', 'a.end_date', 'a.total_days', 't.code', 't.category', 't.is_paid'])
            ->groupBy('employee_id')->map(fn ($g) => $g->map(fn ($r) => [
                'start' => substr((string) $r->start_date, 0, 10), 'end' => substr((string) $r->end_date, 0, 10), 'total' => (float) $r->total_days,
                'code' => $r->code, 'category' => $r->category, 'is_paid' => (bool) $r->is_paid,
            ])->all())->all();

        // Reimbursement disetujui pada periode ini (tanggal persetujuan), IDR, dan belum dibayar lewat periode payroll lain.
        $reimbursements = DB::table('reimbursement_requests as r')
            ->whereIn('r.employee_id', $ids)->whereNull('r.deleted_at')->where('r.status', 'approved')
            ->whereBetween('r.completed_at', [$start . ' 00:00:00', $end . ' 23:59:59'])
            ->where(fn ($q) => $q->whereNull('r.currency')->orWhere('r.currency', 'IDR'))
            ->whereNotIn('r.id', function ($q) use ($periodId) {
                $q->select('i.source_id')->from('payroll_slip_items as i')->join('payroll_slips as s', 's.id', '=', 'i.slip_id')
                    ->where('i.source_type', 'reimbursement_request')->whereNotNull('i.source_id')
                    ->when($periodId, fn ($qq) => $qq->where('s.period_id', '!=', $periodId));
            })
            ->get(['r.id', 'r.employee_id', 'r.title', 'r.total_amount'])->groupBy('employee_id')
            ->map(fn ($g) => $g->map(fn ($r) => ['id' => (int) $r->id, 'title' => (string) $r->title, 'amount' => (float) $r->total_amount])->all())->all();

        // Penghasilan & pajak masa sebelumnya pada tahun pajak yang sama (untuk true-up masa terakhir).
        $year = PayrollPeriodRules::taxYear($end);
        $ytd = DB::table('payroll_slips as s')->join('payroll_periods as p', 'p.id', '=', 's.period_id')
            ->whereIn('s.employee_id', $ids)
            ->whereYear('p.period_end', $year)->where('p.period_end', '<', $start)
            ->when($periodId, fn ($q) => $q->where('s.period_id', '!=', $periodId))
            ->groupBy('s.employee_id')
            ->selectRaw('s.employee_id, SUM(s.tax_gross) as gross, SUM(s.employee_pension) as pension, SUM(s.pph21) as withheld, COUNT(DISTINCT MONTH(p.period_end)) as months')
            ->get()->keyBy('employee_id')
            ->map(fn ($r) => ['gross' => (float) $r->gross, 'employee_pension' => (float) $r->pension, 'pph21_withheld' => (float) $r->withheld, 'months' => (int) $r->months])->all();

        return compact('components', 'overtime', 'adjustments', 'banks', 'npwp', 'ytd', 'attendance', 'leaves', 'reimbursements');
    }

    /**
     * Ringkasan kehadiran pada rentang yang dicakup gaji pokok karyawan.
     *
     * @param array<int,array<string,mixed>> $components
     * @return array<string,mixed> available=false bila karyawan tak punya presensi sama sekali di rentang itu
     */
    private function attendanceSummary(int $id, array $data, array $ctx, string $start, string $end, array $components): array
    {
        $set = $ctx['settings'];
        $from = $end; $to = $start;
        foreach ($components as $c) {
            if ($c['category'] === SalaryComponentRules::BASE && $c['is_active'] && $c['effective_from'] <= $end && (empty($c['effective_to']) || $c['effective_to'] >= $start)) {
                $from = min($from, max($start, $c['effective_from']));
                $to   = max($to, empty($c['effective_to']) ? $end : min($end, $c['effective_to']));
            }
        }
        if ($from > $to) {
            return ['available' => false, 'covered_workdays' => 0, 'present' => 0, 'late_days' => 0, 'late_minutes' => 0, 'late_minutes_chargeable' => 0,
                'sick' => 0.0, 'leave' => 0.0, 'permit' => 0.0, 'absent' => 0.0, 'unpaid_leave' => 0.0, 'deductible_days' => 0.0];
        }

        $work = array_values(array_filter($ctx['workdays'], fn ($d) => $d >= $from && $d <= $to));
        // Hanya presensi pada HARI KERJA yang dihitung (satu presensi nyasar di akhir pekan/libur tidak membuat karyawan
        // dianggap memakai absensi, dan tidak menghasilkan denda terlambat).
        $rec  = array_filter($data['attendance'][$id] ?? [], fn ($v, $d) => in_array($d, $work, true), ARRAY_FILTER_USE_BOTH);
        $presentWork = count($rec);

        $lateDays = $lateMin = $chargeable = 0;
        foreach ($rec as $late) {
            if ($late > 0) {
                $lateDays++;
                $lateMin += $late;
                $chargeable += max(0, $late - (int) $set['late_grace_minutes']);
            }
        }

        $sick = $leave = $permit = $unpaid = 0.0;
        foreach ($data['leaves'][$id] ?? [] as $l) {
            $a = max($l['start'], $from);
            $b = min($l['end'], $to);
            if ($a > $b) {
                continue;
            }
            $days = $l['total'] > 0 && $l['total'] < 1 ? ($l['start'] >= $from && $l['start'] <= $to ? $l['total'] : 0.0)
                : (float) PayrollCalendar::countWithin($work, $a, $b);
            $cat = $l['code'] === 'CSK' ? 'sick' : ($l['category'] === 'permit' ? 'permit' : 'leave');
            match ($cat) { 'sick' => $sick += $days, 'permit' => $permit += $days, default => $leave += $days };
            $paid = $l['is_paid'] && ($cat !== 'sick' || $set['sick_paid']) && ($cat !== 'leave' || $set['leave_paid']) && ($cat !== 'permit' || $set['permit_paid']);
            if (!$paid) {
                $unpaid += $days;
            }
        }

        $absent = max(0.0, count($work) - $presentWork - ($sick + $leave + $permit));

        return [
            'available' => $rec !== [], 'covered_workdays' => count($work), 'present' => $presentWork,
            'late_days' => $lateDays, 'late_minutes' => $lateMin, 'late_minutes_chargeable' => $chargeable,
            'sick' => $sick, 'leave' => $leave, 'permit' => $permit, 'absent' => $absent, 'unpaid_leave' => $unpaid,
            'deductible_days' => round($absent + $unpaid, 2),
        ];
    }

    /**
     * @param array<string,mixed> $e
     * @return array{employee:array<string,mixed>,bank:array<string,mixed>|null,result:array<string,mixed>,attendance:array<string,mixed>|null,attendance_warnings:string[]}|null null = dilewati
     */
    private function computeEmployee(array $e, array $data, array $ctx, string $start, string $end, int $id): ?array
    {
        $set         = $ctx['settings'];
        $components  = $data['components'][$id] ?? [];
        $adjustments = $data['adjustments'][$id] ?? [];

        $att = null;
        $attWarnings = [];
        $needAttendance = self::attendanceAppliesTo($set, $start) && ($set['absence_deduction_enabled'] || $set['late_penalty_enabled']);
        if ($needAttendance) {
            $att = $this->attendanceSummary($id, $data, $ctx, $start, $end, $components);
            if (!$att['available']) {
                $attWarnings[] = 'No attendance records in this period — absence deduction and late penalty were skipped.';
            }
        }

        $result = PayrollCalculator::calculate([
            'start' => $start, 'end' => $end, 'components' => $components,
            'overtime' => $data['overtime'][$id] ?? [], 'adjustments' => $adjustments, 'reimbursements' => $data['reimbursements'][$id] ?? [],
            'workdays' => $ctx['workdays'],
            'attendance' => ($att && $att['available']) ? ['deductible_days' => $att['deductible_days'], 'late_minutes_chargeable' => $att['late_minutes_chargeable']] : null,
            'employee' => [
                'ptkp_code' => $e['ptkp_code'], 'bpjs_health_active' => $e['bpjs_health_active'],
                'bpjs_employment_active' => $e['bpjs_employment_active'], 'bpjs_dependents' => $e['bpjs_dependents'],
                'has_tax_id' => isset($data['npwp'][$id]),
            ],
            'settings' => $set, 'bpjs' => $ctx['bpjs'], 'pph21' => $ctx['pph21'],
            'final_tax_period' => $ctx['final'],
            'ytd' => $data['ytd'][$id] ?? ['gross' => 0, 'employee_pension' => 0, 'pph21_withheld' => 0, 'months' => 0],
        ]);

        $hasBase = collect($components)->contains(fn ($c) => $c['category'] === 'base' && $c['is_active']
            && $c['effective_from'] <= $end && (empty($c['effective_to']) || $c['effective_to'] >= $start));
        if (!$hasBase && !$adjustments) {
            return null;
        }

        return ['employee' => $e, 'bank' => $data['banks'][$id] ?? null, 'result' => $result, 'attendance' => $att, 'attendance_warnings' => $attWarnings];
    }

    /** Peringatan kelengkapan data (tidak menghalangi perhitungan). @return string[] */
    private function dataWarnings(array $e, array $data): array
    {
        $w = [];
        if (empty($data['banks'][$e['employee_id']]['account_number'] ?? null)) {
            $w[] = 'No bank account on file.';
        }
        if (!isset($data['npwp'][$e['employee_id']])) {
            $w[] = 'No NPWP on file.';
        }

        return $w;
    }
}
