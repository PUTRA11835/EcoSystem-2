<?php

namespace App\Support\Payroll;

use App\Services\Overtime\OvertimeRateService;
use InvalidArgumentException;

/**
 * Perhitungan slip gaji SATU karyawan untuk SATU periode — MURNI (tanpa database; semua masukan diberikan pemanggil).
 *
 * Urutan: komponen gaji (prorata bila hanya sebagian periode) → lembur disetujui → reimbursement → penyesuaian manual →
 * potongan absensi (terlambat, tidak hadir) → BPJS (dasar upah = komponen ber-`bpjs_base` penuh sebulan) →
 * PPh 21 (TER bulanan, atau true-up setahun pada masa pajak terakhir) → gaji bersih (THP).
 *
 * Masukan `$in`:
 *   start, end                 Y-m-d
 *   components                 baris komponen (SalaryComponentRules)
 *   overtime                   [['id','date','duration_minutes','day_type']] — hanya yang sudah disetujui
 *   reimbursements             [['id','title','amount']] — sudah disetujui, belum dibayar lewat payroll
 *   adjustments                [['kind'=>'earning|deduction','name','amount','taxable']]
 *   attendance                 null atau ['deductible_days'=>float,'late_minutes_chargeable'=>int] (sudah dihitung pemanggil)
 *   workdays                   tanggal hari kerja periode (PayrollCalendar::workdays) — dasar pembagi & prorata
 *   employee                   ptkp_code, bpjs_health_active, bpjs_employment_active, bpjs_dependents, has_tax_id
 *   settings                   kebijakan (payroll_settings): lihat PayrollEngine::settings()
 *   bpjs                       satu baris bpjs_settings yang berlaku
 *   pph21                      ['ptkp'=>[kode=>setahun],'ter'=>[A,B,C],'brackets'=>[…],'settings'=>[…]]
 *   final_tax_period           bool — true = true-up setahun
 *   ytd                        gross, employee_pension, pph21_withheld, months (masa pajak SEBELUM periode ini, tahun sama)
 *
 * Keluaran: items (baris slip), ringkasan, rincian BPJS & PPh 21, warnings. Data kurang lengkap TIDAK melempar:
 * slip tetap dihitung semampunya dan pemanggil menampilkan peringatan.
 */
class PayrollCalculator
{
    public const T_EARNING = 'earning';
    public const T_DEDUCTION = 'deduction';
    public const T_EMPLOYER = 'employer';   // iuran perusahaan: informasi, tidak mengurangi THP

    /** @return array<string,mixed> */
    public static function calculate(array $in): array
    {
        $start = (string) $in['start'];
        $end   = (string) $in['end'];
        if ($end < $start) {
            throw new InvalidArgumentException('Period end is before period start.');
        }

        $set      = (array) ($in['settings'] ?? []);
        $warnings = [];
        $items    = [];
        $days     = PayrollPeriodRules::daysInPeriod($start, $end);
        $prorate  = !empty($set['prorate_partial_period']);
        $fixed    = ($set['proration_basis'] ?? 'calendar') === 'fixed_divisor';
        $divisor  = max(1, (int) ($set['fixed_divisor'] ?? 21));
        $workdays = (array) ($in['workdays'] ?? []);
        $usesWorkdays = $fixed && $workdays !== [];

        // ── 1. Komponen gaji ────────────────────────────────────────────────
        $rows = array_values(array_filter((array) $in['components'], fn ($r) => !array_key_exists('is_active', $r) || $r['is_active']));
        $hasBase = false;
        $baseCover = [];   // [dari, sampai, hari kalender tercakup] tiap baris Gaji Pokok — untuk "hari dibayar"
        foreach ($rows as $r) {
            $from = max($start, (string) $r['effective_from']);
            $to   = empty($r['effective_to']) ? $end : min($end, (string) $r['effective_to']);
            if ($from > $to) {
                continue;
            }
            $covered = PayrollPeriodRules::daysInPeriod($from, $to);
            $label   = (string) $r['name'];
            if (!$prorate || $covered === $days) {
                $factor = 1.0;
            } elseif ($usesWorkdays) {
                $coveredWork = PayrollCalendar::countWithin($workdays, $from, $to);
                $factor = min(1.0, $coveredWork / $divisor);
                $label .= " ({$coveredWork}/{$divisor} days)";
            } else {
                $factor = $covered / $days;
                $label .= " ({$covered}/{$days} days)";
            }
            $amount = round((float) $r['amount'] * $factor, 2);
            $isDed  = $r['category'] === SalaryComponentRules::DEDUCTION;
            if ($r['category'] === SalaryComponentRules::BASE) {
                $hasBase = true;
                $baseCover[] = [$from, $to, $covered];
            }
            $items[] = [
                'type' => $isDed ? self::T_DEDUCTION : self::T_EARNING, 'code' => $r['category'], 'name' => $label,
                'amount' => $amount, 'taxable' => $isDed ? false : (bool) ($r['taxable'] ?? true),
                'source_type' => 'salary_component', 'source_id' => $r['id'] ?? null,
            ];
        }
        if (!$hasBase) {
            $warnings[] = 'No Base Salary covers this period.';
        }

        // Hari dibayar: dari baris Gaji Pokok yang paling banyak mencakup periode. Pembagi tetap → hari kerja ÷ pembagi
        // (periode penuh = pembagi); kalender → hari kalender ÷ hari periode.
        $paidDays = 0.0;
        $basisDays = $fixed ? (float) $divisor : (float) $days;
        if ($baseCover) {
            usort($baseCover, fn ($a, $b) => $b[2] <=> $a[2]);
            [$bf, $bt, $bc] = $baseCover[0];
            if (!$prorate || $bc === $days) {
                $paidDays = $basisDays;
            } elseif ($usesWorkdays) {
                $paidDays = (float) min($divisor, PayrollCalendar::countWithin($workdays, $bf, $bt));
            } else {
                $paidDays = (float) $bc;
            }
        }

        // ── 2. Dasar upah (penuh sebulan): BPJS dan tarif lembur/denda ────────
        $wageDate = SalaryComponentRules::effectiveOn($rows, $end) ? $end : $start;
        $tot      = SalaryComponentRules::totalsOn($rows, $wageDate);
        $bpjsWage = $tot['bpjs_wage'];
        $rateWage = 0.0;   // gaji pokok + tunjangan tetap: dasar upah sejam, potongan harian, dan denda per menit
        foreach (SalaryComponentRules::effectiveOn($rows, $wageDate) as $r) {
            if (in_array($r['category'], [SalaryComponentRules::BASE, SalaryComponentRules::FIXED_ALLOWANCE], true)) {
                $rateWage += (float) $r['amount'];
            }
        }
        $rateDivisor = $fixed ? $divisor : max(1, count($workdays) ?: PayrollPeriodRules::daysInPeriod($start, $end));

        // ── 3. Lembur disetujui ─────────────────────────────────────────────
        $overtimeTotal = 0.0;
        $hourly = (new OvertimeRateService())->hourlyRateFromMonthly($rateWage);
        if (!empty($set['overtime_enabled']) && !empty($in['overtime'])) {
            $svc = new OvertimeRateService();
            $wd  = (int) (($set['overtime_work_days'] ?? null) ?: ($set['work_days_per_week'] ?? 5)) === 6 ? 6 : 5;
            foreach ($in['overtime'] as $o) {
                $calc = $svc->calculate((int) $o['duration_minutes'], (string) $o['day_type'], $hourly, $wd);
                $amt  = round((float) $calc['amount'], 2);
                $overtimeTotal += $amt;
                $items[] = [
                    'type' => self::T_EARNING, 'code' => 'overtime',
                    'name' => 'Overtime ' . $o['date'] . ' (' . round($calc['total_hours'], 2) . ' h × ' . round($calc['weighted_hours'] / max($calc['total_hours'], 0.0001), 2) . ')',
                    'amount' => $amt, 'taxable' => true, 'source_type' => 'overtime_request', 'source_id' => $o['id'] ?? null,
                ];
            }
            if ($rateWage <= 0) {
                $warnings[] = 'Overtime exists but the hourly wage is zero (no Base Salary).';
            }
        }

        // ── 4. Reimbursement disetujui (bukan penghasilan kena pajak) ────────
        $reimbursementTotal = 0.0;
        if (!empty($set['include_reimbursement'])) {
            foreach ((array) ($in['reimbursements'] ?? []) as $r) {
                $amt = round((float) $r['amount'], 2);
                $reimbursementTotal += $amt;
                $items[] = ['type' => self::T_EARNING, 'code' => 'reimbursement', 'name' => 'Reimbursement: ' . $r['title'],
                    'amount' => $amt, 'taxable' => false, 'source_type' => 'reimbursement_request', 'source_id' => $r['id'] ?? null];
            }
        }

        // ── 5. Penyesuaian manual ───────────────────────────────────────────
        foreach ((array) ($in['adjustments'] ?? []) as $a) {
            $isDed = $a['kind'] === 'deduction';
            $items[] = [
                'type' => $isDed ? self::T_DEDUCTION : self::T_EARNING, 'code' => 'adjustment', 'name' => (string) $a['name'],
                'amount' => round((float) $a['amount'], 2), 'taxable' => $isDed ? false : (bool) ($a['taxable'] ?? true),
                'source_type' => 'adjustment', 'source_id' => $a['id'] ?? null,
            ];
        }

        // ── 6. Potongan absensi ─────────────────────────────────────────────
        $absenceDeduction = $latePenalty = 0.0;
        $att = $in['attendance'] ?? null;
        if ($att !== null) {
            if (!empty($set['absence_deduction_enabled']) && (float) $att['deductible_days'] > 0) {
                $absenceDeduction = round($rateWage / $rateDivisor * (float) $att['deductible_days'], 2);
                if ($absenceDeduction > $rateWage / 2) {
                    $warnings[] = 'The absence deduction is more than half of the monthly wage — check this employee\'s attendance and leave data.';
                }
                $items[] = ['type' => self::T_DEDUCTION, 'code' => 'absence', 'name' => 'Absence deduction (' . rtrim(rtrim(number_format((float) $att['deductible_days'], 2, '.', ''), '0'), '.') . ' days)',
                    'amount' => $absenceDeduction, 'taxable' => false, 'source_type' => 'attendance', 'source_id' => null];
            }
            if (!empty($set['late_penalty_enabled']) && (int) $att['late_minutes_chargeable'] > 0) {
                $perMinute   = $rateWage / ($rateDivisor * max(1, (int) ($set['workday_base_minutes'] ?? 480)));
                $latePenalty = round($perMinute * (int) $att['late_minutes_chargeable'], 2);
                $items[] = ['type' => self::T_DEDUCTION, 'code' => 'late', 'name' => 'Late penalty (' . (int) $att['late_minutes_chargeable'] . ' min)',
                    'amount' => $latePenalty, 'taxable' => false, 'source_type' => 'attendance', 'source_id' => null];
            }
        }

        $earnings    = self::sum($items, self::T_EARNING);
        $taxableEarn = self::sum(array_filter($items, fn ($i) => $i['taxable']), self::T_EARNING);
        $compDeduct  = self::sum($items, self::T_DEDUCTION);

        // ── 7. BPJS ─────────────────────────────────────────────────────────
        $emp = $in['employee'];
        $bpjsOn = !array_key_exists('bpjs_enabled', $set) || !empty($set['bpjs_enabled']);
        if ($bpjsOn && $bpjsWage > 0 && ($emp['bpjs_health_active'] === null || $emp['bpjs_employment_active'] === null)) {
            $warnings[] = 'BPJS Health/Employment flags are not set for this employee, so BPJS was not calculated for the unset program.';
        }
        $bpjs = BpjsCalculator::calculate($bpjsWage, $in['bpjs'], [
            'health_active' => $bpjsOn ? $emp['bpjs_health_active'] : false, 'employment_active' => $bpjsOn ? $emp['bpjs_employment_active'] : false,
            'dependents' => (int) ($emp['bpjs_dependents'] ?? 0),
        ]);
        foreach ([['BPJS Health (employee)', $bpjs['health']['employee'] + $bpjs['health']['dependents_amount'], 'bpjs_health'],
                  ['JHT (employee)', $bpjs['jht']['employee'], 'bpjs_jht'], ['JP (employee)', $bpjs['jp']['employee'], 'bpjs_jp']] as [$n, $a, $c]) {
            if ($a > 0) {
                $items[] = ['type' => self::T_DEDUCTION, 'code' => $c, 'name' => $n, 'amount' => $a, 'taxable' => false, 'source_type' => 'bpjs', 'source_id' => null];
            }
        }
        foreach ([['BPJS Health (employer)', $bpjs['health']['employer'], 'bpjs_health_er'], ['JHT (employer)', $bpjs['jht']['employer'], 'bpjs_jht_er'],
                  ['JP (employer)', $bpjs['jp']['employer'], 'bpjs_jp_er'], ['JKK (employer)', $bpjs['jkk']['employer'], 'bpjs_jkk_er'], ['JKM (employer)', $bpjs['jkm']['employer'], 'bpjs_jkm_er']] as [$n, $a, $c]) {
            if ($a > 0) {
                $items[] = ['type' => self::T_EMPLOYER, 'code' => $c, 'name' => $n, 'amount' => $a, 'taxable' => false, 'source_type' => 'bpjs', 'source_id' => null];
            }
        }

        // ── 8. PPh 21 ───────────────────────────────────────────────────────
        $premiumsForTax = !empty($in['pph21']['settings']['include_employer_premiums']) ? $bpjs['totals']['employer_premiums_for_tax'] : 0.0;
        $taxGross = round($taxableEarn + $premiumsForTax, 2);
        $pph = ['method' => 'none', 'category' => null, 'rate' => null, 'tax_gross' => $taxGross, 'ter_base' => $taxGross, 'tax' => 0.0, 'refund' => 0.0, 'annual' => null, 'surcharge' => false];

        $pphOn  = !array_key_exists('pph21_enabled', $set) || !empty($set['pph21_enabled']);
        $surcharge = !empty($set['apply_no_npwp_surcharge']) && array_key_exists('has_tax_id', $emp) && !$emp['has_tax_id'];
        $mult = $surcharge ? 1.2 : 1.0;
        $ptkp = PtkpRules::normalize($emp['ptkp_code'] ?? null);

        if (!$pphOn) {
            $pph['method'] = 'disabled';
        } elseif ($ptkp === null) {
            $warnings[] = 'PTKP status is not set, so PPh 21 was not calculated.';
        } elseif (empty($in['pph21']['ter'])) {
            $warnings[] = 'PPh 21 settings for this tax year are not set up, so PPh 21 was not calculated.';
        } elseif (!empty($in['final_tax_period'])) {
            $ytd    = $in['ytd'];
            $months = min(12, (int) $ytd['months'] + 1);
            $annual = Pph21Calculator::finalPeriodTrueUp([
                'annual_gross' => (float) $ytd['gross'] + $taxGross,
                'months' => $months,
                'employee_pension' => (float) $ytd['employee_pension'] + $bpjs['totals']['employee_pension'],
                'ptkp_annual' => (float) ($in['pph21']['ptkp'][$ptkp] ?? 0),
                'occupational_cost_rate' => (float) $in['pph21']['settings']['occupational_cost_rate'],
                'occupational_cost_monthly_max' => (float) $in['pph21']['settings']['occupational_cost_monthly_max'],
            ], $in['pph21']['brackets'], (float) $ytd['pph21_withheld']);
            $annualTax = floor($annual['annual']['tax'] * $mult);
            $payable   = $annualTax - (float) $ytd['pph21_withheld'];
            $annual['annual']['tax'] = $annualTax;
            $annual['payable']  = round($payable, 2);
            $annual['overpaid'] = $payable < 0;
            $pph = ['method' => 'annual_true_up', 'category' => PtkpRules::terCategory($ptkp), 'rate' => null, 'tax_gross' => $taxGross, 'ter_base' => $taxGross,
                'tax' => max(0.0, $payable), 'refund' => $payable < 0 ? -$payable : 0.0, 'annual' => $annual, 'surcharge' => $surcharge];
            if (empty($in['pph21']['ptkp'][$ptkp])) {
                $warnings[] = 'PTKP amount for ' . $ptkp . ' is missing for this tax year.';
            }
        } else {
            // Opsi "potong JHT+JP karyawan sebelum TER" (bukan ketentuan baku PMK 168/2023): mengurangi dasar TER bulanan saja.
            $terBase = !empty($set['deduct_bpjs_tk_before_pph21']) ? max(0.0, $taxGross - $bpjs['totals']['employee_pension']) : $taxGross;
            $t   = Pph21Calculator::monthlyTer($terBase, $ptkp, $in['pph21']['ter']);
            $pph = ['method' => 'ter', 'category' => $t['category'], 'rate' => $t['rate'], 'tax_gross' => $taxGross, 'ter_base' => $terBase,
                'tax' => floor($t['tax'] * $mult), 'refund' => 0.0, 'annual' => null, 'surcharge' => $surcharge];
        }
        if ($pph['tax'] > 0) {
            $items[] = ['type' => self::T_DEDUCTION, 'code' => 'pph21', 'name' => 'PPh 21' . ($surcharge ? ' (+20% no NPWP)' : ''), 'amount' => $pph['tax'], 'taxable' => false, 'source_type' => 'pph21', 'source_id' => null];
        }

        // ── 9. Total ────────────────────────────────────────────────────────
        $deductions = round($compDeduct + $bpjs['totals']['employee'] + $pph['tax'], 2);
        $thp        = round($earnings - $deductions, 2);
        if ($thp < 0) {
            $warnings[] = 'Take-home pay is negative. Check deductions and adjustments.';
        }

        return [
            'items' => $items,
            'summary' => [
                'gross_earnings' => round($earnings, 2),
                'taxable_earnings' => round($taxableEarn, 2),
                'overtime' => round($overtimeTotal, 2),
                'reimbursement' => round($reimbursementTotal, 2),
                'late_penalty' => $latePenalty,
                'absence_deduction' => $absenceDeduction,
                'component_deductions' => round($compDeduct, 2),
                'bpjs_employee' => $bpjs['totals']['employee'],
                'bpjs_employer' => $bpjs['totals']['employer'],
                'pph21' => $pph['tax'],
                'total_deductions' => $deductions,
                'take_home_pay' => $thp,
                'employer_cost' => round($earnings + $bpjs['totals']['employer'], 2),
                'bpjs_wage' => $bpjsWage,
                'rate_wage' => round($rateWage, 2),
                'paid_days' => round($paidDays, 2),
                'basis_days' => round($basisDays, 2),
                'hourly_rate' => round($hourly, 2),
            ],
            'bpjs' => $bpjs,
            'pph21' => $pph,
            'warnings' => $warnings,
        ];
    }

    /** @param iterable<array<string,mixed>> $items */
    private static function sum(iterable $items, string $type): float
    {
        $t = 0.0;
        foreach ($items as $i) {
            if ($i['type'] === $type) {
                $t += (float) $i['amount'];
            }
        }

        return round($t, 2);
    }
}
