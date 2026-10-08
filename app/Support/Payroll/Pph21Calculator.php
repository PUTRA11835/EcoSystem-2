<?php

namespace App\Support\Payroll;

use InvalidArgumentException;

/**
 * Kalkulator PPh 21 pegawai tetap — MURNI (tanpa database; tabel tarif diberikan pemanggil dari Settings per tahun).
 *
 * Metode (PP 58/2023, PMK 168/2023):
 *  - Januari–November (dan masa pajak sebelum yang terakhir): TER bulanan = penghasilan bruto × tarif kategori A/B/C.
 *  - Masa pajak terakhir (Desember atau bulan berhenti bekerja): hitung ulang setahun dengan lapisan Pasal 17,
 *    lalu kurangi PPh 21 yang sudah dipotong Jan–Nov.
 *
 * Bentuk tabel:
 *  - TER   : [['gross_over'=>0, 'gross_upto'=>5400000|null, 'rate'=>0.0], …] (over TIDAK termasuk, upto termasuk)
 *  - Pasal17: [['upper_limit'=>60000000|null, 'rate'=>5.0], …] terurut naik, baris terakhir upper_limit null.
 *
 * Pembulatan: PPh 21 dibulatkan KE BAWAH ke rupiah penuh; PKP dibulatkan ke bawah ke ribuan (Pasal 17 UU PPh).
 */
class Pph21Calculator
{
    /**
     * Tarif TER untuk penghasilan bruto sebulan.
     *
     * @param array<int,array{gross_over:float|int,gross_upto:float|int|null,rate:float|int}> $layers
     */
    public static function terRate(float $gross, array $layers): float
    {
        if ($gross < 0) {
            throw new InvalidArgumentException('Gross income cannot be negative.');
        }
        foreach ($layers as $l) {
            if ($gross > (float) $l['gross_over'] && ($l['gross_upto'] === null || $gross <= (float) $l['gross_upto'])) {
                return (float) $l['rate'];
            }
        }

        // Bruto 0 jatuh di luar "di atas 0": tidak ada pajak.
        if ($gross == 0.0) {
            return 0.0;
        }

        throw new InvalidArgumentException('The TER table has a gap at gross income ' . $gross . '.');
    }

    /**
     * PPh 21 bulanan metode TER.
     *
     * @param  array<string,array<int,array<string,mixed>>> $terByCategory ['A'=>layers,'B'=>…,'C'=>…]
     * @return array{rate:float,tax:float,category:string}
     */
    public static function monthlyTer(float $gross, ?string $ptkpCode, array $terByCategory): array
    {
        $category = PtkpRules::terCategory($ptkpCode);
        if ($category === null) {
            throw new InvalidArgumentException('PTKP status is missing or invalid; cannot choose the TER category.');
        }
        if (empty($terByCategory[$category])) {
            throw new InvalidArgumentException('TER table for category ' . $category . ' is not set up for this year.');
        }

        $rate = self::terRate($gross, $terByCategory[$category]);

        return ['rate' => $rate, 'tax' => floor($gross * $rate / 100), 'category' => $category];
    }

    /** Biaya jabatan: rate% × bruto setahun, maksimal batas bulanan × jumlah bulan bekerja. */
    public static function occupationalCost(float $annualGross, int $months, float $ratePercent, float $monthlyMax): float
    {
        $months = max(1, min(12, $months));

        return round(min($annualGross * $ratePercent / 100, $monthlyMax * $months), 2);
    }

    /**
     * PPh terutang setahun atas PKP, lapisan Pasal 17. PKP dibulatkan ke bawah ke ribuan.
     *
     * @param array<int,array{upper_limit:float|int|null,rate:float|int}> $brackets
     */
    public static function progressiveTax(float $pkp, array $brackets): float
    {
        if ($pkp <= 0) {
            return 0.0;
        }
        $pkp  = floor($pkp / 1000) * 1000;
        $tax  = 0.0;
        $prev = 0.0;

        foreach ($brackets as $b) {
            $upper = $b['upper_limit'] === null ? INF : (float) $b['upper_limit'];
            if ($pkp <= $prev) {
                break;
            }
            $tax  += (min($pkp, $upper) - $prev) * (float) $b['rate'] / 100;
            $prev = $upper;
        }

        return floor($tax);
    }

    /**
     * Hitung PPh 21 setahun.
     *
     * @param array{
     *   annual_gross: float, months: int, employee_pension: float, ptkp_annual: float,
     *   occupational_cost_rate: float, occupational_cost_monthly_max: float
     * } $in  employee_pension = JHT + JP porsi KARYAWAN setahun (pengurang penghasilan neto)
     * @param array<int,array<string,mixed>> $brackets
     * @return array{annual_gross:float,occupational_cost:float,employee_pension:float,net_income:float,ptkp:float,pkp:float,tax:float}
     */
    public static function annualTax(array $in, array $brackets): array
    {
        $cost = self::occupationalCost(
            (float) $in['annual_gross'], (int) $in['months'],
            (float) $in['occupational_cost_rate'], (float) $in['occupational_cost_monthly_max']
        );
        $net = (float) $in['annual_gross'] - $cost - (float) $in['employee_pension'];
        $pkp = max(0.0, $net - (float) $in['ptkp_annual']);

        return [
            'annual_gross'      => round((float) $in['annual_gross'], 2),
            'occupational_cost' => $cost,
            'employee_pension'  => round((float) $in['employee_pension'], 2),
            'net_income'        => round($net, 2),
            'ptkp'              => round((float) $in['ptkp_annual'], 2),
            'pkp'               => round($pkp, 2),
            'tax'               => self::progressiveTax($pkp, $brackets),
        ];
    }

    /**
     * Masa pajak terakhir: PPh setahun dikurangi yang sudah dipotong sebelumnya.
     * Hasil negatif = kelebihan potong (`overpaid` true) — dikembalikan/diperhitungkan, tidak dipotong lagi.
     *
     * @return array{annual:array<string,float>,withheld:float,payable:float,overpaid:bool}
     */
    public static function finalPeriodTrueUp(array $annualInput, array $brackets, float $withheldBefore): array
    {
        $annual  = self::annualTax($annualInput, $brackets);
        $payable = $annual['tax'] - $withheldBefore;

        return [
            'annual'   => $annual,
            'withheld' => round($withheldBefore, 2),
            'payable'  => round($payable, 2),
            'overpaid' => $payable < 0,
        ];
    }
}
