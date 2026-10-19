<?php

namespace App\Support\Offboarding;

/**
 * Aturan MURNI Final Settlement (tanpa database, mudah diuji). Semua tarif/pengali datang dari Register Dasar Hukum (`legal_basis_register.payload`)
 * dan dioper sebagai argumen — kelas ini tidak menyimpan angka hukum sendiri.
 *
 * Komponen (sesuai tampilan ESH): dasar upah · uang pesangon · UPMK · kompensasi PKWT · uang penggantian cuti · uang pisah · hak pensiun
 * (kotor) · risiko penalti PKWT · pajak final pesangon · neto.
 *
 * Catatan perhitungan:
 *  - Masa kerja dari tanggal mulai sampai tanggal efektif; tabel pesangon/UPMK memakai tahun penuh (batas atas tidak termasuk).
 *  - Retirement: pesangon + UPMK (kotor) dikurangi perkiraan manfaat pensiun (offset, tidak di bawah nol).
 *  - Pajak final progresif atas pesangon + UPMK (setelah offset) + uang pisah; kompensasi PKWT dan penggantian cuti tidak dimasukkan (perlu konfirmasi Tax).
 *  - Risiko penalti PKWT = upah × sisa bulan kontrak, hanya informasi (tidak masuk neto).
 */
class OffboardingSettlementRules
{
    public const TYPES = [
        'resignation'        => 'Resignation',
        'end_of_pkwt'        => 'End of PKWT',
        'retirement'         => 'Retirement',
        'termination'        => 'Termination (PHK)',
        'mutual_agreement'   => 'Mutual agreement',
        'death'              => 'Death',
        'end_of_engagement'  => 'End of engagement',
    ];

    /** Jenis yang boleh dipilih menurut jenis karyawan. */
    public const TYPES_BY_KIND = [
        'Internal' => ['resignation', 'end_of_pkwt', 'retirement', 'termination', 'mutual_agreement', 'death'],
        'External' => ['end_of_engagement', 'termination', 'mutual_agreement'],
    ];

    /** Alasan (kunci register) untuk jenis PHK. */
    public const TERMINATION_REASONS = ['termination_misconduct' => 'Breach of the employment agreement', 'termination_efficiency' => 'Efficiency'];

    /** Kunci alasan di register menurut jenis (PHK memilih sendiri). */
    public static function reasonFor(string $type, ?string $chosen): ?string
    {
        return match ($type) {
            'resignation' => 'resignation', 'end_of_pkwt' => 'end_of_pkwt', 'retirement' => 'retirement', 'death' => 'death',
            'mutual_agreement' => 'mutual_agreement',
            'termination' => isset(self::TERMINATION_REASONS[$chosen ?? '']) ? $chosen : null,
            default => null,
        };
    }

    /** Masa kerja dalam tahun (desimal) dan bulan penuh. @return array{years:float,months:int} */
    public static function service(string $start, string $end): array
    {
        $a = new \DateTimeImmutable(substr($start, 0, 10));
        $b = new \DateTimeImmutable(substr($end, 0, 10));
        if ($b <= $a) {
            return ['years' => 0.0, 'months' => 0];
        }
        $diff = $a->diff($b);
        $months = $diff->y * 12 + $diff->m;

        return ['years' => round($months / 12 + $diff->d / 365, 4), 'months' => $months];
    }

    /**
     * Pengali dari tabel [[batas atas (tahun, tidak termasuk) | null, pengali], …] urut naik; baris terakhir batas null.
     *
     * @param  array<int,array{0:int|float|null,1:int|float}>  $table
     */
    public static function tableMultiplier(array $table, float $years): float
    {
        foreach ($table as [$upper, $multiplier]) {
            if ($upper === null || $years < (float) $upper) {
                return (float) $multiplier;
            }
        }

        return 0.0;
    }

    /** Kompensasi PKWT = upah × bulan masa kerja ÷ 12 (proporsional). Tanpa masa kerja → 0. */
    public static function pkwtCompensation(float $wage, int $serviceMonths, int $monthsPerYear = 12): float
    {
        return $serviceMonths < 1 ? 0.0 : round($wage * $serviceMonths / max(1, $monthsPerYear), 2);
    }

    /**
     * Pajak progresif final: tiap lapisan [[batas atas | null, tarif], …] dikenakan pada bagian penghasilan yang jatuh di lapisan itu.
     *
     * @param  array<int,array{up_to:int|float|null,rate:float}>  $brackets
     */
    public static function progressiveTax(float $taxable, array $brackets): float
    {
        $tax = 0.0;
        $lower = 0.0;
        foreach ($brackets as $b) {
            $upper = $b['up_to'] === null ? INF : (float) $b['up_to'];
            if ($taxable > $lower) {
                $tax += (min($taxable, $upper) - $lower) * (float) $b['rate'];
            }
            if ($taxable <= $upper) {
                break;
            }
            $lower = $upper;
        }

        return round($tax, 2);
    }

    /**
     * Sisa bulan kontrak PKWT (pembulatan ke atas per 30 hari) bila kontrak berakhir SEBELUM tanggal kontrak habis; selain itu 0.
     */
    public static function remainingMonths(string $effective, ?string $contractEnd): int
    {
        if ($contractEnd === null || substr($contractEnd, 0, 10) <= substr($effective, 0, 10)) {
            return 0;
        }
        $days = (int) ((strtotime(substr($contractEnd, 0, 10)) - strtotime(substr($effective, 0, 10))) / 86400);

        return (int) ceil($days / 30);
    }

    /**
     * Hitung seluruh komponen.
     *
     * @param  array{wage:float,service_start:string,effective:string,contract_type:?string,contract_end:?string,leave_days:float,leave_divisor:float,
     *               retirement_benefit:float,separation_pay:float,other_compensation:float}  $in
     * @param  array{reason:array,severance:array,service_award:array,tax:array,pkwt_months_per_year:int,type:string}  $rules  dari register
     * @return array<string,mixed>
     */
    public static function calculate(array $in, array $rules): array
    {
        $wage = round(max(0.0, (float) $in['wage']), 2);
        $svc = self::service($in['service_start'], $in['effective']);
        $reason = $rules['reason'];
        $type = $rules['type'];

        $sevMult = (float) ($reason['severance'] ?? 0) > 0 ? self::tableMultiplier($rules['severance'], $svc['years']) * (float) $reason['severance'] : 0.0;
        $awardMult = (float) ($reason['service_award'] ?? 0) > 0 ? self::tableMultiplier($rules['service_award'], $svc['years']) * (float) $reason['service_award'] : 0.0;

        $severance = round($wage * $sevMult, 2);
        $award = round($wage * $awardMult, 2);
        $gross = round($severance + $award, 2);                          // hak kotor sebelum offset manfaat pensiun

        $offset = $type === 'retirement' ? min($gross, max(0.0, (float) $in['retirement_benefit'])) : 0.0;
        $payable = round($gross - $offset, 2);
        $payableSeverance = $gross > 0 ? round($severance * $payable / $gross, 2) : 0.0;
        $payableAward = round($payable - $payableSeverance, 2);

        $pkwt = !empty($reason['pkwt_compensation']) && strtoupper((string) $in['contract_type']) === 'PKWT'
            ? self::pkwtCompensation($wage, $svc['months'], (int) $rules['pkwt_months_per_year']) : 0.0;

        $leave = !empty($reason['leave']) && (float) $in['leave_days'] > 0 && (float) $in['leave_divisor'] > 0
            ? round((float) $in['leave_days'] * $wage / (float) $in['leave_divisor'], 2) : 0.0;

        $separation = round(max(0.0, (float) $in['separation_pay']), 2);
        $other = round(max(0.0, (float) $in['other_compensation']), 2);

        $taxBase = round($payable + $separation, 2);
        $tax = self::progressiveTax($taxBase, $rules['tax']);

        $penaltyMonths = strtoupper((string) $in['contract_type']) === 'PKWT' && !in_array($type, ['end_of_pkwt', 'resignation'], true)
            ? self::remainingMonths($in['effective'], $in['contract_end'] ?? null) : 0;
        $penalty = round($wage * $penaltyMonths, 2);

        $net = round($pkwt + $payable + $leave + $separation + $other - $tax, 2);

        return [
            'wage_base' => $wage, 'service_years' => $svc['years'], 'service_months' => $svc['months'],
            'severance_multiplier' => round($sevMult, 2), 'service_award_multiplier' => round($awardMult, 2),
            'severance' => $payableSeverance, 'service_award' => $payableAward, 'gross_entitlement' => $gross, 'retirement_offset' => $offset,
            'pkwt_compensation' => $pkwt, 'leave_settlement' => $leave, 'separation_pay' => $separation, 'other_compensation' => $other,
            'taxable_base' => $taxBase, 'severance_tax' => $tax, 'pkwt_penalty_months' => $penaltyMonths, 'pkwt_penalty_risk' => $penalty,
            'net_settlement' => $net,
        ];
    }
}
