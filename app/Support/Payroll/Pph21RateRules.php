<?php

namespace App\Support\Payroll;

/**
 * Validasi isian halaman PPh 21 Settings — MURNI (tanpa database). Mengembalikan pesan galat berbahasa Inggris
 * (tampil di UI); array kosong = sah.
 */
class Pph21RateRules
{
    /**
     * Lapisan Pasal 17: batas atas naik ketat, tarif 0–100 dan tidak turun, baris terakhir terbuka (null).
     *
     * @param array<int,array{upper_limit:mixed,rate:mixed}> $rows urut dari lapisan terendah
     * @return string[]
     */
    public static function validateBrackets(array $rows): array
    {
        return self::validateLayers($rows, 'upper_limit', 'Bracket', true);
    }

    /**
     * Satu kategori TER: batas atas naik ketat, tarif 0–100 dan tidak turun, baris terakhir terbuka (null).
     *
     * @param array<int,array{upper_limit:mixed,rate:mixed}> $rows
     * @return string[]
     */
    public static function validateTerCategory(array $rows, string $category): array
    {
        return self::validateLayers($rows, 'upper_limit', 'TER ' . $category . ' row', false);
    }

    /**
     * @param array<string,mixed> $amounts kode PTKP → nilai setahun
     * @return string[]
     */
    public static function validatePtkp(array $amounts): array
    {
        $errors = [];
        foreach (PtkpRules::CODES as $code) {
            if (!array_key_exists($code, $amounts) || !is_numeric($amounts[$code]) || (float) $amounts[$code] < 0) {
                $errors[] = "PTKP {$code}: enter a valid amount.";
            }
        }
        if (!$errors) {
            // Struktur PTKP: TK/0 dasar; tiap tanggungan menambah satu langkah tetap; K/n = TK/0 + (n+1) langkah.
            $base = (float) $amounts['TK/0'];
            $step = (float) $amounts['TK/1'] - $base;
            $ok   = $step > 0 && abs((float) $amounts['K/0'] - ($base + $step)) <= 0.5;
            foreach (range(1, 3) as $n) {
                $ok = $ok
                    && abs((float) $amounts["TK/$n"] - ($base + $step * $n)) <= 0.5
                    && abs((float) $amounts["K/$n"] - ($base + $step * ($n + 1))) <= 0.5;
            }
            if (!$ok) {
                $errors[] = 'PTKP amounts must rise by the same step per dependant: TK/n = TK/0 + n steps, K/n = TK/0 + (n+1) steps.';
            }
        }

        return $errors;
    }

    /** @return string[] */
    public static function validateSettings(array $in): array
    {
        $errors = [];
        $rate = $in['occupational_cost_rate'] ?? null;
        if (!is_numeric($rate) || $rate < 0 || $rate > 100) {
            $errors[] = 'Occupational cost rate must be between 0 and 100.';
        }
        $max = $in['occupational_cost_monthly_max'] ?? null;
        if (!is_numeric($max) || $max < 0) {
            $errors[] = 'Occupational cost monthly maximum must be zero or more.';
        }

        return $errors;
    }

    /** @return string[] */
    private static function validateLayers(array $rows, string $limitKey, string $label, bool $strictRate): array
    {
        $errors = [];
        if (count($rows) < 1) {
            return ["{$label}s: at least one row is required."];
        }

        $prevLimit = 0.0;
        $prevRate  = -1.0;
        $last      = count($rows) - 1;

        foreach (array_values($rows) as $i => $r) {
            $n    = $i + 1;
            $rate = $r['rate'] ?? null;
            if (!is_numeric($rate) || $rate < 0 || $rate > 100) {
                $errors[] = "{$label} {$n}: rate must be between 0 and 100.";
                continue;
            }
            if ((float) $rate < $prevRate) {
                $errors[] = "{$label} {$n}: rate cannot be lower than the previous row.";
            }
            $prevRate = (float) $rate;

            $limit = $r[$limitKey] ?? null;
            $open  = $limit === null || $limit === '';
            if ($i === $last) {
                if (!$open) {
                    $errors[] = "{$label} {$n}: the last row must have no upper limit.";
                }
            } else {
                if ($open || !is_numeric($limit)) {
                    $errors[] = "{$label} {$n}: enter the upper limit.";
                    continue;
                }
                if ((float) $limit <= $prevLimit) {
                    $errors[] = "{$label} {$n}: the upper limit must be higher than the previous row.";
                }
                $prevLimit = (float) $limit;
            }
        }

        return $errors;
    }
}
