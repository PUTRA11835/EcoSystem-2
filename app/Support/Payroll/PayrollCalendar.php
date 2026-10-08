<?php

namespace App\Support\Payroll;

use DateTimeImmutable;

/**
 * Kalender kerja payroll — MURNI (libur diberikan pemanggil dari tabel `holidays`).
 * Hari kerja = Senin–Jumat (jadwal 5 hari) atau Senin–Sabtu (6 hari), dikurangi hari libur.
 */
class PayrollCalendar
{
    /**
     * Daftar tanggal hari kerja pada rentang [start, end] (inklusif).
     *
     * @param string[] $holidays tanggal libur Y-m-d
     * @return string[] Y-m-d
     */
    public static function workdays(string $start, string $end, int $workDaysPerWeek, array $holidays = []): array
    {
        $skip = array_flip($holidays);
        $last = $workDaysPerWeek === 6 ? 6 : 5;          // ISO: 1 = Senin … 7 = Minggu
        $out = [];
        for ($d = new DateTimeImmutable($start), $e = new DateTimeImmutable($end); $d <= $e; $d = $d->modify('+1 day')) {
            $iso = (int) $d->format('N');
            $key = $d->format('Y-m-d');
            if ($iso <= $last && !isset($skip[$key])) {
                $out[] = $key;
            }
        }

        return $out;
    }

    /** Jumlah hari kerja dari $workdays yang jatuh pada [from, to]. @param string[] $workdays */
    public static function countWithin(array $workdays, string $from, string $to): int
    {
        $n = 0;
        foreach ($workdays as $d) {
            if ($d >= $from && $d <= $to) {
                $n++;
            }
        }

        return $n;
    }
}
