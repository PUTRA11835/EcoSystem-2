<?php

namespace Tests\Unit\Payroll;

use App\Support\Payroll\Pph21RateRules as R;
use PHPUnit\Framework\TestCase;

/** Uji validasi isian halaman PPh 21 Settings (murni). */
class Pph21RateRulesTest extends TestCase
{
    private const PTKP = [
        'TK/0' => 54_000_000, 'TK/1' => 58_500_000, 'TK/2' => 63_000_000, 'TK/3' => 67_500_000,
        'K/0' => 58_500_000, 'K/1' => 63_000_000, 'K/2' => 67_500_000, 'K/3' => 72_000_000,
    ];

    public function test_ptkp_resmi_2026_sah(): void
    {
        $this->assertSame([], R::validatePtkp(self::PTKP));
    }

    public function test_ptkp_langkah_tidak_seragam_ditolak(): void
    {
        $this->assertNotEmpty(R::validatePtkp(['K/3' => 99_000_000] + self::PTKP));
    }

    public function test_ptkp_kurang_kode_atau_negatif_ditolak(): void
    {
        $partial = self::PTKP;
        unset($partial['K/2']);
        $this->assertNotEmpty(R::validatePtkp($partial));
        $this->assertNotEmpty(R::validatePtkp(['TK/0' => -1] + self::PTKP));
    }

    public function test_lapisan_sah_dan_tidak_sah(): void
    {
        $ok = [
            ['upper_limit' => 60_000_000, 'rate' => 5], ['upper_limit' => 250_000_000, 'rate' => 15],
            ['upper_limit' => null, 'rate' => 25],
        ];
        $this->assertSame([], R::validateBrackets($ok));

        $notAscending = $ok;
        $notAscending[1]['upper_limit'] = 50_000_000;
        $this->assertNotEmpty(R::validateBrackets($notAscending));

        $rateDrops = $ok;
        $rateDrops[2]['rate'] = 10;
        $this->assertNotEmpty(R::validateBrackets($rateDrops));

        $lastClosed = $ok;
        $lastClosed[2]['upper_limit'] = 999;
        $this->assertNotEmpty(R::validateBrackets($lastClosed));

        $this->assertNotEmpty(R::validateBrackets([]));
        $this->assertNotEmpty(R::validateBrackets([['upper_limit' => null, 'rate' => 101]]));
    }

    public function test_data_resmi_ter_lolos_validasi(): void
    {
        $data = require dirname(__DIR__, 3) . '/database/data/pph21_2026.php';
        foreach ($data['ter'] as $cat => $layers) {
            $rows = array_map(fn ($l) => ['upper_limit' => $l[0], 'rate' => $l[1]], $layers);
            $this->assertSame([], R::validateTerCategory($rows, $cat), "TER $cat");
        }
    }

    public function test_pengaturan_biaya_jabatan(): void
    {
        $this->assertSame([], R::validateSettings(['occupational_cost_rate' => 5, 'occupational_cost_monthly_max' => 500_000]));
        $this->assertNotEmpty(R::validateSettings(['occupational_cost_rate' => 120, 'occupational_cost_monthly_max' => 1]));
        $this->assertNotEmpty(R::validateSettings(['occupational_cost_rate' => 5, 'occupational_cost_monthly_max' => -1]));
        $this->assertNotEmpty(R::validateSettings([]));
    }
}
