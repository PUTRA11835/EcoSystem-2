<?php

namespace App\Support\Payroll;

/**
 * Pembulatan kebijakan payroll — MURNI. Mode: none (tidak dibulatkan) | nearest (terdekat) | up (ke atas) | down (ke bawah).
 * Dipakai untuk menit lembur, dasar TER bulanan, dan pembulatan gaji bersih.
 */
class PayrollRounding
{
    public const MODES = ['none' => 'No rounding', 'nearest' => 'Nearest', 'up' => 'Round up', 'down' => 'Round down'];

    public static function apply(float $value, string $mode, float $increment): float
    {
        if ($mode === 'none' || $increment <= 0) {
            return $value;
        }
        $q = round($value / $increment, 6);   // buang derau floating point sebelum floor/ceil

        return match ($mode) {
            'up'      => ceil($q) * $increment,
            'down'    => floor($q) * $increment,
            'nearest' => round($q) * $increment,
            default   => $value,
        };
    }

    /**
     * Menit lembur yang dibayar: di bawah ambang minimum = 0; selebihnya dibulatkan ke kelipatan $roundTo menit (0 = tidak).
     */
    public static function overtimeMinutes(int $minutes, int $minThreshold, int $roundTo, string $mode): int
    {
        if ($minutes <= 0 || $minutes < $minThreshold) {
            return 0;
        }

        return (int) self::apply((float) $minutes, $roundTo > 0 ? $mode : 'none', (float) $roundTo);
    }
}
