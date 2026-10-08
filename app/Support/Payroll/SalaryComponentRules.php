<?php

namespace App\Support\Payroll;

use InvalidArgumentException;

/**
 * Aturan komponen gaji — MURNI (tanpa database), bisa diuji tanpa data gaji.
 *
 * Inti: pada satu tanggal, seorang karyawan punya TEPAT SATU Gaji Pokok aktif.
 * Baris komponen dipakai sebagai array: category, amount, effective_from, effective_to (Y-m-d|null), is_active.
 */
class SalaryComponentRules
{
    public const BASE = 'base';
    public const FIXED_ALLOWANCE = 'fixed_allowance';
    public const VARIABLE_ALLOWANCE = 'variable_allowance';
    public const DEDUCTION = 'deduction';

    public const CATEGORIES = [
        self::BASE               => 'Gaji Pokok',
        self::FIXED_ALLOWANCE    => 'Tunjangan Tetap',
        self::VARIABLE_ALLOWANCE => 'Tunjangan Tidak Tetap',
        self::DEDUCTION          => 'Potongan',
    ];

    /** Apakah baris berlaku pada tanggal tertentu (inklusif kedua ujung). */
    public static function isEffectiveOn(array $row, string $date): bool
    {
        if (array_key_exists('is_active', $row) && !$row['is_active']) {
            return false;
        }
        if ($row['effective_from'] > $date) {
            return false;
        }

        return empty($row['effective_to']) || $row['effective_to'] >= $date;
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    public static function effectiveOn(array $rows, string $date): array
    {
        return array_values(array_filter($rows, fn ($r) => self::isEffectiveOn($r, $date)));
    }

    /**
     * Validasi satu baris baru/ubah terhadap baris lain milik karyawan yang sama.
     *
     * @param  array<string,mixed>            $candidate
     * @param  array<int,array<string,mixed>> $others  baris lain karyawan itu (tanpa $candidate)
     * @return string[] pesan galat; kosong = sah
     */
    public static function validate(array $candidate, array $others): array
    {
        $errors = [];

        if (!isset(self::CATEGORIES[$candidate['category'] ?? ''])) {
            $errors[] = 'Unknown salary component category.';
        }
        if ((float) ($candidate['amount'] ?? 0) < 0) {
            $errors[] = 'Amount cannot be negative.';
        }
        if (empty($candidate['effective_from'])) {
            $errors[] = 'Effective date is required.';

            return $errors;
        }
        if (!empty($candidate['effective_to']) && $candidate['effective_to'] < $candidate['effective_from']) {
            $errors[] = 'End date cannot be before the effective date.';
        }

        if (($candidate['category'] ?? null) === self::BASE && ($candidate['is_active'] ?? true)) {
            foreach ($others as $o) {
                if (($o['category'] ?? null) !== self::BASE || !($o['is_active'] ?? true)) {
                    continue;
                }
                if (self::overlaps($candidate, $o)) {
                    $errors[] = 'An active Base Salary already covers part of this period. End it first.';
                    break;
                }
            }
        }

        return $errors;
    }

    /** Dua rentang berlaku saling tumpang tindih? (effective_to kosong = tanpa batas) */
    public static function overlaps(array $a, array $b): bool
    {
        $aEnd = $a['effective_to'] ?: '9999-12-31';
        $bEnd = $b['effective_to'] ?: '9999-12-31';

        return $a['effective_from'] <= $bEnd && $b['effective_from'] <= $aEnd;
    }

    /**
     * Gaji pokok pada tanggal tertentu.
     *
     * @throws InvalidArgumentException bila tidak ada, atau lebih dari satu (data rusak)
     */
    public static function baseSalaryOn(array $rows, string $date): float
    {
        $base = array_values(array_filter(
            self::effectiveOn($rows, $date),
            fn ($r) => $r['category'] === self::BASE
        ));

        if (count($base) !== 1) {
            throw new InvalidArgumentException(
                count($base) === 0 ? 'No active Base Salary on ' . $date : 'More than one active Base Salary on ' . $date
            );
        }

        return round((float) $base[0]['amount'], 2);
    }

    /** Total tiap sisi pada tanggal tertentu: pendapatan, potongan, dasar upah BPJS (komponen bpjs_base), bruto kena pajak. */
    public static function totalsOn(array $rows, string $date): array
    {
        $earn = $ded = $bpjs = $taxable = 0.0;
        foreach (self::effectiveOn($rows, $date) as $r) {
            $amt = (float) $r['amount'];
            if ($r['category'] === self::DEDUCTION) {
                $ded += $amt;
                continue;
            }
            $earn += $amt;
            if ($r['bpjs_base'] ?? true) {
                $bpjs += $amt;
            }
            if ($r['taxable'] ?? true) {
                $taxable += $amt;
            }
        }

        return [
            'earnings'   => round($earn, 2),
            'deductions' => round($ded, 2),
            'bpjs_wage'  => round($bpjs, 2),
            'taxable'    => round($taxable, 2),
        ];
    }
}
