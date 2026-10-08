<?php

namespace Tests\Unit\Payroll;

use App\Support\Payroll\BpjsCalculator as B;
use App\Support\Payroll\BpjsSettingRules as R;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class BpjsCalculatorTest extends TestCase
{
    private array $versions;
    private array $s;

    protected function setUp(): void
    {
        $this->versions = require dirname(__DIR__, 3) . '/database/data/bpjs_2026.php';
        $this->s = R::effectiveOn($this->versions, '2026-06-30');
    }

    private const ON = ['health_active' => true, 'employment_active' => true, 'dependents' => 0];

    public function test_upah_10_juta_semua_program(): void
    {
        $r = B::calculate(10_000_000, $this->s, self::ON);

        $this->assertSame(400_000.0, $r['health']['employer']);   // 4%
        $this->assertSame(100_000.0, $r['health']['employee']);   // 1%
        $this->assertSame(370_000.0, $r['jht']['employer']);      // 3,7%
        $this->assertSame(200_000.0, $r['jht']['employee']);      // 2%
        $this->assertSame(200_000.0, $r['jp']['employer']);       // 2%, di bawah batas 11.086.300
        $this->assertSame(100_000.0, $r['jp']['employee']);       // 1%
        $this->assertSame(24_000.0, $r['jkk']['employer']);       // 0,24%
        $this->assertSame(30_000.0, $r['jkm']['employer']);       // 0,3%
        $this->assertSame(400_000 + 370_000 + 200_000 + 24_000 + 30_000.0, $r['totals']['employer']);
        $this->assertSame(100_000 + 200_000 + 100_000.0, $r['totals']['employee']);
        $this->assertSame(300_000.0, $r['totals']['employee_pension']);
        $this->assertSame(454_000.0, $r['totals']['employer_premiums_for_tax']);
    }

    public function test_batas_atas_kesehatan_dan_jp(): void
    {
        $r = B::calculate(20_000_000, $this->s, self::ON);

        $this->assertSame(12_000_000.0, $r['health']['base']);
        $this->assertSame(480_000.0, $r['health']['employer']);
        $this->assertSame(11_086_300.0, $r['jp']['base']);
        $this->assertSame(221_726.0, $r['jp']['employer']);       // 2% × 11.086.300 = 221.726
        $this->assertSame(110_863.0, $r['jp']['employee']);       // 1% = 110.863
        $this->assertSame(740_000.0, $r['jht']['employer']);      // JHT tanpa batas atas
    }

    public function test_batas_jp_mengikuti_versi_berlaku_pada_tanggal(): void
    {
        $feb = R::effectiveOn($this->versions, '2026-02-28');
        $this->assertSame(10_547_400.0, (float) $feb['jp_cap']);
        $mar = R::effectiveOn($this->versions, '2026-03-01');
        $this->assertSame(11_086_300.0, (float) $mar['jp_cap']);
    }

    public function test_tanpa_versi_berlaku_dilempar(): void
    {
        $this->expectException(InvalidArgumentException::class);
        R::effectiveOn($this->versions, '2025-12-31');
    }

    public function test_batas_bawah_kesehatan_dipakai_bila_upah_lebih_kecil(): void
    {
        $s = ['health_min_base' => 2_700_000] + $this->s;
        $r = B::calculate(2_000_000, $s, self::ON);

        $this->assertSame(2_700_000.0, $r['health']['base']);
        $this->assertSame(108_000.0, $r['health']['employer']);
    }

    public function test_anggota_keluarga_tambahan_ditanggung_pekerja(): void
    {
        $r = B::calculate(10_000_000, $this->s, ['dependents' => 2] + self::ON);

        $this->assertSame(200_000.0, $r['health']['dependents_amount']);   // 2 × 1% × 10 juta
        $this->assertSame(100_000 + 200_000 + 200_000 + 100_000.0, $r['totals']['employee']);
    }

    public function test_penanda_karyawan_mati_atau_kosong_tidak_dihitung(): void
    {
        $off = B::calculate(10_000_000, $this->s, ['health_active' => false, 'employment_active' => null, 'dependents' => 0]);

        $this->assertSame(0.0, $off['totals']['employer']);
        $this->assertSame(0.0, $off['totals']['employee']);
        $this->assertFalse($off['health']['active']);
    }

    public function test_saklar_perusahaan_mematikan_kelompok_program(): void
    {
        $s = ['health_in_payroll' => false] + $this->s;
        $r = B::calculate(10_000_000, $s, self::ON);

        $this->assertSame(0.0, $r['health']['employer']);
        $this->assertGreaterThan(0, $r['jht']['employer']);
    }

    public function test_pembulatan_ke_rupiah_penuh(): void
    {
        $r = B::calculate(5_333_333, $this->s, self::ON);   // 4% = 213.333,32 → 213.333

        $this->assertSame(213_333.0, $r['health']['employer']);
    }

    public function test_upah_negatif_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        B::calculate(-1, $this->s, self::ON);
    }

    // ── Validasi pengaturan ─────────────────────────────────────────────────

    private function input(array $over = []): array
    {
        return $over + [
            'effective_date' => '2026-09-01',
            'health_employer_rate' => 4, 'health_employee_rate' => 1, 'health_min_base' => 0, 'health_cap' => 12_000_000,
            'jht_employer_rate' => 3.7, 'jht_employee_rate' => 2,
            'jp_employer_rate' => 2, 'jp_employee_rate' => 1, 'jp_cap' => 11_086_300,
            'jkk_rate' => 0.24, 'jkm_rate' => 0.3, 'notes' => '',
        ];
    }

    public function test_pengaturan_sah(): void
    {
        $this->assertSame([], R::validate($this->input(), ['2026-01-01']));
    }

    public function test_tanggal_berlaku_ganda_ditolak(): void
    {
        $this->assertNotEmpty(R::validate($this->input(['effective_date' => '2026-01-01']), ['2026-01-01']));
    }

    public function test_tanggal_tidak_sah_ditolak(): void
    {
        $this->assertNotEmpty(R::validate($this->input(['effective_date' => '2026-02-30']), []));
        $this->assertNotEmpty(R::validate($this->input(['effective_date' => '']), []));
    }

    public function test_jp_nol_wajib_ada_catatan(): void
    {
        $this->assertNotEmpty(R::validate($this->input(['jp_employee_rate' => 0]), []));
        $this->assertSame([], R::validate($this->input(['jp_employee_rate' => 0, 'jp_employer_rate' => 0, 'notes' => 'Company not enrolled in JP yet']), []));
    }

    public function test_tarif_dan_batas_diperiksa(): void
    {
        $this->assertNotEmpty(R::validate($this->input(['health_employer_rate' => 101]), []));
        $this->assertNotEmpty(R::validate($this->input(['jkm_rate' => -1]), []));
        $this->assertNotEmpty(R::validate($this->input(['health_min_base' => 13_000_000]), []));
        $this->assertNotEmpty(R::validate($this->input(['jp_cap' => 0]), []));
        $this->assertNotEmpty(R::validate($this->input(['health_cap' => 'abc']), []));
    }
}
