<?php

namespace App\Support\DataTransfer;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Pembacaan nilai sel spreadsheet. Setiap method melempar
 * InvalidArgumentException dengan pesan yang langsung layak ditampilkan.
 */
class Cell
{
    public static function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    public static function yesNo(mixed $value, bool $default = false): bool
    {
        $text = strtolower((string) self::text($value));

        if ($text === '') {
            return $default;
        }

        if (in_array($text, ['yes', 'y', '1', 'true', 'ya', 'active'], true)) {
            return true;
        }

        if (in_array($text, ['no', 'n', '0', 'false', 'tidak', 'inactive'], true)) {
            return false;
        }

        throw new \InvalidArgumentException('use Yes or No');
    }

    public static function number(mixed $value): ?float
    {
        $text = self::text($value);

        if ($text === null) {
            return null;
        }

        $text = preg_replace('/[^\d,.\-]/', '', $text);

        // "1.250.000,50" (ID) and "1,250,000.50" (EN) both end up as 1250000.50.
        if (preg_match('/,\d{1,2}$/', $text) && str_contains($text, '.')) {
            $text = str_replace(['.', ','], ['', '.'], $text);
        } elseif (substr_count($text, '.') > 1 || (str_contains($text, ',') && str_contains($text, '.'))) {
            $text = str_replace(',', '', $text);
        } elseif (substr_count($text, ',') === 1 && !str_contains($text, '.')) {
            $text = str_replace(',', '.', $text);
        }

        if (!is_numeric($text)) {
            throw new \InvalidArgumentException('must be a number');
        }

        return (float) $text;
    }

    public static function date(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::instance(Date::excelToDateTimeObject((float) $value))->toDateString();
            }

            $text = trim((string) $value);

            foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'd M Y', 'd F Y'] as $format) {
                $parsed = Carbon::createFromFormat('!' . $format, $text);

                if ($parsed && $parsed->format($format) === $text) {
                    return $parsed->toDateString();
                }
            }
        } catch (\Throwable) {
            // falls through to the error below
        }

        throw new \InvalidArgumentException('use a date such as 2026-10-31');
    }

    public static function time(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value) && (float) $value >= 0 && (float) $value < 1) {
            $minutes = (int) round((float) $value * 24 * 60);

            return sprintf('%02d:%02d', intdiv($minutes, 60) % 24, $minutes % 60);
        }

        if (preg_match('/^(\d{1,2})[:.](\d{2})(?::\d{2})?$/', trim((string) $value), $m)
            && (int) $m[1] < 24 && (int) $m[2] < 60) {

            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        throw new \InvalidArgumentException('use a time such as 08:30');
    }

    /**
     * Cocokkan sel dengan nilai ATAU label sebuah daftar pilihan.
     *
     * @param  array<string, string>  $choices  [value => label]
     */
    public static function choice(mixed $value, array $choices, string $what): ?string
    {
        $text = self::text($value);

        if ($text === null) {
            return null;
        }

        foreach ($choices as $key => $label) {
            if (strcasecmp((string) $key, $text) === 0 || strcasecmp((string) $label, $text) === 0) {
                return (string) $key;
            }
        }

        throw new \InvalidArgumentException("\"{$text}\" is not an available {$what}. Choose one of: " . implode(', ', $choices));
    }
}
