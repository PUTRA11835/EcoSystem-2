<?php

namespace App\Services\Payroll;

use App\Support\Payroll\BpjsCalculator;
use App\Support\Payroll\BpjsSettingRules;
use App\Support\Payroll\Money;
use Illuminate\Support\Facades\DB;

/**
 * Pengaturan BPJS berefektif-tanggal: baca versi berlaku (untuk payroll & simulasi) dan buat versi baru.
 * Versi lama TIDAK pernah diubah atau dihapus oleh layanan ini (append-only).
 */
class BpjsSettingsService
{
    /** Semua versi, terbaru dulu. @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return DB::table('bpjs_settings')->orderByDesc('effective_date')->get()
            ->map(fn ($r) => $this->normalize((array) $r))->all();
    }

    /**
     * Versi yang berlaku pada $date (default hari ini). Satu pintu — payroll memakai ini juga.
     *
     * @return array<string,mixed>
     * @throws \InvalidArgumentException bila belum ada versi berlaku
     */
    public function effectiveOn(?string $date = null): array
    {
        return BpjsSettingRules::effectiveOn($this->all(), $date ?? now()->toDateString());
    }

    /**
     * Buat versi baru dari isian formulir (angka dalam format Indonesia diterima).
     *
     * @param  array<string,mixed> $input
     * @return string[] galat; kosong = tersimpan
     */
    public function create(array $input, ?int $actorId): array
    {
        $clean = ['effective_date' => trim((string) ($input['effective_date'] ?? '')), 'notes' => trim((string) ($input['notes'] ?? ''))];
        foreach ([...array_keys(BpjsSettingRules::RATE_FIELDS), ...array_keys(BpjsSettingRules::AMOUNT_FIELDS)] as $k) {
            // Nilai yang tak bisa di-parse dibiarkan apa adanya supaya validasi menolaknya dengan pesan jelas.
            $clean[$k] = Money::parse($input[$k] ?? null) ?? ($input[$k] ?? null);
        }

        $errors = BpjsSettingRules::validate($clean, DB::table('bpjs_settings')->pluck('effective_date')->map(fn ($d) => (string) $d)->all());
        if ($errors) {
            return $errors;
        }

        DB::table('bpjs_settings')->insert([
            'effective_date'        => $clean['effective_date'],
            'health_employer_rate'  => round((float) $clean['health_employer_rate'], 2),
            'health_employee_rate'  => round((float) $clean['health_employee_rate'], 2),
            'health_min_base'       => round((float) $clean['health_min_base'], 2),
            'health_cap'            => round((float) $clean['health_cap'], 2),
            'jht_employer_rate'     => round((float) $clean['jht_employer_rate'], 2),
            'jht_employee_rate'     => round((float) $clean['jht_employee_rate'], 2),
            'jp_employer_rate'      => round((float) $clean['jp_employer_rate'], 2),
            'jp_employee_rate'      => round((float) $clean['jp_employee_rate'], 2),
            'jp_cap'                => round((float) $clean['jp_cap'], 2),
            'jkk_rate'              => round((float) $clean['jkk_rate'], 2),
            'jkm_rate'              => round((float) $clean['jkm_rate'], 2),
            'health_in_payroll'     => !empty($input['health_in_payroll']),
            'employment_in_payroll' => !empty($input['employment_in_payroll']),
            'notes'                 => $clean['notes'] === '' ? null : mb_substr($clean['notes'], 0, 1000),
            'created_by'            => $actorId,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);

        return [];
    }

    /**
     * Simulasi iuran dari satu upah (tanpa menyimpan apa pun).
     *
     * @return array<string,mixed>
     */
    public function simulate(float $wage, ?string $date, bool $healthActive, bool $employmentActive, int $dependents): array
    {
        $setting = $this->effectiveOn($date);

        return [
            'effective_date' => $setting['effective_date'],
            'result' => BpjsCalculator::calculate($wage, $setting, [
                'health_active' => $healthActive, 'employment_active' => $employmentActive, 'dependents' => $dependents,
            ]),
        ];
    }

    /** @param array<string,mixed> $r @return array<string,mixed> */
    private function normalize(array $r): array
    {
        foreach ($r as $k => $v) {
            if (str_ends_with($k, '_rate') || str_ends_with($k, '_cap') || $k === 'health_min_base') {
                $r[$k] = (float) $v;
            }
        }
        $r['effective_date']        = (string) $r['effective_date'];
        $r['health_in_payroll']     = (bool) $r['health_in_payroll'];
        $r['employment_in_payroll'] = (bool) $r['employment_in_payroll'];

        return $r;
    }
}
