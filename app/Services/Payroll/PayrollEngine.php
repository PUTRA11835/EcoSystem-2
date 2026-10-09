<?php

namespace App\Services\Payroll;

use App\Models\EmployeeSalaryComponent;
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
 * absensi, cuti, rekening, NPWP, koreksi), memanggil PayrollCalculator (murni) per karyawan, lalu menyimpan slip —
 * untuk seluruh periode, SATU karyawan ("Hitung Ulang"), atau hanya menghitung (simulasi).
 *
 * Aturan keselamatan:
 *  - Hitung/hitung ulang hanya untuk periode `open`; slip yang diganti memakai transaksi, dan NOMOR SLIP serta jejak
 *    Generate Slip karyawan itu dipertahankan (kode verifikasi dihitung ulang dari angka baru).
 *  - Karyawan yang ikut: `employee.is_active`, tidak diblokir/dihapus di master, `payroll_activated` menyala.
 *  - Slip menyimpan SNAPSHOT identitas dan tarif (BPJS, PPh 21, kebijakan) — mengubah pengaturan sesudahnya
 *    tidak mengubah slip lama.
 *  - Mesin tidak menulis ke tabel modul lain (lembur, absensi, cuti, reimbursement): hanya membaca.
 *  - Potongan absensi: modul absensi TIDAK menulis status "alpa", jadi tidak-hadir DITURUNKAN di sini
 *    (hari kerja − hari ada presensi − hari cuti/izin disetujui). Karyawan tanpa SATU pun presensi pada
 *    periode itu tidak dipotong (dianggap tidak memakai absensi) dan diberi peringatan.
 *  - Periode absensi & cut-off: data absensi/cuti/lembur dibaca dari [attendance_start, attendance_end]; hari kerja
 *    SESUDAH cut-off dianggap penuh (tidak dipotong, lembur belum dibayar) dan dikoreksi di payroll berikutnya.
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

    /** Kode verifikasi slip: HMAC angka-angka utamanya dengan kunci aplikasi (tidak dapat ditebak, berubah bila angka berubah). */
    public static function verificationCode(int $periodId, int $employeeId, int $slipNo, float $takeHome, float $gross): string
    {
        return hash_hmac('sha256', implode('|', [$periodId, $employeeId, $slipNo, number_format($takeHome, 2, '.', ''), number_format($gross, 2, '.', '')]), (string) config('app.key'));
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Hitung
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Hitung ulang seluruh slip sebuah periode `open`.
     *
     * @return array{employees:int,skipped:int,totals:array<string,float>,warnings:string[]}
     */
    public function calculatePeriod(int $periodId, ?int $actorId): array
    {
        $period = $this->openPeriod($periodId);
        $p      = (array) $period;
        $start  = (string) $period->period_start;
        $end    = (string) $period->period_end;
        $ctx    = $this->context($start, $end, $p);

        $employees = $this->eligibleEmployees();
        $ids       = array_column($employees, 'employee_id');
        $data      = $this->loadEmployeeData($ids, $start, $end, $periodId, $ctx);

        $slips = [];
        $warnings = [];
        $skipped = 0;
        if (!$employees) {
            $warnings[] = 'No employee is included in payroll yet. In Master → Employee → Compensation, set the PTKP status, add a Base Salary (Contract tab → Salary Components), and tick “Include this employee when payroll is calculated”.';
        }
        foreach ($employees as $e) {
            $out = $this->computeEmployee($e, $data, $ctx, $start, $end, (int) $e['employee_id']);
            if ($out === null) {
                $skipped++;
                $warnings[] = $e['name'] . ': skipped — no Base Salary covers this period and there are no corrections.';
                continue;
            }
            foreach (array_merge($out['result']['warnings'], $out['attendance_warnings'], $this->dataWarnings($e, $data)) as $w) {
                $warnings[] = $e['name'] . ': ' . $w;
            }
            $slips[] = $out;
        }

        DB::transaction(function () use ($periodId, $slips, $actorId, $warnings, $ctx, $period) {
            $previous = DB::table('payroll_slips')->where('period_id', $periodId)->get()->keyBy('employee_id');
            DB::table('payroll_slips')->where('period_id', $periodId)->delete();      // item ikut terhapus (cascade)
            foreach ($slips as $s) {
                $this->persistSlip($periodId, $s, $previous->get($s['employee']['employee_id']), $ctx);
            }
            DB::table('payroll_periods')->where('id', $periodId)->update([
                'warnings' => json_encode(array_values($warnings)), 'calculated_at' => now(), 'calculated_by' => $actorId, 'updated_at' => now(),
            ]);
            $this->refreshTotals($periodId);
        });

        return [
            'employees' => count($slips), 'skipped' => $skipped, 'warnings' => $warnings,
            'totals' => ['gross' => round(array_sum(array_map(fn ($s) => $s['result']['summary']['gross_earnings'], $slips)), 2),
                         'take_home' => round(array_sum(array_map(fn ($s) => $s['result']['summary']['take_home_pay'], $slips)), 2)],
        ];
    }

    /**
     * Hitung ulang SATU karyawan pada periode `open` ("Hitung Ulang" / "Terapkan & Hitung Ulang" di baris karyawan).
     * Saklar BPJS/PPh 21 per karyawan yang tersimpan di payroll_employee_overrides ikut dipakai.
     *
     * @return array{name:string,take_home:float}
     */
    public function calculateEmployee(int $periodId, int $employeeId, ?int $actorId): array
    {
        $period = $this->openPeriod($periodId);
        $start  = (string) $period->period_start;
        $end    = (string) $period->period_end;
        $ctx    = $this->context($start, $end, (array) $period);

        $e = collect($this->employees([$employeeId], true))->first();
        if (!$e) {
            throw new InvalidArgumentException('This employee is not included in payroll (inactive, blocked, or “Include this employee…” is off).');
        }
        $data = $this->loadEmployeeData([$employeeId], $start, $end, $periodId, $ctx);
        $out  = $this->computeEmployee($e, $data, $ctx, $start, $end, $employeeId);
        if ($out === null) {
            throw new InvalidArgumentException($e['name'] . ' has no Base Salary covering this period, and no corrections.');
        }

        DB::transaction(function () use ($periodId, $employeeId, $out, $ctx, $actorId, $e, $data) {
            $previous = DB::table('payroll_slips')->where('period_id', $periodId)->where('employee_id', $employeeId)->first();
            DB::table('payroll_slips')->where('period_id', $periodId)->where('employee_id', $employeeId)->delete();
            $this->persistSlip($periodId, $out, $previous, $ctx);

            // Peringatan periode: ganti baris milik karyawan ini saja.
            $old = json_decode((string) DB::table('payroll_periods')->where('id', $periodId)->value('warnings'), true) ?: [];
            $keep = array_values(array_filter($old, fn ($w) => !str_starts_with((string) $w, $e['name'] . ':')));
            foreach (array_merge($out['result']['warnings'], $out['attendance_warnings'], $this->dataWarnings($e, $data)) as $w) {
                $keep[] = $e['name'] . ': ' . $w;
            }
            DB::table('payroll_periods')->where('id', $periodId)->update([
                'warnings' => json_encode($keep), 'calculated_at' => now(), 'calculated_by' => $actorId, 'updated_at' => now(),
            ]);
            $this->refreshTotals($periodId);
        });

        return ['name' => $e['name'], 'take_home' => $out['result']['summary']['take_home_pay']];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Simulation
    // ═════════════════════════════════════════════════════════════════════════

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
        $att = $this->attendanceSummary($employeeId, $data, $ctx, $rows);

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
            'workdays' => count($ctx['workdays']), 'hours' => $otHours,
            'attendance_entered' => ($absent + (float) ($f['days_sick'] ?? 0) + (float) ($f['days_leave'] ?? 0) + (float) ($f['days_permit'] ?? 0) + $lateCount + $lateMinutes) > 0];
    }

    // ═════════════════════════════════════════════════════════════════════════
    // internal
    // ═════════════════════════════════════════════════════════════════════════

    private function openPeriod(int $periodId): object
    {
        $period = DB::table('payroll_periods')->where('id', $periodId)->first();
        if (!$period) {
            throw new InvalidArgumentException('Payroll period not found.');
        }
        if (!PayrollPeriodRules::isEditable($period->status)) {
            throw new InvalidArgumentException('A locked payroll period cannot be recalculated.');
        }

        return $period;
    }

    /**
     * Pengaturan yang berlaku untuk periode: BPJS, PPh 21 tahun pajak, kebijakan payroll, kalender kerja, dan jendela absensi
     * (periode absensi + cut-off). Tanpa baris periode (simulasi), jendela absensi = periode gaji.
     *
     * @param array<string,mixed>|null $periodRow
     */
    private function context(string $start, string $end, ?array $periodRow = null): array
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

        $win = PayrollPeriodRules::attendanceWindow($periodRow ?? ['period_start' => $start, 'period_end' => $end]);
        $holidays = $this->holidays->getHolidayDates((int) substr(min($start, $win['start']), 0, 4), (int) substr(max($end, $win['end']), 0, 4));
        $dow = $settings['work_days_per_week'];

        $win['workdays'] = PayrollCalendar::workdays($win['start'], $win['trusted_end'], $dow, $holidays);
        $win['assumed']  = $win['trusted_end'] < $win['end']
            ? PayrollCalendar::workdays(date('Y-m-d', strtotime($win['trusted_end'] . ' +1 day')), $win['end'], $dow, $holidays) : [];

        return [
            'bpjs' => $bpjs, 'pph21' => $pph, 'settings' => $settings, 'att' => $win,
            'workdays' => PayrollCalendar::workdays($start, $end, $dow, $holidays),
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
        // Tabel yang sama dengan kotak Salary Components (Master Employee → Contract): `kind` dipetakan ke kategori aturan
        // payroll oleh EmployeeSalaryComponent::toRuleRow(). Baris tanpa effective_to berlaku seterusnya.
        $components = EmployeeSalaryComponent::query()->whereIn('employee_id', $ids)
            ->where('effective_from', '<=', $end)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $start))
            ->get()->groupBy('employee_id')
            ->map(fn ($g) => $g->map(fn (EmployeeSalaryComponent $c) => $c->toRuleRow())->all())->all();

        // Jendela absensi: periode absensi dipotong cut-off. Lembur/absensi/cuti sesudah cut-off ditangani di payroll berikutnya.
        $aStart = $ctx['att']['start'];
        $aEnd   = $ctx['att']['trusted_end'];

        $overtime = DB::table('overtime_requests')->whereIn('employee_id', $ids)->where('status', 'approved')
            ->whereBetween('overtime_date', [$aStart, $aEnd])->orderBy('overtime_date')->get()
            ->groupBy('employee_id')->map(fn ($g) => $g->map(fn ($r) => [
                'id' => (int) $r->id, 'date' => (string) $r->overtime_date, 'duration_minutes' => (int) $r->duration_minutes, 'day_type' => $r->day_type,
            ])->all())->all();

        // Koreksi Payroll: kurang bayar / lebih bayar dari periode asal, atau penyesuaian biasa.
        $adjustments = $periodId === null ? [] : DB::table('payroll_adjustments as a')
            ->leftJoin('payroll_periods as sp', 'sp.id', '=', 'a.source_period_id')
            ->where('a.period_id', $periodId)->whereIn('a.employee_id', $ids)->orderBy('a.id')
            ->get(['a.*', 'sp.name as source_name'])
            ->groupBy('employee_id')->map(fn ($g) => $g->map(function ($r) {
                $label = ['underpaid' => 'Correction (underpaid)', 'overpaid' => 'Correction (overpaid)'][$r->category] ?? null;
                $name  = $label === null ? $r->name
                    : $label . ($r->source_name ? ' · ' . $r->source_name : '') . ': ' . ($r->component ?: $r->name) . ($r->component && $r->name && $r->name !== $r->component ? ' — ' . $r->name : '');

                return ['id' => (int) $r->id, 'kind' => $r->kind, 'name' => mb_substr($name, 0, 190), 'amount' => (float) $r->amount, 'taxable' => (bool) $r->taxable];
            })->all())->all();

        // Saklar per karyawan per periode (BPJS Kesehatan / BPJS TK / PPh 21): null = ikut data master.
        $overrides = $periodId === null ? [] : DB::table('payroll_employee_overrides')->where('period_id', $periodId)->whereIn('employee_id', $ids)->get()
            ->keyBy('employee_id')->map(fn ($r) => [
                'bpjs_health' => $r->bpjs_health === null ? null : (bool) $r->bpjs_health,
                'bpjs_employment' => $r->bpjs_employment === null ? null : (bool) $r->bpjs_employment,
                'pph21' => $r->pph21 === null ? null : (bool) $r->pph21,
            ])->all();

        $banks = DB::table('employee_bank')->whereIn('employee_id', $ids)
            ->where(fn ($q) => $q->whereNull('valid_from')->orWhere('valid_from', '<=', $end))
            ->where(fn ($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>=', $end))
            ->orderByDesc('valid_from')->get()->groupBy('employee_id')->map(fn ($g) => (array) $g->first())->all();

        $npwp = DB::table('employee_identification')->whereIn('employee_id', $ids)->where('identification_type', 'NPWP')
            ->pluck('employee_id')->flip()->all();

        $attendance = DB::table('attendance_records')->whereIn('employee_id', $ids)->whereBetween('attendance_date', [$aStart, $aEnd])
            ->get(['employee_id', 'attendance_date', 'late_minutes'])->groupBy('employee_id')
            ->map(fn ($g) => $g->mapWithKeys(fn ($r) => [substr((string) $r->attendance_date, 0, 10) => (int) $r->late_minutes])->all())->all();

        $leaves = DB::table('leave_permit_applications as a')->join('leave_permit_types as t', 't.id', '=', 'a.leave_permit_type_id')
            ->whereIn('a.employee_id', $ids)->where('a.status', 'approved')
            ->where('a.start_date', '<=', $aEnd)->where('a.end_date', '>=', $aStart)
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

        return compact('components', 'overtime', 'adjustments', 'overrides', 'banks', 'npwp', 'ytd', 'attendance', 'leaves', 'reimbursements');
    }

    /**
     * Ringkasan kehadiran pada rentang yang dicakup gaji pokok karyawan di dalam jendela absensi (sampai cut-off).
     * Hari kerja SESUDAH cut-off tidak dihitung sebagai alpa (`assumed_days`: dianggap penuh).
     *
     * @param array<int,array<string,mixed>> $components
     * @return array<string,mixed> available=false bila karyawan tak punya presensi sama sekali di rentang itu
     */
    private function attendanceSummary(int $id, array $data, array $ctx, array $components): array
    {
        $set = $ctx['settings'];
        $win = $ctx['att'];
        $start = $win['start'];
        $end   = $win['trusted_end'];

        $empty = ['available' => false, 'covered_workdays' => 0, 'present' => 0, 'late_days' => 0, 'late_minutes' => 0, 'late_minutes_chargeable' => 0,
            'sick' => 0.0, 'leave' => 0.0, 'permit' => 0.0, 'paid_leave' => 0.0, 'paid_permit' => 0.0, 'absent' => 0.0, 'unpaid_leave' => 0.0,
            'deductible_days' => 0.0, 'assumed_days' => 0, 'cutoff' => $win['cutoff'], 'window_start' => $win['start'], 'window_end' => $win['end']];

        $from = $end; $to = $start; $firstBase = null; $lastBaseEnd = null;
        foreach ($components as $c) {
            if ($c['category'] === SalaryComponentRules::BASE && $c['is_active'] && $c['effective_from'] <= $win['end'] && (empty($c['effective_to']) || $c['effective_to'] >= $start)) {
                $from = min($from, max($start, $c['effective_from']));
                $to   = max($to, empty($c['effective_to']) ? $end : min($end, $c['effective_to']));
                $firstBase = $firstBase === null ? $c['effective_from'] : min($firstBase, $c['effective_from']);
                $lastBaseEnd = empty($c['effective_to']) ? '9999-12-31' : max((string) $lastBaseEnd, $c['effective_to']);
            }
        }
        if ($from > $to) {
            return $empty;
        }

        $work = array_values(array_filter($win['workdays'], fn ($d) => $d >= $from && $d <= $to));
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

        $sick = $leave = $permit = $unpaid = $paidLeave = $paidPermit = 0.0;
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
            } elseif ($cat === 'permit') {
                $paidPermit += $days;
            } else {
                $paidLeave += $days;
            }
        }

        $absent = max(0.0, count($work) - $presentWork - ($sick + $leave + $permit));

        $assumed = 0;
        foreach ($win['assumed'] as $d) {
            if ($firstBase !== null && $d >= $firstBase && $d <= $lastBaseEnd) {
                $assumed++;
            }
        }

        return [
            'available' => $rec !== [], 'covered_workdays' => count($work), 'present' => $presentWork,
            'late_days' => $lateDays, 'late_minutes' => $lateMin, 'late_minutes_chargeable' => $chargeable,
            'sick' => $sick, 'leave' => $leave, 'permit' => $permit, 'paid_leave' => $paidLeave, 'paid_permit' => $paidPermit,
            'absent' => $absent, 'unpaid_leave' => $unpaid, 'deductible_days' => round($absent + $unpaid, 2),
            'assumed_days' => $assumed, 'cutoff' => $win['cutoff'], 'window_start' => $win['start'], 'window_end' => $win['end'],
        ];
    }

    /**
     * @param array<string,mixed> $e
     * @return array<string,mixed>|null null = dilewati
     */
    private function computeEmployee(array $e, array $data, array $ctx, string $start, string $end, int $id): ?array
    {
        $set         = $ctx['settings'];
        $components  = $data['components'][$id] ?? [];
        $adjustments = $data['adjustments'][$id] ?? [];

        // Saklar per karyawan untuk periode ini (null = ikut master).
        $ov = $data['overrides'][$id] ?? null;
        $empSet = $set;
        if ($ov) {
            if ($ov['bpjs_health'] !== null)     { $e['bpjs_health_active'] = $ov['bpjs_health']; }
            if ($ov['bpjs_employment'] !== null) { $e['bpjs_employment_active'] = $ov['bpjs_employment']; }
            if ($ov['pph21'] === false)          { $empSet['pph21_enabled'] = false; }
        }

        // Ringkasan kehadiran SELALU dihitung (untuk tampilan); hanya dipakai potongan bila kebijakan menyalakannya.
        $att = $this->attendanceSummary($id, $data, $ctx, $components);
        $needAttendance = self::attendanceAppliesTo($set, $start) && ($set['absence_deduction_enabled'] || $set['late_penalty_enabled']);
        $attWarnings = [];
        if ($needAttendance && !$att['available']) {
            $attWarnings[] = 'No attendance records in this period — absence deduction and late penalty were skipped.';
        }
        $att['applied'] = $needAttendance && $att['available'];

        $result = PayrollCalculator::calculate([
            'start' => $start, 'end' => $end, 'components' => $components,
            'overtime' => $data['overtime'][$id] ?? [], 'adjustments' => $adjustments, 'reimbursements' => $data['reimbursements'][$id] ?? [],
            'workdays' => $ctx['workdays'],
            'attendance' => $att['applied'] ? ['deductible_days' => $att['deductible_days'], 'late_minutes_chargeable' => $att['late_minutes_chargeable']] : null,
            'employee' => [
                'ptkp_code' => $e['ptkp_code'], 'bpjs_health_active' => $e['bpjs_health_active'],
                'bpjs_employment_active' => $e['bpjs_employment_active'], 'bpjs_dependents' => $e['bpjs_dependents'],
                'has_tax_id' => isset($data['npwp'][$id]),
            ],
            'settings' => $empSet, 'bpjs' => $ctx['bpjs'], 'pph21' => $ctx['pph21'],
            'final_tax_period' => $ctx['final'],
            'ytd' => $data['ytd'][$id] ?? ['gross' => 0, 'employee_pension' => 0, 'pph21_withheld' => 0, 'months' => 0],
        ]);

        $hasBase = collect($components)->contains(fn ($c) => $c['category'] === 'base' && $c['is_active']
            && $c['effective_from'] <= $end && (empty($c['effective_to']) || $c['effective_to'] >= $start));
        if (!$hasBase && !$adjustments) {
            return null;
        }

        $otMinutes = array_sum(array_column($data['overtime'][$id] ?? [], 'duration_minutes'));

        return [
            'employee' => $e, 'bank' => $data['banks'][$id] ?? null, 'result' => $result, 'attendance' => $att,
            'attendance_warnings' => $attWarnings, 'overrides' => $ov, 'overtime_hours' => round($otMinutes / 60, 2),
        ];
    }

    /** Menyimpan satu slip + itemnya; nomor slip & jejak Generate Slip sebelumnya dipertahankan. */
    private function persistSlip(int $periodId, array $s, ?object $previous, array $ctx): void
    {
        $r = $s['result']; $sum = $r['summary']; $e = $s['employee'];
        $now = now();

        $slipNo = $previous?->slip_no;
        $generated = $slipNo !== null && $previous->slip_generated_at !== null;

        $slipId = DB::table('payroll_slips')->insertGetId([
            'period_id' => $periodId, 'employee_id' => $e['employee_id'],
            'slip_no' => $slipNo,
            'employee_name' => $e['name'], 'employee_eci' => $e['eci'], 'position' => $e['position'], 'department' => $e['department'],
            'ptkp_code' => $e['ptkp_code'], 'bank_name' => $s['bank']['bank_name'] ?? null, 'bank_account' => $s['bank']['account_number'] ?? null,
            'gross_earnings' => $sum['gross_earnings'], 'overtime' => $sum['overtime'], 'component_deductions' => $sum['component_deductions'],
            'bpjs_employee' => $sum['bpjs_employee'], 'bpjs_employer' => $sum['bpjs_employer'], 'pph21' => $sum['pph21'],
            'total_deductions' => $sum['total_deductions'], 'take_home_pay' => $sum['take_home_pay'],
            'tax_gross' => $r['pph21']['tax_gross'], 'employee_pension' => $r['bpjs']['totals']['employee_pension'],
            'pph21_refund' => $r['pph21']['refund'],
            'status' => $generated ? 'generated' : 'calculated',
            'slip_generated_at' => $generated ? $previous->slip_generated_at : null,
            'slip_generated_by' => $generated ? $previous->slip_generated_by : null,
            'verification_code' => $generated ? self::verificationCode($periodId, (int) $e['employee_id'], (int) $slipNo, (float) $sum['take_home_pay'], (float) $sum['gross_earnings']) : null,
            'breakdown' => json_encode([
                'bpjs' => $r['bpjs'], 'pph21' => $r['pph21'], 'summary' => $sum, 'warnings' => $r['warnings'],
                'attendance' => $s['attendance'], 'overtime_hours' => $s['overtime_hours'], 'overrides' => $s['overrides'],
                'rates' => $ctx['snapshot'],
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
    }

    /** Total periode selalu dihitung dari slip yang ada (satu sumber kebenaran). */
    private function refreshTotals(int $periodId): void
    {
        $t = DB::table('payroll_slips')->where('period_id', $periodId)->selectRaw(
            'COUNT(*) c, COALESCE(SUM(gross_earnings),0) g, COALESCE(SUM(total_deductions),0) d, COALESCE(SUM(take_home_pay),0) t,
             COALESCE(SUM(bpjs_employee),0) be, COALESCE(SUM(bpjs_employer),0) br, COALESCE(SUM(pph21),0) p'
        )->first();

        DB::table('payroll_periods')->where('id', $periodId)->update([
            'employee_count' => (int) $t->c, 'total_gross' => round((float) $t->g, 2), 'total_deductions' => round((float) $t->d, 2),
            'total_take_home' => round((float) $t->t, 2), 'total_bpjs_employee' => round((float) $t->be, 2),
            'total_bpjs_employer' => round((float) $t->br, 2), 'total_pph21' => round((float) $t->p, 2), 'updated_at' => now(),
        ]);
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
