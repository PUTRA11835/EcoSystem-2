<?php

namespace Tests\Unit\Offboarding;

use App\Support\Offboarding\OffboardingSettlementRules as R;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Aturan murni Final Settlement. Angka hukum di sini dioper sebagai argumen (di aplikasi: Register Dasar Hukum). */
class OffboardingSettlementRulesTest extends TestCase
{
    private const SEVERANCE = [[1, 1], [2, 2], [3, 3], [4, 4], [5, 5], [6, 6], [7, 7], [8, 8], [null, 9]];
    private const AWARD = [[3, 0], [6, 2], [9, 3], [12, 4], [15, 5], [18, 6], [21, 7], [24, 8], [null, 10]];
    private const TAX = [['up_to' => 50000000, 'rate' => 0.0], ['up_to' => 100000000, 'rate' => 0.05], ['up_to' => 500000000, 'rate' => 0.15], ['up_to' => null, 'rate' => 0.25]];

    private function rules(string $type, array $reason): array
    {
        return ['reason' => $reason, 'severance' => self::SEVERANCE, 'service_award' => self::AWARD, 'tax' => self::TAX, 'pkwt_months_per_year' => 12, 'type' => $type];
    }

    private function input(array $over = []): array
    {
        return $over + ['wage' => 10000000.0, 'service_start' => '2016-01-01', 'effective' => '2026-01-01', 'contract_type' => 'PKWTT', 'contract_end' => null,
            'leave_days' => 0.0, 'leave_divisor' => 21.0, 'retirement_benefit' => 0.0, 'separation_pay' => 0.0, 'other_compensation' => 0.0];
    }

    // ── masa kerja & tabel ───────────────────────────────────────────────

    public function test_service_years_and_months(): void
    {
        $this->assertSame(['years' => 2.0, 'months' => 24], R::service('2024-01-15', '2026-01-15'));
        $this->assertSame(6, R::service('2025-07-01', '2026-01-01')['months']);
        $this->assertSame(['years' => 0.0, 'months' => 0], R::service('2026-01-01', '2025-01-01'));
        $this->assertSame(['years' => 0.0, 'months' => 0], R::service('2026-01-01', '2026-01-01'));
    }

    #[DataProvider('tableCases')]
    public function test_table_multiplier(array $table, float $years, float $expected): void
    {
        $this->assertSame($expected, R::tableMultiplier($table, $years));
    }

    public static function tableCases(): array
    {
        return [
            'pesangon <1' => [self::SEVERANCE, 0.5, 1.0], 'tepat 1 tahun' => [self::SEVERANCE, 1.0, 2.0], 'hampir 8' => [self::SEVERANCE, 7.99, 8.0],
            'tepat 8' => [self::SEVERANCE, 8.0, 9.0], 'lama sekali' => [self::SEVERANCE, 40.0, 9.0],
            'UPMK <3' => [self::AWARD, 2.9, 0.0], 'UPMK tepat 3' => [self::AWARD, 3.0, 2.0], 'UPMK 23,9' => [self::AWARD, 23.9, 8.0], 'UPMK 24' => [self::AWARD, 24.0, 10.0],
        ];
    }

    // ── kompensasi PKWT, pajak, sisa kontrak ─────────────────────────────

    public function test_pkwt_compensation_is_proportional(): void
    {
        $this->assertSame(2500000.0, R::pkwtCompensation(5000000, 6));
        $this->assertSame(5000000.0, R::pkwtCompensation(5000000, 12));
        $this->assertSame(0.0, R::pkwtCompensation(5000000, 0));
    }

    #[DataProvider('taxCases')]
    public function test_progressive_tax(float $taxable, float $expected): void
    {
        $this->assertSame($expected, R::progressiveTax($taxable, self::TAX));
    }

    public static function taxCases(): array
    {
        return ['nol' => [0.0, 0.0], 'di bawah 50 jt' => [40000000.0, 0.0], 'tepat 50 jt' => [50000000.0, 0.0], '75 jt' => [75000000.0, 1250000.0],
            '150 jt' => [150000000.0, 10000000.0], '600 jt' => [600000000.0, 87500000.0]];
    }

    public function test_remaining_months(): void
    {
        $this->assertSame(0, R::remainingMonths('2026-10-01', null));
        $this->assertSame(0, R::remainingMonths('2026-10-01', '2026-09-30'));
        $this->assertSame(0, R::remainingMonths('2026-10-01', '2026-10-01'));
        $this->assertSame(6, R::remainingMonths('2026-10-01', '2027-03-30'));
        $this->assertSame(1, R::remainingMonths('2026-10-01', '2026-10-02'));
    }

    // ── jenis & alasan ───────────────────────────────────────────────────

    public function test_types_and_reasons(): void
    {
        $this->assertSame('retirement', R::reasonFor('retirement', null));
        $this->assertSame('termination_efficiency', R::reasonFor('termination', 'termination_efficiency'));
        $this->assertNull(R::reasonFor('termination', 'bogus'));
        $this->assertNull(R::reasonFor('end_of_engagement', null));
        $this->assertContains('end_of_engagement', R::TYPES_BY_KIND['External']);
        $this->assertNotContains('retirement', R::TYPES_BY_KIND['External']);
        foreach (R::TYPES_BY_KIND as $list) {
            foreach ($list as $type) {
                $this->assertArrayHasKey($type, R::TYPES);
            }
        }
    }

    // ── perhitungan utuh ─────────────────────────────────────────────────

    public function test_retirement_with_offset_and_tax(): void
    {
        $reason = ['severance' => 1.75, 'service_award' => 1, 'leave' => true];
        $r = R::calculate($this->input(['retirement_benefit' => 50000000.0]), $this->rules('retirement', $reason));

        $this->assertSame(10.0, $r['service_years']);
        $this->assertSame(15.75, $r['severance_multiplier']);                // 9 × 1,75
        $this->assertSame(4.0, $r['service_award_multiplier']);              // 10 tahun → 4
        $this->assertSame(197500000.0, $r['gross_entitlement']);             // 157,5 jt + 40 jt
        $this->assertSame(50000000.0, $r['retirement_offset']);
        $this->assertSame(147500000.0, $r['severance'] + $r['service_award']);
        $this->assertSame(147500000.0, $r['taxable_base']);
        $this->assertSame(9625000.0, $r['severance_tax']);                   // 2,5 jt + 47,5 jt × 15%
        $this->assertSame(137875000.0, $r['net_settlement']);
    }

    public function test_offset_never_goes_below_zero(): void
    {
        $r = R::calculate($this->input(['retirement_benefit' => 999999999999.0]), $this->rules('retirement', ['severance' => 1.75, 'service_award' => 1, 'leave' => true]));
        $this->assertSame(0.0, $r['severance'] + $r['service_award']);
        $this->assertSame(0.0, $r['severance_tax']);
        $this->assertSame(0.0, $r['net_settlement']);
    }

    public function test_end_of_pkwt_pays_compensation_not_severance(): void
    {
        $r = R::calculate($this->input(['wage' => 5000000.0, 'service_start' => '2025-01-01', 'effective' => '2026-01-01', 'contract_type' => 'PKWT', 'contract_end' => '2026-01-01', 'leave_days' => 2.0]),
            $this->rules('end_of_pkwt', ['severance' => 0, 'service_award' => 0, 'leave' => true, 'pkwt_compensation' => true]));

        $this->assertSame(5000000.0, $r['pkwt_compensation']);
        $this->assertSame(0.0, $r['severance']);
        $this->assertSame(476190.48, $r['leave_settlement']);                // 2 × 5 jt ÷ 21
        $this->assertSame(0, $r['pkwt_penalty_months']);
        $this->assertSame(5476190.48, $r['net_settlement']);
    }

    public function test_pkwt_compensation_only_for_pkwt_contracts(): void
    {
        $r = R::calculate($this->input(['contract_type' => 'PKWTT']), $this->rules('end_of_pkwt', ['severance' => 0, 'service_award' => 0, 'leave' => true, 'pkwt_compensation' => true]));
        $this->assertSame(0.0, $r['pkwt_compensation']);
    }

    public function test_resignation_gets_leave_and_separation_only(): void
    {
        $r = R::calculate($this->input(['wage' => 6300000.0, 'leave_days' => 5.0, 'separation_pay' => 3000000.0]), $this->rules('resignation', ['severance' => 0, 'service_award' => 0, 'leave' => true]));

        $this->assertSame(1500000.0, $r['leave_settlement']);               // 5 × 6,3 jt ÷ 21
        $this->assertSame(3000000.0, $r['separation_pay']);
        $this->assertSame(3000000.0, $r['taxable_base']);
        $this->assertSame(0.0, $r['severance_tax']);                         // di bawah Rp50 jt
        $this->assertSame(4500000.0, $r['net_settlement']);
    }

    public function test_early_pkwt_termination_reports_penalty_risk_outside_net(): void
    {
        $r = R::calculate($this->input(['wage' => 8000000.0, 'service_start' => '2026-01-01', 'effective' => '2026-10-01', 'contract_type' => 'PKWT', 'contract_end' => '2027-03-30']),
            $this->rules('termination', ['severance' => 1, 'service_award' => 1, 'leave' => true]));

        $this->assertSame(6, $r['pkwt_penalty_months']);
        $this->assertSame(48000000.0, $r['pkwt_penalty_risk']);
        $this->assertNotSame($r['pkwt_penalty_risk'], $r['net_settlement']);
        $this->assertSame(8000000.0 * 1 + 0.0, $r['severance']);             // masa kerja < 1 tahun → pengali 1 × 1
    }

    public function test_zero_inputs_are_safe(): void
    {
        $r = R::calculate($this->input(['wage' => 0.0, 'service_start' => '2026-01-01', 'effective' => '2026-01-01']), $this->rules('termination', ['severance' => 1, 'service_award' => 1, 'leave' => true]));
        $this->assertSame(0.0, $r['net_settlement']);
        $this->assertSame(0.0, $r['service_years']);
    }
}
