<?php

namespace App\Support\Payroll;

use DateTimeImmutable;

/**
 * Aturan periode payroll — MURNI (tanpa database).
 *
 * Alur status (satu arah, kecuali Reopen sebelum dibayar):
 *   open ──approve──▶ approved ──paid──▶ paid ──lock──▶ locked
 *            ◀─reopen──┘
 * Hanya periode `open` yang boleh dihitung ulang, diubah penyesuaiannya, atau dihapus.
 */
class PayrollPeriodRules
{
    public const OPEN = 'open';
    public const APPROVED = 'approved';
    public const PAID = 'paid';
    public const LOCKED = 'locked';

    public const STATUSES = [
        self::OPEN     => 'Open',
        self::APPROVED => 'Approved',
        self::PAID     => 'Paid',
        self::LOCKED   => 'Locked',
    ];

    private const TRANSITIONS = [
        self::OPEN     => [self::APPROVED],
        self::APPROVED => [self::OPEN, self::PAID],
        self::PAID     => [self::LOCKED],
        self::LOCKED   => [],
    ];

    public const MAX_PERIOD_DAYS = 62;

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public static function isEditable(string $status): bool
    {
        return $status === self::OPEN;
    }

    /**
     * @param array<string,mixed>            $in     name, period_start, period_end, pay_date
     * @param array<int,array<string,mixed>> $others periode lain (name, period_start, period_end)
     * @return string[] galat; kosong = sah
     */
    public static function validate(array $in, array $others): array
    {
        $errors = [];
        $name = trim((string) ($in['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            $errors[] = 'Enter a period name (maximum 100 characters).';
        }

        $start = self::date($in['period_start'] ?? null);
        $end   = self::date($in['period_end'] ?? null);
        $pay   = self::date($in['pay_date'] ?? null);
        if (!$start) { $errors[] = 'Enter a valid period start date.'; }
        if (!$end)   { $errors[] = 'Enter a valid period end date.'; }
        if (!$pay)   { $errors[] = 'Enter a valid pay date.'; }

        if ($start && $end) {
            if ($end < $start) {
                $errors[] = 'The period end date cannot be before the start date.';
            } elseif ($start->diff($end)->days + 1 > self::MAX_PERIOD_DAYS) {
                $errors[] = 'A payroll period cannot be longer than ' . self::MAX_PERIOD_DAYS . ' days.';
            } else {
                foreach ($others as $o) {
                    if ($in['period_start'] <= $o['period_end'] && $o['period_start'] <= $in['period_end']) {
                        $errors[] = 'This period overlaps "' . $o['name'] . '" (' . $o['period_start'] . ' – ' . $o['period_end'] . '). Periods cannot overlap, otherwise overtime and tax would be counted twice.';
                        break;
                    }
                }
            }
        }

        return $errors;
    }

    public static function taxYear(string $periodEnd): int
    {
        return (int) substr($periodEnd, 0, 4);
    }

    /** Masa pajak terakhir: bulan akhir periode = bulan final (Desember) → true-up setahun. */
    public static function isFinalTaxPeriod(string $periodEnd, int $finalMonth = 12): bool
    {
        return (int) substr($periodEnd, 5, 2) === $finalMonth;
    }

    public static function daysInPeriod(string $start, string $end): int
    {
        return (int) (new DateTimeImmutable($start))->diff(new DateTimeImmutable($end))->days + 1;
    }

    private static function date(mixed $v): ?DateTimeImmutable
    {
        if (!is_string($v)) {
            return null;
        }
        $d = DateTimeImmutable::createFromFormat('Y-m-d', $v);

        return $d && $d->format('Y-m-d') === $v ? $d : null;
    }
}
