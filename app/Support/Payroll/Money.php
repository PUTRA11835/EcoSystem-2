<?php

namespace App\Support\Payroll;

/**
 * Format uang modul keuangan (Payroll, BPJS, PPh 21) — MURNI. Satu aturan untuk seluruh tampilan:
 * titik sebagai pemisah ribuan, koma sebagai desimal, selalu dua digit: 1.000.000,00.
 *
 * `parse()` menerima apa yang mungkin dikirim formulir: format Indonesia ("1.000.000,00"), angka polos
 * ("1000000" / "1000000.50"), atau angka PHP. Kembalian null = bukan angka sah (pemanggil menolaknya).
 */
class Money
{
    public static function format(float|int|string|null $amount, int $decimals = 2): string
    {
        if ($amount === null || $amount === '') {
            return '';
        }

        return number_format((float) $amount, $decimals, ',', '.');
    }

    /** "Rp 1.000.000,00" */
    public static function rupiah(float|int|string|null $amount, int $decimals = 2): string
    {
        return 'Rp ' . self::format($amount ?? 0, $decimals);
    }

    public static function parse(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        $s = trim(str_replace(['Rp', 'rp', ' ', "\u{00a0}"], '', (string) $value));
        if ($s === '' || $s === '-') {
            return null;
        }

        if (str_contains($s, ',')) {
            // Format Indonesia: titik = ribuan, koma = desimal.
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $s)) {
            // "1.000.000" tanpa koma = ribuan.
            $s = str_replace('.', '', $s);
        }

        return preg_match('/^-?\d+(\.\d+)?$/', $s) ? (float) $s : null;
    }
}
