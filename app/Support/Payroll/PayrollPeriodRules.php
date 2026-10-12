<?php

namespace App\Support\Payroll;

use DateTimeImmutable;

/**
 * Aturan periode payroll — MURNI (tanpa database).
 *
 * Alur mengikuti aplikasi acuan ESH:
 *   periode : open ──Lock──▶ locked
 *   slip    : calculated ──Generate Slip──▶ generated   (per karyawan; lihat PayrollPeriodService)
 * Hanya periode `open` yang boleh dihitung ulang, diubah koreksinya/saklarnya, atau dihapus. Generate Slip boleh pada
 * periode open maupun locked (slip adalah dokumen, bukan perubahan angka).
 *
 * Periode absensi: rentang data absensi/cuti/lembur yang dipakai (bawaan = periode gaji). Cut-off: tanggal terakhir data
 * absensi yang DIPERCAYA; hari kerja sesudahnya dianggap penuh dan dikoreksi di payroll berikutnya.
 */
class PayrollPeriodRules
{
    public const OPEN = 'open';
    public const LOCKED = 'locked';

    public const STATUSES = [
        self::OPEN   => 'Open',
        self::LOCKED => 'Locked',
    ];

    private const TRANSITIONS = [
        self::OPEN   => [self::LOCKED],
        self::LOCKED => [],
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
     * @param array<string,mixed>            $in     name, period_start, period_end, pay_date, [attendance_start, attendance_end, cutoff_date]
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

        // Periode absensi (opsional): keduanya diisi atau keduanya kosong, urut, dan tidak terlalu panjang.
        $aS = (string) ($in['attendance_start'] ?? '');
        $aE = (string) ($in['attendance_end'] ?? '');
        if ($aS !== '' || $aE !== '') {
            $a1 = self::date($aS);
            $a2 = self::date($aE);
            if (!$a1 || !$a2) {
                $errors[] = 'Enter both attendance period dates, or leave both empty.';
            } elseif ($a2 < $a1) {
                $errors[] = 'The attendance period end cannot be before its start.';
            } elseif ($a1->diff($a2)->days + 1 > self::MAX_PERIOD_DAYS) {
                $errors[] = 'The attendance period cannot be longer than ' . self::MAX_PERIOD_DAYS . ' days.';
            }
        }

        $cut = (string) ($in['cutoff_date'] ?? '');
        if ($cut !== '') {
            $c = self::date($cut);
            $from = $aS !== '' ? $aS : (string) ($in['period_start'] ?? '');
            $to   = $aE !== '' ? $aE : (string) ($in['period_end'] ?? '');
            if (!$c) {
                $errors[] = 'Enter a valid cut-off date.';
            } elseif ($from !== '' && $to !== '' && ($cut < $from || $cut > $to)) {
                $errors[] = 'The cut-off date must fall inside the attendance period (' . $from . ' – ' . $to . ').';
            }
        }

        return $errors;
    }

    /**
     * Rentang data absensi yang dipakai: [start, end] = periode absensi (bawaan periode gaji), dipotong cut-off.
     *
     * @param array<string,mixed> $period period_start, period_end, attendance_start?, attendance_end?, cutoff_date?
     * @return array{start:string,end:string,trusted_end:string,cutoff:?string}
     */
    public static function attendanceWindow(array $period): array
    {
        $start = (string) (($period['attendance_start'] ?? null) ?: $period['period_start']);
        $end   = (string) (($period['attendance_end'] ?? null) ?: $period['period_end']);
        $cut   = ($period['cutoff_date'] ?? null) ? (string) $period['cutoff_date'] : null;
        $trusted = ($cut !== null && $cut < $end) ? max($cut, $start) : $end;

        return ['start' => $start, 'end' => $end, 'trusted_end' => $trusted, 'cutoff' => $cut];
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
