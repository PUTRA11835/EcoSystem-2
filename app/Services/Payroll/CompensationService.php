<?php

namespace App\Services\Payroll;

use App\Models\EmployeeHrProfile;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollSetting;
use App\Support\Payroll\PtkpRules;
use App\Support\Payroll\SalaryComponentRules;
use Illuminate\Support\Facades\DB;

/**
 * Seksi "Compensation" karyawan = penanda PAJAK & BPJS untuk payroll (di employee_hr_profile) + ringkasan gaji (baca saja).
 *
 * Komponen gaji TIDAK diedit di sini: itu kotak "Salary Components" di Master Employee → Contract
 * (EmployeeSalaryComponentController; tabel yang sama, terisi otomatis dari Offering Letter). Payroll hanya membacanya.
 *
 * Data ini boleh dikumpulkan sekarang, tetapi tidak dipakai perhitungan sebelum modul Payroll dinyalakan
 * (Payroll → Settings, HC-D21). Penegakan izin di rute (`employee.section:compensation`).
 */
class CompensationService
{
    /** @return array<string,mixed> */
    public function forEmployee(int $employeeId): array
    {
        $profile = EmployeeHrProfile::where('employee_id', $employeeId)->first();

        $rows = EmployeeSalaryComponent::where('employee_id', $employeeId)
            ->orderByRaw("case kind when 'base' then 0 when 'fixed' then 1 when 'variable' then 2 else 3 end")
            ->orderBy('effective_from')->orderBy('id')
            ->get();

        $today = now()->toDateString();
        $rules = $rows->map(fn (EmployeeSalaryComponent $c) => $c->toRuleRow())->all();
        $totals = SalaryComponentRules::totalsOn($rules, $today);

        return [
            'payroll_enabled' => PayrollSetting::isEnabled(),
            'tax' => [
                'ptkp_code'              => $profile?->ptkp_code,
                'dependents_count'       => $profile?->dependents_count,
                'ter_category'           => PtkpRules::terCategory($profile?->ptkp_code),
                'bpjs_health_active'     => $profile?->bpjs_health_active,
                'bpjs_employment_active' => $profile?->bpjs_employment_active,
                'bpjs_dependents_count'  => $profile?->bpjs_dependents_count,
                'payroll_activated'      => (bool) $profile?->payroll_activated,
            ],
            'ptkp_options' => PtkpRules::CODES,
            'categories'   => SalaryComponentRules::CATEGORIES,
            // Baca saja — diubah di kotak Salary Components.
            'components'   => array_map(fn (array $r) => $r + ['active_today' => SalaryComponentRules::isEffectiveOn($r, $today)], $rules),
            'summary'      => $totals + ['base' => $this->safeBase($rules, $today), 'as_of' => $today],
        ];
    }

    /**
     * Simpan penanda pajak & BPJS. Tanggungan PTKP diturunkan dari kode (tak diterima dari input).
     *
     * @param  array<string,mixed> $input
     * @return array{ok:bool, errors:array<string,string>}
     */
    public function saveTax(int $employeeId, array $input, ?int $actorId): array
    {
        $errors = [];
        $data   = [];

        if (array_key_exists('ptkp_code', $input)) {
            $raw  = is_string($input['ptkp_code']) ? trim($input['ptkp_code']) : '';
            $code = PtkpRules::normalize($raw);
            if ($raw !== '' && $code === null) {
                $errors['ptkp_code'] = 'Choose a valid PTKP status (TK/0–TK/3 or K/0–K/3).';
            }
            $data['ptkp_code']        = $code;
            $data['dependents_count'] = PtkpRules::dependents($code);
        }

        foreach (['bpjs_health_active', 'bpjs_employment_active', 'payroll_activated'] as $flag) {
            if (array_key_exists($flag, $input)) {
                $v = filter_var($input[$flag], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                $data[$flag] = $flag === 'payroll_activated' ? (bool) $v : $v;
            }
        }

        if (array_key_exists('bpjs_dependents_count', $input)) {
            $n = PtkpRules::normalizeBpjsDependents($input['bpjs_dependents_count']);
            if ($input['bpjs_dependents_count'] !== null && $input['bpjs_dependents_count'] !== '' && $n === null) {
                $errors['bpjs_dependents_count'] = 'Enter a whole number from 0 to 5.';
            }
            $data['bpjs_dependents_count'] = $n;
        }

        if (!empty($data['payroll_activated'])) {
            $finalPtkp = array_key_exists('ptkp_code', $data)
                ? $data['ptkp_code']
                : EmployeeHrProfile::where('employee_id', $employeeId)->value('ptkp_code');
            if (!$finalPtkp) {
                $errors['payroll_activated'] = 'Set the PTKP status before activating payroll for this employee.';
            }
        }

        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        DB::transaction(function () use ($employeeId, $data, $actorId) {
            $profile = EmployeeHrProfile::firstOrNew(['employee_id' => $employeeId]);
            // Kolom payroll sengaja di luar $fillable (HC-D21) — diisi lewat jalur khusus ini saja.
            $profile->forceFill($data + ['updated_by' => $actorId])->save();
        });

        return ['ok' => true, 'errors' => []];
    }

    private function safeBase(array $components, string $date): ?float
    {
        try {
            return SalaryComponentRules::baseSalaryOn($components, $date);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
