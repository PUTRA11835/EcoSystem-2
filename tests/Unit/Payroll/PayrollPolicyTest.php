<?php

namespace Tests\Unit\Payroll;

use App\Services\Payroll\PayrollEngine;
use App\Support\Payroll\BpjsSettingRules;
use App\Support\Payroll\PayrollCalculator as P;
use App\Support\Payroll\PayrollCalendar;
use PHPUnit\Framework\TestCase;

/** Kebijakan Payroll → Settings: prorata pembagi tetap, potongan absensi, denda terlambat, reimbursement, saklar BPJS/PPh 21. */
class PayrollPolicyTest extends TestCase
{
    private array $pph;
    private array $bpjs;

    protected function setUp(): void
    {
        $d = require dirname(__DIR__, 3) . '/database/data/pph21_2026.php';
        $ter = [];
        foreach ($d['ter'] as $cat => $layers) {
            $over = 0;
            foreach ($layers as [$upto, $rate]) {
                $ter[$cat][] = ['gross_over' => $over, 'gross_upto' => $upto, 'rate' => $rate];
                $over = $upto;
            }
        }
        $this->pph = ['ptkp' => array_column($d['ptkp'], 2, 0), 'ter' => $ter,
            'brackets' => array_map(fn ($b) => ['upper_limit' => $b[0], 'rate' => $b[1]], $d['progressive']), 'settings' => $d['settings']];
        $this->bpjs = BpjsSettingRules::effectiveOn(require dirname(__DIR__, 3) . '/database/data/bpjs_2026.php', '2026-10-31');
    }

    private function base(string $from = '2026-01-01', float $amt = 10_000_000): array
    {
        return ['name' => 'Basic Salary', 'category' => 'base', 'amount' => $amt, 'effective_from' => $from, 'effective_to' => null, 'is_active' => true, 'taxable' => true, 'bpjs_base' => true];
    }

    private function input(array $settings = [], array $over = []): array
    {
        return $over + [
            'start' => '2026-10-01', 'end' => '2026-10-31', 'components' => [$this->base()],
            'overtime' => [], 'adjustments' => [], 'reimbursements' => [], 'attendance' => null,
            'workdays' => PayrollCalendar::workdays('2026-10-01', '2026-10-31', 5, []),
            'employee' => ['ptkp_code' => 'TK/0', 'bpjs_health_active' => true, 'bpjs_employment_active' => true, 'bpjs_dependents' => 0, 'has_tax_id' => true],
            'settings' => $settings + ['work_days_per_week' => 5, 'overtime_enabled' => true, 'prorate_partial_period' => true,
                'proration_basis' => 'fixed_divisor', 'fixed_divisor' => 21, 'workday_base_minutes' => 480],
            'bpjs' => $this->bpjs, 'pph21' => $this->pph, 'final_tax_period' => false,
            'ytd' => ['gross' => 0, 'employee_pension' => 0, 'pph21_withheld' => 0, 'months' => 0],
        ];
    }

    public function test_kalender_hari_kerja(): void
    {
        $this->assertCount(22, PayrollCalendar::workdays('2026-10-01', '2026-10-31', 5, []));      // Okt 2026: 22 hari Senin–Jumat
        $this->assertCount(27, PayrollCalendar::workdays('2026-10-01', '2026-10-31', 6, []));      // + 4 Sabtu
        $this->assertCount(21, PayrollCalendar::workdays('2026-10-01', '2026-10-31', 5, ['2026-10-12']));
    }

    public function test_pembagi_tetap_masuk_tengah_bulan(): void
    {
        // 16–31 Okt 2026: 11 hari kerja → 11/21 dari 10 juta
        $r = P::calculate($this->input([], ['components' => [$this->base('2026-10-16')]]));

        $this->assertEqualsWithDelta(5_238_095.24, $r['summary']['gross_earnings'], 0.01);
        $this->assertStringContainsString('11/21', $r['items'][0]['name']);
    }

    public function test_pembagi_tetap_periode_penuh_tetap_100_persen_walau_22_hari_kerja(): void
    {
        $this->assertSame(10_000_000.0, P::calculate($this->input())['summary']['gross_earnings']);
    }

    public function test_potongan_absensi_memakai_pembagi_tetap(): void
    {
        $in = $this->input(['absence_deduction_enabled' => true], ['attendance' => ['deductible_days' => 2.0, 'late_minutes_chargeable' => 0]]);
        $r = P::calculate($in);

        $this->assertEqualsWithDelta(952_380.95, $r['summary']['absence_deduction'], 0.01);     // 10jt / 21 × 2
        $this->assertSame('absence', $r['items'][array_key_last(array_filter($r['items'], fn ($i) => $i['code'] === 'absence'))]['code']);
    }

    public function test_potongan_absensi_dimatikan_atau_tanpa_data_tidak_memotong(): void
    {
        $att = ['attendance' => ['deductible_days' => 2.0, 'late_minutes_chargeable' => 30]];
        $this->assertSame(0.0, P::calculate($this->input([], $att))['summary']['absence_deduction']);
        $this->assertSame(0.0, P::calculate($this->input(['absence_deduction_enabled' => true]))['summary']['absence_deduction']);
    }

    public function test_potongan_absensi_berbasis_kalender_membagi_hari_kerja_periode(): void
    {
        $in = $this->input(['absence_deduction_enabled' => true, 'proration_basis' => 'calendar'], ['attendance' => ['deductible_days' => 1.0, 'late_minutes_chargeable' => 0]]);

        $this->assertEqualsWithDelta(454_545.45, P::calculate($in)['summary']['absence_deduction'], 0.01);   // 10jt / 22
    }

    public function test_denda_terlambat_per_menit(): void
    {
        $in = $this->input(['late_penalty_enabled' => true], ['attendance' => ['deductible_days' => 0, 'late_minutes_chargeable' => 30]]);

        $this->assertEqualsWithDelta(29_761.90, P::calculate($in)['summary']['late_penalty'], 0.01);   // 10jt / (21 × 480) × 30
    }

    public function test_reimbursement_tidak_kena_pajak_dan_menambah_thp(): void
    {
        $in = $this->input(['include_reimbursement' => true], ['reimbursements' => [['id' => 9, 'title' => 'Taxi', 'amount' => 150_000]]]);
        $base = P::calculate($this->input());
        $r = P::calculate($in);

        $this->assertSame(150_000.0, $r['summary']['reimbursement']);
        $this->assertSame($base['summary']['pph21'], $r['summary']['pph21']);
        $this->assertSame($base['summary']['take_home_pay'] + 150_000, $r['summary']['take_home_pay']);
        $this->assertSame(0.0, P::calculate($this->input([], ['reimbursements' => [['id' => 9, 'title' => 'Taxi', 'amount' => 150_000]]]))['summary']['reimbursement']);
    }

    public function test_bpjs_dimatikan_menghilangkan_bpjs_dan_premi_dari_bruto_pajak(): void
    {
        $r = P::calculate($this->input(['bpjs_enabled' => false]));

        $this->assertSame(0.0, $r['summary']['bpjs_employee']);
        $this->assertSame(10_000_000.0, $r['pph21']['tax_gross']);
        $this->assertSame(200_000.0, $r['summary']['pph21']);                    // TER A 2%
    }

    public function test_pph21_dimatikan(): void
    {
        $r = P::calculate($this->input(['pph21_enabled' => false]));

        $this->assertSame(0.0, $r['summary']['pph21']);
        $this->assertSame('disabled', $r['pph21']['method']);
        $this->assertSame([], $r['warnings']);
    }

    public function test_tambahan_20_persen_tanpa_npwp(): void
    {
        $in = $this->input(['apply_no_npwp_surcharge' => true]);
        $in['employee']['has_tax_id'] = false;

        $this->assertSame(313_620.0, P::calculate($in)['summary']['pph21']);      // 261.350 × 1,2
        $in['employee']['has_tax_id'] = true;
        $this->assertSame(261_350.0, P::calculate($in)['summary']['pph21']);
    }

    public function test_jht_jp_dipotong_sebelum_ter(): void
    {
        $r = P::calculate($this->input(['deduct_bpjs_tk_before_pph21' => true]));

        $this->assertSame(10_454_000.0, $r['pph21']['tax_gross']);                // bruto tak berubah (dipakai true-up)
        $this->assertSame(10_154_000.0, $r['pph21']['ter_base']);                 // − JHT 200rb − JP 100rb
        $this->assertSame(228_465.0, $r['summary']['pph21']);                     // TER A 2,25%
    }

    public function test_biaya_perusahaan(): void
    {
        $r = P::calculate($this->input());

        $this->assertSame(11_024_000.0, $r['summary']['employer_cost']);          // 10jt + BPJS perusahaan 1.024.000
    }
    public function test_saklar_induk_absensi(): void
    {
        $off = ['attendance_enabled' => false, 'attendance_effective_from' => null];
        $this->assertFalse(PayrollEngine::attendanceAppliesTo($off, '2026-10-01'));

        $on = ['attendance_enabled' => true, 'attendance_effective_from' => null];
        $this->assertTrue(PayrollEngine::attendanceAppliesTo($on, '2026-10-01'));

        // tanggal mulai berlaku: periode yang DIMULAI sebelum tanggal itu tidak terpengaruh
        $from = ['attendance_enabled' => true, 'attendance_effective_from' => '2026-11-01'];
        $this->assertFalse(PayrollEngine::attendanceAppliesTo($from, '2026-10-01'));
        $this->assertTrue(PayrollEngine::attendanceAppliesTo($from, '2026-11-01'));
        $this->assertTrue(PayrollEngine::attendanceAppliesTo($from, '2026-12-01'));

        // saklar induk mati menang atas tanggal mulai
        $this->assertFalse(PayrollEngine::attendanceAppliesTo(['attendance_enabled' => false, 'attendance_effective_from' => '2026-01-01'], '2026-12-01'));
    }
}