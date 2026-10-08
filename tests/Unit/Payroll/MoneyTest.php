<?php

namespace Tests\Unit\Payroll;

use App\Support\Payroll\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_format_titik_ribuan_koma_desimal(): void
    {
        $this->assertSame('1.000.000,00', Money::format(1_000_000));
        $this->assertSame('54.000.000,00', Money::format(54000000.0));
        $this->assertSame('0,00', Money::format(0));
        $this->assertSame('1.234,50', Money::format('1234.5'));
        $this->assertSame('1.000.000', Money::format(1_000_000, 0));
        $this->assertSame('', Money::format(null));
        $this->assertSame('Rp 2.500.000,00', Money::rupiah(2_500_000));
    }

    public function test_parse_format_indonesia(): void
    {
        $this->assertSame(1_000_000.0, Money::parse('1.000.000,00'));
        $this->assertSame(1_234.5, Money::parse('1.234,50'));
        $this->assertSame(1_000_000.0, Money::parse('1.000.000'));
        $this->assertSame(1_500_000.0, Money::parse('Rp 1.500.000,00'));
        $this->assertSame(0.5, Money::parse('0,5'));
    }

    public function test_parse_angka_polos(): void
    {
        $this->assertSame(1_000_000.0, Money::parse('1000000'));
        $this->assertSame(1_000_000.5, Money::parse('1000000.5'));
        $this->assertSame(250.0, Money::parse(250));
        $this->assertSame(-5.0, Money::parse('-5'));
    }

    public function test_parse_menolak_bukan_angka(): void
    {
        $this->assertNull(Money::parse(''));
        $this->assertNull(Money::parse(null));
        $this->assertNull(Money::parse('abc'));
        $this->assertNull(Money::parse('1.2.3,4,5'));
        $this->assertNull(Money::parse('-'));
    }

    public function test_bolak_balik_tidak_berubah(): void
    {
        foreach ([0, 5400000, 1234567.89, 1_419_000_000] as $n) {
            $this->assertSame((float) $n, Money::parse(Money::format($n)));
        }
    }
}
