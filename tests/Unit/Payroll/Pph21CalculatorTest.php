<?php

namespace Tests\Unit\Payroll;

use App\Support\Payroll\Pph21Calculator as P;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Uji kalkulator PPh 21 dengan data resmi 2026 (database/data/pph21_2026.php). */
class Pph21CalculatorTest extends TestCase
{
    private array $data;
    private array $ter = [];
    private array $brackets = [];

    protected function setUp(): void
    {
        $this->data = require dirname(__DIR__, 3) . '/database/data/pph21_2026.php';
        foreach ($this->data['ter'] as $cat => $layers) {
            $over = 0;
            foreach ($layers as [$upto, $rate]) {
                $this->ter[$cat][] = ['gross_over' => $over, 'gross_upto' => $upto, 'rate' => $rate];
                $over = $upto;
            }
        }
        foreach ($this->data['progressive'] as [$upper, $rate]) {
            $this->brackets[] = ['upper_limit' => $upper, 'rate' => $rate];
        }
    }

    // ── TER ─────────────────────────────────────────────────────────────────

    public function test_ter_batas_kategori_a(): void
    {
        $this->assertSame(0.0, P::terRate(5_400_000, $this->ter['A']));
        $this->assertSame(0.25, P::terRate(5_400_001, $this->ter['A']));
        $this->assertSame(0.25, P::terRate(5_650_000, $this->ter['A']));
        $this->assertSame(0.5, P::terRate(5_650_001, $this->ter['A']));
        $this->assertSame(2.0, P::terRate(10_000_000, $this->ter['A']));
        $this->assertSame(34.0, P::terRate(5_000_000_000, $this->ter['A']));
    }

    public function test_ter_bulanan_menurut_status_ptkp(): void
    {
        $a = P::monthlyTer(10_000_000, 'TK/0', $this->ter);
        $this->assertSame(['rate' => 2.0, 'tax' => 200_000.0, 'category' => 'A'], $a);

        $b = P::monthlyTer(7_300_000, 'K/2', $this->ter);          // B: 6.850.000 < x <= 7.300.000 → 0,75%
        $this->assertSame(0.75, $b['rate']);
        $this->assertSame(54_750.0, $b['tax']);

        $c = P::monthlyTer(12_000_000, 'K/3', $this->ter);          // C: 11.200.000 < x <= 12.050.000 → 2%
        $this->assertSame(240_000.0, $c['tax']);
    }

    public function test_ter_dibulatkan_ke_bawah_ke_rupiah(): void
    {
        $this->assertSame(13_500.0, P::monthlyTer(5_400_001, 'TK/0', $this->ter)['tax']); // 0,25% = 13.500,0025
    }

    public function test_status_ptkp_tak_dikenal_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        P::monthlyTer(10_000_000, null, $this->ter);
    }

    public function test_bruto_nol_tidak_ada_pajak(): void
    {
        $this->assertSame(0.0, P::monthlyTer(0, 'TK/0', $this->ter)['tax']);
    }

    // ── Pasal 17 ────────────────────────────────────────────────────────────

    public function test_lapisan_pasal_17(): void
    {
        $this->assertSame(3_000_000.0, P::progressiveTax(60_000_000, $this->brackets));
        $this->assertSame(31_500_000.0, P::progressiveTax(250_000_000, $this->brackets));
        $this->assertSame(44_000_000.0, P::progressiveTax(300_000_000, $this->brackets));
        $this->assertSame(1_479_000_000.0, P::progressiveTax(5_100_000_000, $this->brackets));
        $this->assertSame(0.0, P::progressiveTax(0, $this->brackets));
    }

    public function test_pkp_dibulatkan_ke_bawah_ke_ribuan(): void
    {
        // 60.999.999 → 60.999.000: 3.000.000 + 15% × 999.000
        $this->assertSame(3_149_850.0, P::progressiveTax(60_999_999, $this->brackets));
    }

    public function test_biaya_jabatan_maksimal_500rb_per_bulan(): void
    {
        $this->assertSame(6_000_000.0, P::occupationalCost(120_000_000, 12, 5, 500_000));
        $this->assertSame(1_500_000.0, P::occupationalCost(30_000_000, 12, 5, 500_000));   // 5% < batas
        $this->assertSame(1_500_000.0, P::occupationalCost(120_000_000, 3, 5, 500_000));   // 3 bulan × 500rb
    }

    // ── Setahun & true-up ───────────────────────────────────────────────────

    private function annualInput(): array
    {
        return [
            'annual_gross' => 120_000_000, 'months' => 12, 'employee_pension' => 3_600_000,
            'ptkp_annual' => 54_000_000,
            'occupational_cost_rate' => 5, 'occupational_cost_monthly_max' => 500_000,
        ];
    }

    public function test_pajak_setahun_tk0_gaji_10_juta(): void
    {
        $r = P::annualTax($this->annualInput(), $this->brackets);

        $this->assertSame(110_400_000.0, $r['net_income']);   // 120jt − 6jt − 3,6jt
        $this->assertSame(56_400_000.0, $r['pkp']);            // − PTKP 54jt
        $this->assertSame(2_820_000.0, $r['tax']);             // 5%
    }

    public function test_true_up_desember_mengurangi_potongan_ter_jan_nov(): void
    {
        $withheld = 11 * 200_000.0;                            // TER 2% × 10jt × 11 bulan
        $r = P::finalPeriodTrueUp($this->annualInput(), $this->brackets, $withheld);

        $this->assertSame(620_000.0, $r['payable']);
        $this->assertFalse($r['overpaid']);
    }

    public function test_true_up_negatif_ditandai_kelebihan_potong(): void
    {
        $r = P::finalPeriodTrueUp($this->annualInput(), $this->brackets, 3_000_000.0);

        $this->assertSame(-180_000.0, $r['payable']);
        $this->assertTrue($r['overpaid']);
    }

    public function test_pkp_negatif_menjadi_nol(): void
    {
        $in = ['annual_gross' => 40_000_000, 'ptkp_annual' => 54_000_000] + $this->annualInput();
        $r = P::annualTax($in, $this->brackets);

        $this->assertSame(0.0, $r['pkp']);
        $this->assertSame(0.0, $r['tax']);
    }

    // ── Kebersihan data tabel ───────────────────────────────────────────────

    public function test_data_ter_menerus_tanpa_celah_dan_tarif_naik(): void
    {
        foreach ($this->data['ter'] as $cat => $layers) {
            $prevUpper = 0;
            $prevRate = -1;
            foreach ($layers as $i => [$upto, $rate]) {
                $this->assertGreaterThan($prevRate, $rate, "$cat baris $i: tarif harus naik");
                if ($upto !== null) {
                    $this->assertGreaterThan($prevUpper, $upto, "$cat baris $i: batas harus naik");
                    $prevUpper = $upto;
                } else {
                    $this->assertSame(count($layers) - 1, $i, "$cat: hanya baris terakhir yang terbuka");
                }
                $prevRate = $rate;
            }
            $this->assertSame(34, end($layers)[1], "$cat: tarif tertinggi 34%");
        }
    }

    public function test_jumlah_lapisan_sesuai_lampiran(): void
    {
        $this->assertCount(44, $this->data['ter']['A']);
        $this->assertCount(40, $this->data['ter']['B']);
        $this->assertCount(41, $this->data['ter']['C']);
    }

    public function test_ptkp_2026_konsisten_dengan_kode(): void
    {
        $ptkp = array_column($this->data['ptkp'], 2, 0);
        $this->assertSame(54_000_000, $ptkp['TK/0']);
        foreach (range(1, 3) as $n) {
            $this->assertSame($ptkp['TK/0'] + 4_500_000 * $n, $ptkp["TK/$n"]);
            $this->assertSame($ptkp['TK/0'] + 4_500_000 * ($n + 1), $ptkp["K/$n"]);
        }
        $this->assertSame($ptkp['TK/0'] + 4_500_000, $ptkp['K/0']);
    }
}
