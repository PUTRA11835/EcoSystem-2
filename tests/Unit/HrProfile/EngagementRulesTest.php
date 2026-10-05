<?php

namespace Tests\Unit\HrProfile;

use App\Services\HrProfile\EngagementRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Aturan murni blok Engagement konsultan (HC-D47). Tanpa DB. */
class EngagementRulesTest extends TestCase
{
    #[DataProvider('rateProvider')]
    public function test_normalisasi_tarif(string $raw, ?string $expected): void
    {
        $this->assertSame($expected, EngagementRules::normalizeRate($raw), $raw);
    }

    public static function rateProvider(): array
    {
        return [
            'format ESH'            => ['2.700.000,00', '2700000.00'],
            'format Inggris'        => ['2,700,000.50', '2700000.50'],
            'polos'                 => ['2700000', '2700000.00'],
            'ribuan satu pemisah'   => ['2.700', '2700.00'],
            'desimal satu digit'    => ['2.5', '2.50'],
            'koma desimal'          => ['1500,75', '1500.75'],
            'spasi dibuang'         => [' 2 700 000 ', '2700000.00'],
            'nol'                   => ['0', '0.00'],
            'huruf'                 => ['abc', null],
            'negatif'               => ['-5', null],
            'kosong'                => ['', null],
            'tiga desimal'          => ['1,5000', null],
            'terlalu besar'         => ['99999999999999', null],
            'batas atas 13 digit'   => ['9999999999999', '9999999999999.00'],
            'simbol mata uang'      => ['Rp 1.000', null],
        ];
    }

    public function test_input_sah_dinormalkan_dan_mata_uang_bawaan_idr(): void
    {
        [$c, $e] = EngagementRules::clean([
            'engagement_scheme' => 'mandays', 'client_company' => ' Eclectic ', 'rate' => '2.700.000,00',
            'start_date' => '2026-01-09', 'end_date' => '2026-10-31',
        ]);

        $this->assertSame([], $e);
        $this->assertSame('Eclectic', $c['client_company']);
        $this->assertSame('2700000.00', $c['rate']);
        $this->assertSame('IDR', $c['currency']);
    }

    public function test_mata_uang_eksplisit_dipertahankan_dan_tidak_sah_ditolak(): void
    {
        [$c] = EngagementRules::clean(['rate' => '100', 'currency' => 'usd']);
        $this->assertSame('USD', $c['currency']);

        [, $e] = EngagementRules::clean(['currency' => 'XYZ']);
        $this->assertArrayHasKey('currency', $e);
    }

    public function test_skema_asing_tanggal_tidak_sah_dan_teks_terlalu_panjang_ditolak(): void
    {
        [, $e] = EngagementRules::clean([
            'engagement_scheme' => 'borongan', 'start_date' => '31/12/2026', 'vendor_partner' => str_repeat('a', 151),
        ]);

        $this->assertArrayHasKey('engagement_scheme', $e);
        $this->assertArrayHasKey('start_date', $e);
        $this->assertArrayHasKey('vendor_partner', $e);
    }

    public function test_selesai_tidak_boleh_sebelum_mulai_termasuk_terhadap_nilai_tersimpan(): void
    {
        [, $e] = EngagementRules::clean(['start_date' => '2026-05-01', 'end_date' => '2026-04-30']);
        $this->assertArrayHasKey('end_date', $e);

        // hanya end_date dikirim; start tersimpan lebih akhir
        [, $e] = EngagementRules::clean(['end_date' => '2026-01-01'], '2026-06-01', null);
        $this->assertArrayHasKey('end_date', $e);

        [, $e] = EngagementRules::clean(['end_date' => '2026-12-01'], '2026-06-01', null);
        $this->assertSame([], $e);
    }

    public function test_kosong_berarti_menghapus_nilai(): void
    {
        [$c, $e] = EngagementRules::clean(['vendor_partner' => '  ', 'rate' => '']);
        $this->assertSame([], $e);
        $this->assertNull($c['vendor_partner']);
        $this->assertNull($c['rate']);
    }

    public function test_karakter_kontrol_ditolak(): void
    {
        [, $e] = EngagementRules::clean(['assignment_role' => "Konsultan\x00Keuangan"]);
        $this->assertArrayHasKey('assignment_role', $e);
    }

    public function test_pemisahan_field_dasar_dan_tarif(): void
    {
        $this->assertSame([], array_intersect(EngagementRules::BASIC_FIELDS, EngagementRules::RATE_FIELDS));
        $this->assertSame(['rate', 'currency'], EngagementRules::RATE_FIELDS);
    }
}
