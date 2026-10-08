<?php

namespace Tests\Unit\Payroll;

use App\Support\Payroll\PtkpRules;
use PHPUnit\Framework\TestCase;

class PtkpRulesTest extends TestCase
{
    public function test_kode_dinormalkan_dan_tanggungan_diturunkan_dari_kode(): void
    {
        $this->assertSame('TK/0', PtkpRules::normalize(' tk/0 '));
        $this->assertSame('K/2', PtkpRules::normalize('k / 2'));
        $this->assertSame(2, PtkpRules::dependents('K/2'));
        $this->assertSame(0, PtkpRules::dependents('TK/0'));
        $this->assertNull(PtkpRules::normalize('K/4'));
        $this->assertNull(PtkpRules::dependents(''));
    }

    public function test_kategori_ter_sesuai_pmk_168_2023(): void
    {
        foreach (['TK/0', 'TK/1', 'K/0'] as $c) {
            $this->assertSame('A', PtkpRules::terCategory($c), $c);
        }
        foreach (['TK/2', 'TK/3', 'K/1', 'K/2'] as $c) {
            $this->assertSame('B', PtkpRules::terCategory($c), $c);
        }
        $this->assertSame('C', PtkpRules::terCategory('K/3'));
        $this->assertNull(PtkpRules::terCategory('X'));
    }

    public function test_semua_kode_punya_kategori(): void
    {
        $this->assertCount(8, PtkpRules::CODES);
        foreach (PtkpRules::CODES as $c) {
            $this->assertNotNull(PtkpRules::terCategory($c));
        }
    }

    public function test_status_kawin(): void
    {
        $this->assertTrue(PtkpRules::isMarried('K/1'));
        $this->assertFalse(PtkpRules::isMarried('TK/1'));
    }

    public function test_tanggungan_bpjs(): void
    {
        $this->assertSame(0, PtkpRules::normalizeBpjsDependents('0'));
        $this->assertSame(5, PtkpRules::normalizeBpjsDependents(5));
        $this->assertNull(PtkpRules::normalizeBpjsDependents(6));
        $this->assertNull(PtkpRules::normalizeBpjsDependents('a'));
        $this->assertNull(PtkpRules::normalizeBpjsDependents(1.5));
    }
}
