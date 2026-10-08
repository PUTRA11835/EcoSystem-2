<?php

namespace App\Services\Payroll;

use App\Models\EmployeeHrProfile;
use App\Models\EmployeeSalaryComponent;
use App\Support\Payroll\Money;
use App\Support\Payroll\PtkpRules;
use App\Support\Payroll\SalaryComponentRules;
use Illuminate\Support\Facades\DB;

/**
 * Data kompensasi karyawan: penanda pajak/BPJS (di employee_hr_profile) + komponen gaji.
 *
 * Data ini boleh dikumpulkan sekarang, tetapi TIDAK dipakai perhitungan sebelum
 * saklar Payroll (Payroll → Settings) dinyalakan (HC-D21). Penegakan izin di rute (`employee.section:compensation`).
 * Pembatalan tidak menghapus riwayat: komponen dinonaktifkan/diakhiri, kecuali baris salah input yang dihapus eksplisit.
 */
class CompensationService
{
    /** @return array<string,mixed> */
    public function forEmployee(int $employeeId): array
    {
        $profile = EmployeeHrProfile::where('employee_id', $employeeId)->first();

        $components = EmployeeSalaryComponent::where('employee_id', $employeeId)
            ->orderByRaw("FIELD(category, 'base', 'fixed_allowance', 'variable_allowance', 'deduction')")
            ->orderByDesc('effective_from')
            ->get()
            ->map(fn (EmployeeSalaryComponent $c) => $this->present($c))
            ->all();

        $today  = now()->toDateString();
        $totals = SalaryComponentRules::totalsOn($components, $today);

        return [
            'payroll_enabled' => \App\Models\PayrollSetting::isEnabled(),
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
            'components'   => $components,
            'summary'      => $totals + [
                'base' => $this->safeBase($components, $today),
                'as_of' => $today,
            ],
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

    /**
     * Tambah/ubah komponen gaji. $componentId null = baru.
     *
     * @param  array<string,mixed> $input
     * @return array{ok:bool, errors:array<string,string>, id?:int}
     */
    public function saveComponent(int $employeeId, ?int $componentId, array $input, ?int $actorId): array
    {
        $candidate = [
            'category'       => (string) ($input['category'] ?? ''),
            'name'           => trim((string) ($input['name'] ?? '')),
            // Format uang Indonesia (1.000.000,00) atau angka polos; nilai tak sah menjadi null → ditolak di bawah.
            'amount'         => Money::parse($input['amount'] ?? 0),
            'effective_from' => (string) ($input['effective_from'] ?? ''),
            'effective_to'   => ($input['effective_to'] ?? '') !== '' ? (string) $input['effective_to'] : null,
            'is_active'      => filter_var($input['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'taxable'        => filter_var($input['taxable'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'bpjs_base'      => filter_var($input['bpjs_base'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ];

        $errors = [];
        if ($candidate['name'] === '' || mb_strlen($candidate['name']) > 120) {
            $errors['name'] = 'Enter a component name (maximum 120 characters).';
        }
        if ($candidate['amount'] === null || $candidate['amount'] < 0 || $candidate['amount'] > 9_999_999_999_999.99) {
            $errors['amount'] = 'Enter a valid amount.';
        }
        foreach (['effective_from', 'effective_to'] as $f) {
            if ($candidate[$f] !== null && $candidate[$f] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $candidate[$f])) {
                $errors[$f] = 'Enter a valid date.';
            }
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $existing = null;
        if ($componentId !== null) {
            $existing = EmployeeSalaryComponent::where('employee_id', $employeeId)->find($componentId);
            if (!$existing) {
                return ['ok' => false, 'errors' => ['_' => 'Component not found.']];
            }
            // Gaji Pokok wajib tidak boleh diubah kategorinya.
            if ($existing->is_mandatory) {
                $candidate['category'] = $existing->category;
            }
        }

        $others = EmployeeSalaryComponent::where('employee_id', $employeeId)
            ->when($componentId, fn ($q) => $q->where('id', '!=', $componentId))
            ->get()->map(fn ($c) => $this->present($c))->all();

        $ruleErrors = SalaryComponentRules::validate($candidate, $others);
        if ($ruleErrors) {
            return ['ok' => false, 'errors' => ['_' => implode(' ', $ruleErrors)]];
        }

        $component = $existing ?? new EmployeeSalaryComponent(['employee_id' => $employeeId]);
        $component->fill([
            'name'           => $candidate['name'],
            'category'       => $candidate['category'],
            'amount'         => round((float) $candidate['amount'], 2),
            'effective_from' => $candidate['effective_from'],
            'effective_to'   => $candidate['effective_to'],
            'is_active'      => $candidate['is_active'],
            'taxable'        => $candidate['taxable'],
            'bpjs_base'      => $candidate['bpjs_base'],
        ]);
        if (!$existing) {
            $component->is_mandatory = $candidate['category'] === SalaryComponentRules::BASE;
        }
        $component->updated_by = $actorId;
        $component->save();

        return ['ok' => true, 'errors' => [], 'id' => $component->id];
    }

    /** @return array{ok:bool, message?:string} */
    public function deleteComponent(int $employeeId, int $componentId): array
    {
        $component = EmployeeSalaryComponent::where('employee_id', $employeeId)->find($componentId);
        if (!$component) {
            return ['ok' => false, 'message' => 'Component not found.'];
        }
        if ($component->is_mandatory) {
            return ['ok' => false, 'message' => 'The Base Salary cannot be deleted. End it with an end date and add the new one instead.'];
        }
        $component->delete();

        return ['ok' => true];
    }

    /** @return array<string,mixed> baris datar untuk aturan murni + tampilan */
    private function present(EmployeeSalaryComponent $c): array
    {
        return [
            'id'             => $c->id,
            'name'           => $c->name,
            'category'       => $c->category,
            'amount'         => (float) $c->amount,
            'effective_from' => $c->effective_from?->toDateString(),
            'effective_to'   => $c->effective_to?->toDateString(),
            'is_mandatory'   => (bool) $c->is_mandatory,
            'is_active'      => (bool) $c->is_active,
            'taxable'        => (bool) $c->taxable,
            'bpjs_base'      => (bool) $c->bpjs_base,
        ];
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
