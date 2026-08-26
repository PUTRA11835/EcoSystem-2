<?php

namespace App\Services\Overtime;

use InvalidArgumentException;

/**
 * Perhitungan pengali lembur menurut PP 35/2021.
 *
 * 🔴 SERVICE INI MURNI: tidak menyentuh database, tidak membaca sesi, tidak
 * memanggil service lain. Tarif per jam DITERIMA SEBAGAI PARAMETER, bukan
 * dicari sendiri. Karena itu ia dapat diuji sepenuhnya tanpa data gaji —
 * meniru GeofenceService yang sudah terbukti dengan 19 tes.
 *
 * KENAPA DIBANGUN SEKARANG PADAHAL PAYROLL BELUM ADA:
 * Basis data ini belum punya satu pun tabel gaji, sehingga nominal rupiah belum
 * dapat dihitung. Tetapi yang belum ada hanyalah ANGKA UPAHNYA — ATURAN
 * pengalinya sudah ditetapkan peraturan dan tidak menunggu apa pun. Membangunnya
 * sekarang berarti saat payroll tersedia nanti, penyambungannya cukup mengisi
 * satu parameter.
 *
 * RUMUS UPAH SEJAM (Pasal 78 UU Ketenagakerjaan jo. PP 35/2021):
 *
 *     upah sejam = upah sebulan / 173
 *
 * PENGALI:
 *
 *   Hari kerja
 *     jam ke-1        1,5 x
 *     jam ke-2 dst    2   x
 *
 *   Hari istirahat mingguan / libur resmi, pola 5 hari kerja seminggu
 *     jam ke-1  s/d 8   2 x
 *     jam ke-9          3 x
 *     jam ke-10 dst     4 x
 *
 *   Pola 6 hari kerja seminggu menggeser batasnya satu jam lebih awal
 *     jam ke-1  s/d 7   2 x
 *     jam ke-8          3 x
 *     jam ke-9  dst     4 x
 *
 * Perhitungan dilakukan pada tingkat MENIT lalu dibagi 60, bukan dibulatkan ke
 * jam penuh. Membulatkan ke bawah akan memangkas hak karyawan; membulatkan ke
 * atas membebani perusahaan. Keduanya keputusan kebijakan, dan tidak satu pun
 * diminta — jadi angkanya dibiarkan apa adanya.
 */
class OvertimeRateService
{
    public const DAY_WORKDAY        = 'workday';
    public const DAY_WEEKEND        = 'weekend';
    public const DAY_PUBLIC_HOLIDAY = 'public_holiday';

    /** Pembagi baku upah sebulan menjadi upah sejam. */
    public const MONTHLY_DIVISOR = 173;

    private const MINUTES_PER_HOUR = 60;

    /**
     * Rincian pengali untuk satu pengajuan lembur.
     *
     * @param  int         $durationMinutes  Durasi lembur dalam menit.
     * @param  string      $dayType          Salah satu konstanta DAY_*.
     * @param  float|null  $hourlyRate       Upah sejam. NULL bila belum diketahui.
     * @param  int         $workDaysPerWeek  5 atau 6. Menggeser batas pengali hari libur.
     *
     * @return array{
     *     duration_minutes: int,
     *     day_type: string,
     *     segments: array<int, array{label: string, minutes: int, hours: float, multiplier: float, weighted_hours: float, amount: float|null}>,
     *     total_hours: float,
     *     weighted_hours: float,
     *     hourly_rate: float|null,
     *     amount: float|null
     * }
     */
    public function calculate(
        int $durationMinutes,
        string $dayType,
        ?float $hourlyRate = null,
        int $workDaysPerWeek = 5
    ): array {
        if (!in_array($dayType, self::dayTypes(), true)) {
            throw new InvalidArgumentException("Unknown overtime day type: {$dayType}");
        }

        if ($hourlyRate !== null && $hourlyRate < 0) {
            throw new InvalidArgumentException('Hourly rate cannot be negative.');
        }

        $minutes  = max(0, $durationMinutes);
        $segments = [];
        $weighted = 0.0;

        // Sisa menit dipotong band demi band. Band terakhir selalu tak terbatas,
        // sehingga berapa pun durasinya selalu habis terbagi.
        $remaining = $minutes;

        foreach ($this->bands($dayType, $workDaysPerWeek) as $band) {
            if ($remaining <= 0) {
                break;
            }

            $take = $band['minutes'] === null
                ? $remaining
                : min($remaining, $band['minutes']);

            $hours         = $take / self::MINUTES_PER_HOUR;
            $weightedHours = $hours * $band['multiplier'];
            $weighted     += $weightedHours;

            $segments[] = [
                'label'          => $band['label'],
                'minutes'        => $take,
                'hours'          => round($hours, 4),
                'multiplier'     => $band['multiplier'],
                'weighted_hours' => round($weightedHours, 4),
                'amount'         => $hourlyRate === null ? null : round($weightedHours * $hourlyRate, 2),
            ];

            $remaining -= $take;
        }

        return [
            'duration_minutes' => $minutes,
            'day_type'         => $dayType,
            'segments'         => $segments,
            'total_hours'      => round($minutes / self::MINUTES_PER_HOUR, 4),
            'weighted_hours'   => round($weighted, 4),
            'hourly_rate'      => $hourlyRate,
            'amount'           => $hourlyRate === null ? null : round($weighted * $hourlyRate, 2),
        ];
    }

    /**
     * Upah sejam dari upah sebulan.
     *
     * Disediakan terpisah supaya saat modul Payroll ada nanti, pemanggilnya
     * cukup meneruskan angka upah bulanan tanpa mengulang pembagi 173 di
     * beberapa tempat.
     */
    public function hourlyRateFromMonthly(float $monthlyWage): float
    {
        if ($monthlyWage < 0) {
            throw new InvalidArgumentException('Monthly wage cannot be negative.');
        }

        return $monthlyWage / self::MONTHLY_DIVISOR;
    }

    /** @return array<int, string> */
    public static function dayTypes(): array
    {
        return [self::DAY_WORKDAY, self::DAY_WEEKEND, self::DAY_PUBLIC_HOLIDAY];
    }

    /**
     * Band pengali yang berlaku, berurutan dari jam paling awal.
     *
     * `minutes => null` berarti band terakhir tanpa batas atas.
     *
     * @return array<int, array{label: string, minutes: int|null, multiplier: float}>
     */
    private function bands(string $dayType, int $workDaysPerWeek): array
    {
        if ($dayType === self::DAY_WORKDAY) {
            return [
                ['label' => 'First hour',       'minutes' => 60,   'multiplier' => 1.5],
                ['label' => 'Subsequent hours', 'minutes' => null, 'multiplier' => 2.0],
            ];
        }

        // Hari libur: batas bergeser menurut pola hari kerja perusahaan.
        $normalHours = $workDaysPerWeek >= 6 ? 7 : 8;

        return [
            ['label' => "First {$normalHours} hours", 'minutes' => $normalHours * 60, 'multiplier' => 2.0],
            ['label' => 'Next hour',                  'minutes' => 60,                'multiplier' => 3.0],
            ['label' => 'Remaining hours',            'minutes' => null,              'multiplier' => 4.0],
        ];
    }
}
