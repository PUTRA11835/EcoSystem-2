<?php

namespace Tests\Unit\Onboarding;

use App\Services\Onboarding\JoinDateRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Uji unit aturan murni alat join date HR (HC-D64): pembacaan tanggal gaya Indonesia (hari/bulan/tahun),
 * rentang wajar, dan pemecahan teks tempel/CSV.
 */
class JoinDateRulesTest extends TestCase
{
    #[DataProvider('tanggalSah')]
    public function test_format_tanggal_dibaca_sebagai_hari_bulan_tahun(string $raw, string $iso): void
    {
        $this->assertSame($iso, JoinDateRules::parseDate($raw), $raw);
    }

    public static function tanggalSah(): array
    {
        return [
            ['2026-10-05', '2026-10-05'],
            ['05/10/2026', '2026-10-05'],   // 5 Oktober, BUKAN 10 Mei
            ['5/10/2026', '2026-10-05'],
            ['05-10-2026', '2026-10-05'],
            ['05.10.2026', '2026-10-05'],
            ['5 Oct 2026', '2026-10-05'],
            ['05 October 2026', '2026-10-05'],
            ['  31/12/2023  ', '2023-12-31'],
        ];
    }

    #[DataProvider('tanggalTakSah')]
    public function test_tanggal_tak_sah_ditolak(?string $raw): void
    {
        $this->assertNull(JoinDateRules::parseDate($raw), (string) $raw);
    }

    public static function tanggalTakSah(): array
    {
        return [[null], [''], ['bukan tanggal'], ['31/02/2026'], ['32/01/2026'], ['2026-13-01'], ['10/2026'], ['2026/10/05x']];
    }

    public function test_rentang_wajar(): void
    {
        $today = new \DateTimeImmutable('2026-10-05');
        $this->assertNull(JoinDateRules::rangeError('2026-10-05', $today));
        $this->assertNull(JoinDateRules::rangeError('2026-11-04', $today));      // tepat 30 hari ke depan
        $this->assertSame('in_future', JoinDateRules::rangeError('2026-11-05', $today));
        $this->assertSame('too_old', JoinDateRules::rangeError('1989-12-31', $today));
        $this->assertNull(JoinDateRules::rangeError('1990-01-01', $today));
    }

    public function test_pecah_csv_koma_titik_koma_dan_tab(): void
    {
        $today = new \DateTimeImmutable('2026-10-05');
        foreach ([",", ";", "\t"] as $d) {
            $rows = JoinDateRules::parseCsv("C26001{$d} 01/03/2023\nC26002{$d}2022-07-15", $today);
            $this->assertCount(2, $rows, "pemisah " . json_encode($d));
            $this->assertSame(['C26001', '2023-03-01', null], [$rows[0]['eci'], $rows[0]['date'], $rows[0]['error']]);
            $this->assertSame(['C26002', '2022-07-15', null], [$rows[1]['eci'], $rows[1]['date'], $rows[1]['error']]);
        }
    }

    public function test_baris_judul_bom_kutip_dan_baris_kosong(): void
    {
        $today = new \DateTimeImmutable('2026-10-05');
        $rows = JoinDateRules::parseCsv("\xEF\xBB\xBF\"ECI\",\"Join date\"\r\n\r\n\"C1\",\"05/10/2026\"\r\n   \r\nC2,2024-01-31\n", $today);
        $this->assertCount(2, $rows, 'judul + baris kosong dilewati');
        $this->assertSame('C1', $rows[0]['eci']);
        $this->assertSame('2026-10-05', $rows[0]['date']);
        $this->assertSame(3, $rows[0]['line'], 'nomor baris mengikuti berkas asli');
    }

    public function test_kode_galat_per_baris(): void
    {
        $today = new \DateTimeImmutable('2026-10-05');
        $rows = JoinDateRules::parseCsv(",05/10/2026\nC1,\nC2,bukan\nC3,01/01/1980\nC4,01/01/2030\nC5,05/10/2026", $today);
        $this->assertSame(['missing_eci', 'missing_date', 'invalid_date', 'too_old', 'in_future', null], array_column($rows, 'error'));
    }

    public function test_jumlah_baris_dibatasi(): void
    {
        $text = implode("\n", array_map(fn ($i) => "C{$i},2024-01-01", range(1, JoinDateRules::MAX_ROWS + 50)));
        $this->assertCount(JoinDateRules::MAX_ROWS, JoinDateRules::parseCsv($text, new \DateTimeImmutable('2026-10-05')));
    }
}
