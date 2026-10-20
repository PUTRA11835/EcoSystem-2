<?php

namespace App\Support\External;

use App\Support\Payroll\PayrollCalendar;

/**
 * Hitung penagihan konsultan eksternal (MURNI — tanpa database, mudah diuji): jam dari jam mulai–selesai, kuantitas tagih
 * menurut skema, faktor prorata bulan, dan bruto = kuantitas × tarif.
 *
 *   mandays      kuantitas = total jam ÷ jam per manday  (32 jam ÷ 8 = 4,00 mandays)
 *   hourly       kuantitas = total jam
 *   monthly      kuantitas = faktor prorata bulan (1,00 = bulan penuh)
 *   fixed_price  kuantitas = persen tagihan lembar ÷ 100 (milestone)
 *
 * Bruto belum dipotong pajak (PPh 21/23 di luar cakupan).
 */
class ExternalBilling
{
    /** Jam desimal antara dua jam "HH:MM"; null bila salah satu kosong/tak sah atau selesai ≤ mulai. */
    public static function hoursBetween(?string $start, ?string $end): ?float
    {
        $a = self::minutes($start);
        $b = self::minutes($end);
        if ($a === null || $b === null || $b <= $a) {
            return null;
        }

        return round(($b - $a) / 60, 2);
    }

    private static function minutes(?string $time): ?int
    {
        if ($time === null || !preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', trim($time), $m)) {
            return null;
        }

        return (int) $m[1] * 60 + (int) $m[2];
    }

    /**
     * Faktor prorata sebulan penuh = 1. Kontrak yang hanya mencakup sebagian bulan dihitung menurut aturan prorata kontrak.
     *
     * @param  string[]  $workdays  hari kerja bulan itu (Y-m-d) — dipakai bila prorata = working_days
     */
    public static function monthFactor(string $proration, string $contractStart, ?string $contractEnd, int $year, int $month, array $workdays = []): float
    {
        $first = sprintf('%04d-%02d-01', $year, $month);
        $last = date('Y-m-t', strtotime($first));
        $from = max($first, substr($contractStart, 0, 10));
        $to = min($last, $contractEnd ? substr($contractEnd, 0, 10) : $last);

        if ($from > $to) {
            return 0.0;
        }
        if ($proration === 'none') {
            return 1.0;
        }
        if ($proration === 'calendar_days') {
            $days = (int) date('t', strtotime($first));

            return round((((strtotime($to) - strtotime($from)) / 86400) + 1) / $days, 4);
        }

        $total = count($workdays);

        return $total === 0 ? 0.0 : round(PayrollCalendar::countWithin($workdays, $from, $to) / $total, 4);
    }

    /**
     * @param  array{scheme:?string,rate:?float,hours_per_manday:float,milestone_pct?:float}  $terms
     * @return array{qty:float,gross:?float}  gross null bila tarif belum ada
     */
    public static function compute(array $terms, float $totalHours, float $monthFactor = 1.0): array
    {
        $scheme = ExternalRules::schemeKey($terms['scheme'] ?? null) ?? 'mandays';
        $hpm = (float) ($terms['hours_per_manday'] ?? 8) > 0 ? (float) $terms['hours_per_manday'] : 8.0;

        $qty = match ($scheme) {
            'hourly'      => round($totalHours, 2),
            'monthly'     => round($monthFactor, 2),
            'fixed_price' => round(((float) ($terms['milestone_pct'] ?? 100)) / 100, 2),
            default       => round($totalHours / $hpm, 2),
        };

        $rate = $terms['rate'] ?? null;

        // Bruto dihitung dari kuantitas TANPA pembulatan 2 desimal untuk skema bulanan/fixed (faktor 4 desimal), agar prorata akurat.
        $exact = match ($scheme) {
            'monthly'     => $monthFactor,
            'fixed_price' => ((float) ($terms['milestone_pct'] ?? 100)) / 100,
            'hourly'      => $totalHours,
            default       => $totalHours / $hpm,
        };

        return ['qty' => $qty, 'gross' => $rate === null ? null : round((float) $rate * $exact, 2)];
    }
}
