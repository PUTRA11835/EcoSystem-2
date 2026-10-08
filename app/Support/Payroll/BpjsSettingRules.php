<?php

namespace App\Support\Payroll;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Aturan pengaturan BPJS berefektif-tanggal — MURNI (tanpa database).
 */
class BpjsSettingRules
{
    public const RATE_FIELDS = [
        'health_employer_rate' => 'BPJS Health employer rate',
        'health_employee_rate' => 'BPJS Health employee rate',
        'jht_employer_rate'    => 'JHT employer rate',
        'jht_employee_rate'    => 'JHT employee rate',
        'jp_employer_rate'     => 'JP employer rate',
        'jp_employee_rate'     => 'JP employee rate',
        'jkk_rate'             => 'JKK rate',
        'jkm_rate'             => 'JKM rate',
    ];

    public const AMOUNT_FIELDS = [
        'health_min_base' => 'Minimum Health BPJS base',
        'health_cap'      => 'Health BPJS cap',
        'jp_cap'          => 'JP cap',
    ];

    /**
     * Versi yang berlaku pada $date: yang `effective_date`-nya paling akhir tetapi tidak melewati $date.
     *
     * @param array<int,array<string,mixed>> $settings
     * @return array<string,mixed>
     * @throws InvalidArgumentException bila belum ada versi yang berlaku pada tanggal itu
     */
    public static function effectiveOn(array $settings, string $date): array
    {
        $best = null;
        foreach ($settings as $s) {
            if ($s['effective_date'] <= $date && ($best === null || $s['effective_date'] > $best['effective_date'])) {
                $best = $s;
            }
        }
        if ($best === null) {
            throw new InvalidArgumentException("No BPJS setting is effective on {$date}.");
        }

        return $best;
    }

    /**
     * @param array<string,mixed> $in            isian (angka sudah di-parse; nilai tak sah dibiarkan agar ditolak)
     * @param string[]            $existingDates tanggal berlaku yang sudah ada
     * @return string[] galat; kosong = sah
     */
    public static function validate(array $in, array $existingDates): array
    {
        $errors = [];

        $date = (string) ($in['effective_date'] ?? '');
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (!$d || $d->format('Y-m-d') !== $date) {
            $errors[] = 'Enter a valid effective date.';
        } elseif (in_array($date, $existingDates, true)) {
            $errors[] = 'A setting with this effective date already exists. Choose a new effective date — earlier versions are never changed.';
        }

        foreach (self::RATE_FIELDS as $key => $label) {
            $v = $in[$key] ?? null;
            if (!is_numeric($v) || $v < 0 || $v > 100) {
                $errors[] = "{$label} must be between 0 and 100.";
            }
        }
        foreach (self::AMOUNT_FIELDS as $key => $label) {
            $v = $in[$key] ?? null;
            if (!is_numeric($v) || $v < 0) {
                $errors[] = "{$label} must be zero or more.";
            }
        }

        if (!$errors) {
            if ((float) $in['health_cap'] <= 0) {
                $errors[] = 'Health BPJS cap must be more than zero.';
            }
            if ((float) $in['jp_cap'] <= 0) {
                $errors[] = 'JP cap must be more than zero.';
            }
            if ((float) $in['health_min_base'] > (float) $in['health_cap']) {
                $errors[] = 'The minimum Health BPJS base cannot be higher than the cap.';
            }
            // Cacat aplikasi acuan: JP 0% / 0% muncul di riwayat tanpa penjelasan.
            if (((float) $in['jp_employer_rate'] == 0.0 || (float) $in['jp_employee_rate'] == 0.0)
                && trim((string) ($in['notes'] ?? '')) === '') {
                $errors[] = 'A JP rate of 0% needs a note explaining why.';
            }
        }

        return $errors;
    }
}
