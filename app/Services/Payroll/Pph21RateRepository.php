<?php

namespace App\Services\Payroll;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pembaca pengaturan PPh 21 per tahun pajak, dalam bentuk yang diminta Pph21Calculator.
 * Dipakai halaman Settings dan (kelak) mesin Payroll — satu pintu, jadi tarif tak pernah dibaca dua cara.
 * Tahun yang belum disiapkan = galat jelas (tidak diam-diam memakai tahun lain).
 */
class Pph21RateRepository
{
    /** Tahun yang sudah punya pengaturan, terbaru dulu. @return int[] */
    public function years(): array
    {
        return DB::table('pph21_settings')->orderByDesc('year')->pluck('year')->map(fn ($y) => (int) $y)->all();
    }

    public function hasYear(int $year): bool
    {
        return DB::table('pph21_settings')->where('year', $year)->exists();
    }

    /** @return array<string,float> kode PTKP → nilai setahun */
    public function ptkp(int $year): array
    {
        return DB::table('pph21_ptkp')->where('year', $year)->orderBy('id')->pluck('annual_amount', 'code')
            ->map(fn ($v) => (float) $v)->all();
    }

    /** @return array<int,array{upper_limit:?float,rate:float}> */
    public function brackets(int $year): array
    {
        return DB::table('pph21_progressive_brackets')->where('year', $year)->orderBy('seq')->get()
            ->map(fn ($r) => ['upper_limit' => $r->upper_limit === null ? null : (float) $r->upper_limit, 'rate' => (float) $r->rate])
            ->all();
    }

    /** @return array<string,array<int,array{gross_over:float,gross_upto:?float,rate:float}>> */
    public function ter(int $year): array
    {
        $out = [];
        foreach (DB::table('pph21_ter_rates')->where('year', $year)->orderBy('category')->orderBy('seq')->get() as $r) {
            $out[$r->category][] = [
                'gross_over' => (float) $r->gross_over,
                'gross_upto' => $r->gross_upto === null ? null : (float) $r->gross_upto,
                'rate'       => (float) $r->rate,
            ];
        }

        return $out;
    }

    /** @return array{occupational_cost_rate:float,occupational_cost_monthly_max:float,include_employer_premiums:bool,notes:?string} */
    public function settings(int $year): array
    {
        $row = DB::table('pph21_settings')->where('year', $year)->first();
        if (!$row) {
            throw new RuntimeException("PPh 21 settings for tax year {$year} have not been set up.");
        }

        return [
            'occupational_cost_rate'        => (float) $row->occupational_cost_rate,
            'occupational_cost_monthly_max' => (float) $row->occupational_cost_monthly_max,
            'include_employer_premiums'     => (bool) $row->include_employer_premiums,
            'notes'                         => $row->notes,
        ];
    }

    /** Paket lengkap untuk satu tahun (dipakai kalkulator/payroll). */
    public function bundle(int $year): array
    {
        return [
            'year'        => $year,
            'ptkp'        => $this->ptkp($year),
            'brackets'    => $this->brackets($year),
            'ter'         => $this->ter($year),
            'settings'    => $this->settings($year),
        ];
    }
}
