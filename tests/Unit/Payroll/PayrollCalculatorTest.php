<?php

namespace Tests\Unit\Payroll;

use App\Support\Payroll\BpjsSettingRules;
use App\Support\Payroll\PayrollCalculator as P;
use App\Support\Payroll\PayrollPeriodRules as R;
use PHPUnit\Framework\TestCase;

/**
 * Skenario payroll lengkap. Skenario A–F JUGA dipakai sebagai kasus uji manual di
 * docs/humancapital/07-PANDUAN-UJI-PAYROLL.md — angka di sini dihitung tangan dan harus sama dengan layar.
 */
class PayrollCalculatorTest extends TestCase
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
        $this->pph = [
            'ptkp' => array_column($d['ptkp'], 2, 0),
            'ter' => $ter,
            'brackets' => array_map(fn ($b) => ['upper_limit' => $b[0], 'rate' => $b[1]], $d['progressive']),
            'settings' => $d['settings'],
        ];
        $this->bpjs = BpjsSettingRules::effectiveOn(require dirname(__DIR__, 3) . '/database/data/bpjs_2026.php', '2026-10-31');
    }

    private function comp(string $name, string $cat, float $amount, string $from = '2026-01-01', ?string $to = null, array $extra = []): array
    {
        return $extra + ['name' => $name, 'category' => $cat, 'amount' => $amount, 'effective_from' => $from,
            'effective_to' => $to, 'is_active' => true, 'taxable' => true, 'bpjs_base' => true];
    }

    private function input(array $over = []): array
    {
        return $over + [
            'start' => '2026-10-01', 'end' => '2026-10-31',
            'components' => [$this->comp('Basic Salary', 'base', 10_000_000)],
            'overtime' => [], 'adjustments' => [],
            'employee' => ['ptkp_code' => 'TK/0', 'bpjs_health_active' => true, 'bpjs_employment_active' => true, 'bpjs_dependents' => 0],
            'settings' => ['work_days_per_week' => 5, 'overtime_enabled' => true, 'prorate_partial_period' => true],
            'bpjs' => $this->bpjs, 'pph21' => $this->pph,
            'final_tax_period' => false,
            'ytd' => ['gross' => 0, 'employee_pension' => 0, 'pph21_withheld' => 0, 'months' => 0],
        ];
    }

    /** A — karyawan biasa TK/0, gaji pokok Rp10.000.000, BPJS aktif, bulan biasa (TER). */
    public function test_skenario_a_gaji_pokok_saja(): void
    {
        $r = P::calculate($this->input());
        $s = $r['summary'];

        $this->assertSame(10_000_000.0, $s['gross_earnings']);
        $this->assertSame(400_000.0, $s['bpjs_employee']);           // 100rb Kes + 200rb JHT + 100rb JP
        $this->assertSame(1_024_000.0, $s['bpjs_employer']);         // 400+370+200+24+30 rb
        $this->assertSame(10_454_000.0, $r['pph21']['tax_gross']);   // + premi Kes/JKK/JKM perusahaan 454rb
        $this->assertSame('A', $r['pph21']['category']);
        $this->assertSame(2.5, $r['pph21']['rate']);
        $this->assertSame(261_350.0, $s['pph21']);
        $this->assertSame(661_350.0, $s['total_deductions']);
        $this->assertSame(9_338_650.0, $s['take_home_pay']);
        $this->assertSame([], $r['warnings']);
    }

    /** A2 — premi perusahaan TIDAK dihitung sebagai bruto (saklar di PPh 21 Settings dimatikan). */
    public function test_skenario_a2_tanpa_premi_perusahaan_di_bruto(): void
    {
        $in = $this->input();
        $in['pph21']['settings']['include_employer_premiums'] = false;
        $r = P::calculate($in);

        $this->assertSame(10_000_000.0, $r['pph21']['tax_gross']);   // TER A: 9.65–10.05jt = 2%
        $this->assertSame(200_000.0, $r['summary']['pph21']);
    }

    /** B — K/1, gaji pokok 8jt + tunjangan tetap 2jt + lembur 2 jam hari kerja. */
    public function test_skenario_b_tunjangan_dan_lembur(): void
    {
        $in = $this->input([
            'components' => [$this->comp('Basic Salary', 'base', 8_000_000), $this->comp('Position Allowance', 'fixed_allowance', 2_000_000)],
            'overtime' => [['id' => 1, 'date' => '2026-10-12', 'duration_minutes' => 120, 'day_type' => 'workday']],
        ]);
        $in['employee']['ptkp_code'] = 'K/1';
        $r = P::calculate($in);

        // Upah sejam = 10.000.000 / 173; 2 jam hari kerja = 1×1,5 + 1×2 = 3,5 jam setara.
        $this->assertEqualsWithDelta(202_312.14, $r['summary']['overtime'], 0.01);
        $this->assertEqualsWithDelta(10_202_312.14, $r['summary']['gross_earnings'], 0.01);
        $this->assertSame(10_000_000.0, $r['summary']['bpjs_wage']);               // lembur bukan dasar BPJS
        $this->assertSame('B', $r['pph21']['category']);
        $this->assertSame(1.5, $r['pph21']['rate']);                                // bruto 10.656.312 → B: 9,2–10,75jt
        $this->assertSame(159_844.0, $r['summary']['pph21']);
    }

    /** C — Desember: true-up setahun dikurangi PPh 21 TER Jan–Nov. */
    public function test_skenario_c_desember_true_up(): void
    {
        $in = $this->input(['start' => '2026-12-01', 'end' => '2026-12-31', 'final_tax_period' => true]);
        $in['ytd'] = ['gross' => 11 * 10_454_000, 'employee_pension' => 11 * 300_000, 'pph21_withheld' => 11 * 261_350, 'months' => 11];
        $r = P::calculate($in);
        $a = $r['pph21']['annual'];

        $this->assertSame('annual_true_up', $r['pph21']['method']);
        $this->assertSame(125_448_000.0, $a['annual']['annual_gross']);
        $this->assertSame(6_000_000.0, $a['annual']['occupational_cost']);   // 5% = 6.272.400 → dibatasi 6jt
        $this->assertSame(3_600_000.0, $a['annual']['employee_pension']);
        $this->assertSame(115_848_000.0, $a['annual']['net_income']);
        $this->assertSame(61_848_000.0, $a['annual']['pkp']);                // − PTKP TK/0 54jt
        $this->assertSame(3_277_200.0, $a['annual']['tax']);                 // 3jt + 15% × 1.848.000
        $this->assertSame(402_350.0, $r['summary']['pph21']);                // 3.277.200 − 2.874.850
        $this->assertSame(0.0, $r['pph21']['refund']);
    }

    /** C2 — Desember dengan kelebihan potong: PPh Desember 0, selisih dicatat sebagai refund. */
    public function test_skenario_c2_kelebihan_potong(): void
    {
        $in = $this->input(['start' => '2026-12-01', 'end' => '2026-12-31', 'final_tax_period' => true]);
        $in['ytd'] = ['gross' => 11 * 10_454_000, 'employee_pension' => 11 * 300_000, 'pph21_withheld' => 4_000_000, 'months' => 11];
        $r = P::calculate($in);

        $this->assertSame(0.0, $r['summary']['pph21']);
        $this->assertSame(722_800.0, $r['pph21']['refund']);                 // 4.000.000 − 3.277.200
    }

    /** D — karyawan masuk 16 Oktober: gaji prorata 16/31, BPJS tetap dasar upah penuh. */
    public function test_skenario_d_masuk_tengah_bulan(): void
    {
        $in = $this->input(['components' => [$this->comp('Basic Salary', 'base', 10_000_000, '2026-10-16')]]);
        $r = P::calculate($in);

        $this->assertEqualsWithDelta(5_161_290.32, $r['summary']['gross_earnings'], 0.01);
        $this->assertSame(10_000_000.0, $r['summary']['bpjs_wage']);
        $this->assertStringContainsString('16/31', $r['items'][0]['name']);
    }

    /** D2 — prorata dimatikan di Payroll Settings: dibayar penuh. */
    public function test_skenario_d2_tanpa_prorata(): void
    {
        $in = $this->input(['components' => [$this->comp('Basic Salary', 'base', 10_000_000, '2026-10-16')]]);
        $in['settings']['prorate_partial_period'] = false;

        $this->assertSame(10_000_000.0, P::calculate($in)['summary']['gross_earnings']);
    }

    /** E — data belum lengkap: tetap dihitung semampunya + peringatan, tidak melempar. */
    public function test_skenario_e_data_kurang_lengkap_memberi_peringatan(): void
    {
        $in = $this->input();
        $in['employee'] = ['ptkp_code' => null, 'bpjs_health_active' => null, 'bpjs_employment_active' => null, 'bpjs_dependents' => 0];
        $r = P::calculate($in);

        $this->assertCount(2, $r['warnings']);
        $this->assertSame(0.0, $r['summary']['pph21']);
        $this->assertSame(0.0, $r['summary']['bpjs_employee']);
        $this->assertSame(10_000_000.0, $r['summary']['take_home_pay']);
    }

    public function test_tanpa_gaji_pokok_diperingatkan(): void
    {
        $r = P::calculate($this->input(['components' => []]));

        $this->assertContains('No Base Salary covers this period.', $r['warnings']);
        $this->assertSame(0.0, $r['summary']['gross_earnings']);
    }

    /** F — penyesuaian manual: bonus kena pajak, potongan, dan potongan komponen. */
    public function test_skenario_f_penyesuaian_manual(): void
    {
        $in = $this->input([
            'components' => [$this->comp('Basic Salary', 'base', 10_000_000), $this->comp('Loan installment', 'deduction', 500_000)],
            'adjustments' => [
                ['kind' => 'earning', 'name' => 'Performance bonus', 'amount' => 1_000_000, 'taxable' => true],
                ['kind' => 'earning', 'name' => 'Meal reimbursement', 'amount' => 200_000, 'taxable' => false],
                ['kind' => 'deduction', 'name' => 'Late penalty', 'amount' => 100_000],
            ],
        ]);
        $r = P::calculate($in);
        $s = $r['summary'];

        $this->assertSame(11_200_000.0, $s['gross_earnings']);
        $this->assertSame(11_000_000.0, $s['taxable_earnings']);              // reimbursement tidak kena pajak
        $this->assertSame(600_000.0, $s['component_deductions']);             // 500rb + 100rb
        $this->assertSame(11_454_000.0, $r['pph21']['tax_gross']);            // TER A 11,05–11,6jt = 3,5%
        $this->assertSame(400_890.0, $s['pph21']);
        $this->assertSame(11_200_000 - 600_000 - 400_000 - 400_890.0, $s['take_home_pay']);
    }

    public function test_lembur_dimatikan_di_settings(): void
    {
        $in = $this->input(['overtime' => [['id' => 1, 'date' => '2026-10-12', 'duration_minutes' => 120, 'day_type' => 'workday']]]);
        $in['settings']['overtime_enabled'] = false;

        $this->assertSame(0.0, P::calculate($in)['summary']['overtime']);
    }

    public function test_kenaikan_gaji_tengah_bulan_dipecah_dua_baris(): void
    {
        $in = $this->input(['components' => [
            $this->comp('Basic Salary', 'base', 10_000_000, '2026-01-01', '2026-10-15'),
            $this->comp('Basic Salary', 'base', 12_000_000, '2026-10-16'),
        ]]);
        $r = P::calculate($in);

        // 10jt × 15/31 + 12jt × 16/31
        $this->assertEqualsWithDelta(4_838_709.68 + 6_193_548.39, $r['summary']['gross_earnings'], 0.02);
        $this->assertSame(12_000_000.0, $r['summary']['bpjs_wage']);          // dasar BPJS = upah pada akhir periode
    }

    // ── Aturan periode ──────────────────────────────────────────────────────

    public function test_periode_tumpang_tindih_ditolak(): void
    {
        $others = [['name' => 'Payroll Oktober 2026', 'period_start' => '2026-10-01', 'period_end' => '2026-10-31']];
        $bad = ['name' => 'X', 'period_start' => '2026-10-31', 'period_end' => '2026-11-30', 'pay_date' => '2026-11-30'];
        $ok  = ['name' => 'Payroll November 2026', 'period_start' => '2026-11-01', 'period_end' => '2026-11-30', 'pay_date' => '2026-11-29'];

        $this->assertNotEmpty(R::validate($bad, $others));
        $this->assertSame([], R::validate($ok, $others));
    }

    public function test_periode_tanggal_tidak_sah(): void
    {
        $this->assertNotEmpty(R::validate(['name' => '', 'period_start' => '2026-02-30', 'period_end' => '2026-03-01', 'pay_date' => 'x'], []));
        $this->assertNotEmpty(R::validate(['name' => 'X', 'period_start' => '2026-10-10', 'period_end' => '2026-10-01', 'pay_date' => '2026-10-10'], []));
        $this->assertNotEmpty(R::validate(['name' => 'X', 'period_start' => '2026-01-01', 'period_end' => '2026-12-31', 'pay_date' => '2026-12-31'], []));
    }

    public function test_jendela_absensi_dan_cutoff(): void
    {
        $w = R::attendanceWindow(['period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'attendance_start' => '2026-09-26', 'attendance_end' => '2026-10-25', 'cutoff_date' => '2026-10-20']);
        $this->assertSame(['2026-09-26', '2026-10-25', '2026-10-20', '2026-10-20'], [$w['start'], $w['end'], $w['trusted_end'], $w['cutoff']]);
        $d = R::attendanceWindow(['period_start' => '2026-10-01', 'period_end' => '2026-10-31']);
        $this->assertSame(['2026-10-01', '2026-10-31', '2026-10-31', null], [$d['start'], $d['end'], $d['trusted_end'], $d['cutoff']]);
        $this->assertNotEmpty(R::validate(['name' => 'x', 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'pay_date' => '2026-10-31', 'cutoff_date' => '2026-11-15'], []));
    }

    public function test_alur_status(): void
    {
        // Alur ESH: hanya open → locked.
        $this->assertTrue(R::canTransition('open', 'locked'));
        $this->assertFalse(R::canTransition('locked', 'open'));
        $this->assertFalse(R::canTransition('open', 'paid'));
        $this->assertTrue(R::isEditable('open'));
        $this->assertFalse(R::isEditable('locked'));
        $this->assertTrue(R::isFinalTaxPeriod('2026-12-31'));
        $this->assertFalse(R::isFinalTaxPeriod('2026-11-30'));
        $this->assertSame(31, R::daysInPeriod('2026-10-01', '2026-10-31'));
    }
}
