<?php

namespace App\Console\Commands;

use App\Models\EmployeeSalaryComponent;
use App\Support\Payroll\SalaryComponentRules;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Isi awal `employee_salary_components` dari kontrak AKTIF (Payroll Fase 0, langkah 2).
 *
 * AMAN: default hanya mensimulasikan (dry-run); `--apply` baru menulis. Hanya MENAMBAH baris, tidak pernah
 * mengubah/menghapus. Karyawan yang sudah punya komponen apa pun dilewati (jadi aman dijalankan ulang, dan
 * data yang sudah diatur HR tidak tertimpa). `employee_contract.salary` = gaji pokok; `salary_components`
 * JSON [{name, amount}] = tunjangan tetap.
 */
class PayrollImportContractSalary extends Command
{
    protected $signature = 'payroll:import-contract-salary {--apply : Tulis ke database (tanpa opsi ini hanya simulasi)}';

    protected $description = 'Seed employee_salary_components from active contracts (dry-run by default, add-only, idempotent)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $contracts = DB::table('employee_contract')
            ->where('is_active', 1)
            ->where(fn ($q) => $q->whereNull('lifecycle_status')->orWhere('lifecycle_status', 'active'))
            ->whereNotNull('salary')
            ->where('salary', '>', 0)
            ->orderBy('employee_id')->orderByDesc('start_date')->orderByDesc('contract_id')
            ->get();

        $seen = $created = $skipped = 0;

        foreach ($contracts->groupBy('employee_id') as $employeeId => $rows) {
            $seen++;
            if (EmployeeSalaryComponent::where('employee_id', $employeeId)->exists()) {
                $skipped++;
                continue;
            }

            $contract = $rows->first();
            $from = $contract->start_date ?: now()->toDateString();
            $to   = $contract->end_date ?: null;

            $lines = [['Basic Salary', SalaryComponentRules::BASE, (float) $contract->salary, true]];
            foreach ((array) json_decode((string) ($contract->salary_components ?? '[]'), true) as $c) {
                if (!empty($c['name']) && isset($c['amount']) && (float) $c['amount'] > 0) {
                    $lines[] = [mb_substr((string) $c['name'], 0, 120), SalaryComponentRules::FIXED_ALLOWANCE, (float) $c['amount'], false];
                }
            }

            $this->line(sprintf('Employee %s: %d component(s), base %s from %s', $employeeId, count($lines), number_format($lines[0][2], 0, ',', '.'), $from));

            if ($apply) {
                DB::transaction(function () use ($employeeId, $lines, $from) {
                    foreach ($lines as [$name, $category, $amount, $mandatory]) {
                        EmployeeSalaryComponent::create([
                            'employee_id' => $employeeId, 'name' => $name, 'category' => $category, 'amount' => $amount,
                            'effective_from' => $from, 'effective_to' => null,
                            'is_mandatory' => $mandatory, 'is_active' => true, 'taxable' => true, 'bpjs_base' => true,
                        ]);
                    }
                });
            }
            $created += count($lines);
        }

        $this->info(sprintf(
            '%s: %d employee(s) with an active contract, %d skipped (already have components), %d component row(s) %s.',
            $apply ? 'APPLIED' : 'DRY-RUN', $seen, $skipped, $created, $apply ? 'written' : 'would be written'
        ));
        if (!$apply) {
            $this->comment('Nothing was written. Re-run with --apply to save.');
        }

        return self::SUCCESS;
    }
}
