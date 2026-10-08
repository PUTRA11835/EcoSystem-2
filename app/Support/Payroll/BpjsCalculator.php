<?php

namespace App\Support\Payroll;

use InvalidArgumentException;

/**
 * Iuran BPJS — MURNI (tanpa database; pengaturan diberikan pemanggil dari `bpjs_settings` versi yang berlaku).
 *
 * Dasar upah & tanggungan:
 *  - Kesehatan : upah dibatasi [health_min_base, health_cap]; pemberi kerja & pekerja menurut tarif. Anggota keluarga
 *                tambahan (di luar istri/suami dan 3 anak pertama) membayar tarif pekerja per orang, ditanggung pekerja.
 *  - JHT       : seluruh upah (tanpa batas atas).
 *  - JP        : upah dibatasi jp_cap.
 *  - JKK, JKM  : seluruh upah, ditanggung pemberi kerja.
 *
 * Program hanya dihitung bila saklar perusahaan (`*_in_payroll`) DAN penanda karyawan (aktif) menyala. Penanda
 * karyawan yang belum diisi (null) dianggap tidak aktif — tidak diam-diam dihitung.
 * Pembulatan: tiap komponen dibulatkan ke rupiah penuh (setengah ke atas).
 */
class BpjsCalculator
{
    /**
     * @param array<string,mixed> $s     satu baris bpjs_settings
     * @param array{health_active:?bool,employment_active:?bool,dependents:?int} $flags
     * @return array<string,mixed>
     */
    public static function calculate(float $wage, array $s, array $flags): array
    {
        if ($wage < 0) {
            throw new InvalidArgumentException('Wage cannot be negative.');
        }

        $healthOn = !empty($s['health_in_payroll']) && !empty($flags['health_active']);
        $empOn    = !empty($s['employment_in_payroll']) && !empty($flags['employment_active']);
        $deps     = max(0, (int) ($flags['dependents'] ?? 0));

        // ── Kesehatan ──
        $healthBase = 0.0;
        $healthEr = $healthEe = $healthDep = 0.0;
        if ($healthOn) {
            $healthBase = max(min($wage, (float) $s['health_cap']), (float) $s['health_min_base']);
            $healthEr   = self::pct($healthBase, $s['health_employer_rate']);
            $healthEe   = self::pct($healthBase, $s['health_employee_rate']);
            $healthDep  = $deps * $healthEe;
        }

        // ── Ketenagakerjaan ──
        $jhtEr = $jhtEe = $jpEr = $jpEe = $jkk = $jkm = 0.0;
        $jpBase = 0.0;
        if ($empOn) {
            $jpBase = min($wage, (float) $s['jp_cap']);
            $jhtEr  = self::pct($wage, $s['jht_employer_rate']);
            $jhtEe  = self::pct($wage, $s['jht_employee_rate']);
            $jpEr   = self::pct($jpBase, $s['jp_employer_rate']);
            $jpEe   = self::pct($jpBase, $s['jp_employee_rate']);
            $jkk    = self::pct($wage, $s['jkk_rate']);
            $jkm    = self::pct($wage, $s['jkm_rate']);
        }

        $employer = $healthEr + $jhtEr + $jpEr + $jkk + $jkm;
        $employee = $healthEe + $healthDep + $jhtEe + $jpEe;

        return [
            'wage'   => round($wage, 2),
            'health' => ['active' => $healthOn, 'base' => $healthBase, 'employer' => $healthEr, 'employee' => $healthEe, 'dependents' => $deps, 'dependents_amount' => $healthDep],
            'jht'    => ['active' => $empOn, 'base' => $empOn ? $wage : 0.0, 'employer' => $jhtEr, 'employee' => $jhtEe],
            'jp'     => ['active' => $empOn, 'base' => $jpBase, 'employer' => $jpEr, 'employee' => $jpEe],
            'jkk'    => ['active' => $empOn, 'base' => $empOn ? $wage : 0.0, 'employer' => $jkk],
            'jkm'    => ['active' => $empOn, 'base' => $empOn ? $wage : 0.0, 'employer' => $jkm],
            'totals' => [
                'employer' => $employer,
                'employee' => $employee,
                'total'    => $employer + $employee,
                // Masukan PPh 21: premi pemberi kerja (Kesehatan, JKK, JKM) = penghasilan bruto bila diatur demikian;
                // JHT + JP porsi pekerja = pengurang penghasilan neto.
                'employer_premiums_for_tax' => $healthEr + $jkk + $jkm,
                'employee_pension'          => $jhtEe + $jpEe,
            ],
        ];
    }

    /** Rupiah penuh, setengah ke atas. */
    private static function pct(float $base, mixed $rate): float
    {
        return round($base * (float) $rate / 100, 0, PHP_ROUND_HALF_UP);
    }
}
