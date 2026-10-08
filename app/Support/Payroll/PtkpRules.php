<?php

namespace App\Support\Payroll;

/**
 * Kode PTKP dan turunannya — MURNI (tanpa database).
 *
 * Kode: TK/0–TK/3 (tidak kawin), K/0–K/3 (kawin). Jumlah tanggungan menempel pada kode, jadi kolom
 * `employee_hr_profile.dependents_count` SELALU diturunkan dari kode (bukan diisi bebas) agar keduanya
 * tidak pernah berselisih. Kategori TER (PMK 168/2023): A = TK/0, TK/1, K/0 · B = TK/2, TK/3, K/1, K/2 · C = K/3.
 */
class PtkpRules
{
    public const CODES = ['TK/0', 'TK/1', 'TK/2', 'TK/3', 'K/0', 'K/1', 'K/2', 'K/3'];

    public const MAX_DEPENDENTS = 3;

    public const TER_CATEGORY = [
        'TK/0' => 'A', 'TK/1' => 'A', 'K/0' => 'A',
        'TK/2' => 'B', 'TK/3' => 'B', 'K/1' => 'B', 'K/2' => 'B',
        'K/3'  => 'C',
    ];

    public static function normalize(?string $code): ?string
    {
        if ($code === null || trim($code) === '') {
            return null;
        }
        $c = strtoupper(str_replace(' ', '', trim($code)));

        return in_array($c, self::CODES, true) ? $c : null;
    }

    public static function dependents(?string $code): ?int
    {
        $c = self::normalize($code);

        return $c === null ? null : (int) substr($c, -1);
    }

    public static function isMarried(?string $code): ?bool
    {
        $c = self::normalize($code);

        return $c === null ? null : str_starts_with($c, 'K/');
    }

    /** Kategori TER A/B/C; null bila kode belum diisi/tidak sah. */
    public static function terCategory(?string $code): ?string
    {
        $c = self::normalize($code);

        return $c === null ? null : self::TER_CATEGORY[$c];
    }

    /** Jumlah tanggungan BPJS Kesehatan tambahan (di luar peserta utama): 0–5, orang tua/mertua/anak ke-4+. */
    public static function normalizeBpjsDependents(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_numeric($value) || (int) $value != $value) {
            return null;
        }
        $n = (int) $value;

        return $n >= 0 && $n <= 5 ? $n : null;
    }
}
