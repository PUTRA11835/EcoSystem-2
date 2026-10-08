<?php

namespace Tests\Unit\Payroll;

use App\Support\Payroll\SalaryComponentRules as R;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Uji aturan komponen gaji (murni, tanpa database). */
class SalaryComponentRulesTest extends TestCase
{
    private function row(string $cat, float $amt, string $from, ?string $to = null, array $extra = []): array
    {
        return array_merge([
            'category' => $cat, 'amount' => $amt, 'effective_from' => $from,
            'effective_to' => $to, 'is_active' => true,
        ], $extra);
    }

    public function test_gaji_pokok_pada_tanggal_dipilih_berdasarkan_rentang(): void
    {
        $rows = [
            $this->row(R::BASE, 5_000_000, '2026-01-01', '2026-06-30'),
            $this->row(R::BASE, 6_000_000, '2026-07-01'),
        ];

        $this->assertSame(5_000_000.0, R::baseSalaryOn($rows, '2026-06-30'));
        $this->assertSame(6_000_000.0, R::baseSalaryOn($rows, '2026-07-01'));
    }

    public function test_tanpa_gaji_pokok_aktif_dilempar(): void
    {
        $this->expectException(InvalidArgumentException::class);
        R::baseSalaryOn([$this->row(R::BASE, 1, '2026-01-01', null, ['is_active' => false])], '2026-02-01');
    }

    public function test_dua_gaji_pokok_pada_tanggal_sama_dianggap_data_rusak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        R::baseSalaryOn([
            $this->row(R::BASE, 1, '2026-01-01'),
            $this->row(R::BASE, 2, '2026-03-01'),
        ], '2026-04-01');
    }

    public function test_validasi_menolak_gaji_pokok_tumpang_tindih(): void
    {
        $others = [$this->row(R::BASE, 5_000_000, '2026-01-01')];
        $errors = R::validate($this->row(R::BASE, 6_000_000, '2026-07-01'), $others);

        $this->assertNotEmpty($errors);
    }

    public function test_validasi_menerima_gaji_pokok_baru_setelah_yang_lama_diakhiri(): void
    {
        $others = [$this->row(R::BASE, 5_000_000, '2026-01-01', '2026-06-30')];

        $this->assertSame([], R::validate($this->row(R::BASE, 6_000_000, '2026-07-01'), $others));
    }

    public function test_tunjangan_boleh_banyak_dan_tidak_dicek_tumpang_tindih(): void
    {
        $others = [$this->row(R::FIXED_ALLOWANCE, 500_000, '2026-01-01')];

        $this->assertSame([], R::validate($this->row(R::FIXED_ALLOWANCE, 500_000, '2026-01-01'), $others));
    }

    public function test_validasi_dasar(): void
    {
        $this->assertNotEmpty(R::validate($this->row('x', 1, '2026-01-01'), []));
        $this->assertNotEmpty(R::validate($this->row(R::BASE, -1, '2026-01-01'), []));
        $this->assertNotEmpty(R::validate($this->row(R::BASE, 1, '2026-02-01', '2026-01-01'), []));
    }

    public function test_total_memisahkan_dasar_bpjs_dan_kena_pajak(): void
    {
        $rows = [
            $this->row(R::BASE, 5_000_000, '2026-01-01'),
            $this->row(R::FIXED_ALLOWANCE, 1_000_000, '2026-01-01'),
            $this->row(R::VARIABLE_ALLOWANCE, 500_000, '2026-01-01', null, ['bpjs_base' => false]),
            $this->row(R::DEDUCTION, 200_000, '2026-01-01'),
        ];

        $t = R::totalsOn($rows, '2026-02-01');

        $this->assertSame(6_500_000.0, $t['earnings']);
        $this->assertSame(200_000.0, $t['deductions']);
        $this->assertSame(6_000_000.0, $t['bpjs_wage']);
        $this->assertSame(6_500_000.0, $t['taxable']);
    }
}
