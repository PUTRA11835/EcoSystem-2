<?php

namespace Tests\Unit\CashAdvance;

use App\Models\CashAdvance\CashAdvance;
use App\Models\CashAdvance\CashAdvanceReport;
use App\Models\CashAdvance\CashAdvanceSetting;
use App\Services\CashAdvance\CashAdvanceAmountService;
use PHPUnit\Framework\TestCase;

/**
 * Uji unit CashAdvanceAmountService.
 *
 * Sengaja memakai PHPUnit\Framework\TestCase polos, BUKAN Tests\TestCase milik
 * Laravel: service ini tidak menyentuh database maupun container. Model setelan
 * hanya dibuat di memori dan tidak pernah disimpan.
 *
 * 🔴 Ini modul yang mengeluarkan uang perusahaan. Salah hitung di sini tidak
 * muncul sebagai galat — ia muncul sebagai angka keliru di dokumen yang sudah
 * ditandatangani. Itulah alasan berkas ini ada.
 */
class CashAdvanceAmountServiceTest extends TestCase
{
    private CashAdvanceAmountService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->svc = new CashAdvanceAmountService();
    }

    /** Setelan di memori dengan nilai bawaan yang sama dengan migrasi. */
    private function setting(array $overrides = []): CashAdvanceSetting
    {
        return new CashAdvanceSetting(array_merge([
            'min_amount'            => 0,
            'max_amount'            => 0,
            'over_limit_policy'     => CashAdvanceSetting::LIMIT_FLAG,
            'car_allow_over_amount' => true,
            'car_due_days'          => 0,
        ], $overrides));
    }

    // ── parseAmount: membaca nominal yang diketik ───────────────────────────

    /**
     * 🔴 Uji yang paling penting di berkas ini.
     *
     * Form acuan menampilkan "100.000". PHP membaca `(float) "100.000"` sebagai
     * 100,0 — seratus rupiah, bukan seratus ribu. Kesalahan itu tidak pernah
     * memunculkan galat; ia hanya membuat kas tidak cocok.
     */
    public function test_titik_dibaca_sebagai_pemisah_ribuan_gaya_indonesia(): void
    {
        $this->assertSame(100000.0, $this->svc->parseAmount('100.000'));
        $this->assertSame(350000.0, $this->svc->parseAmount('350.000'));
        $this->assertSame(1500000.0, $this->svc->parseAmount('1.500.000'));
        $this->assertSame(12345678.0, $this->svc->parseAmount('12.345.678'));
    }

    public function test_titik_dibaca_sebagai_desimal_bila_bentuknya_bukan_kelompok_tiga(): void
    {
        // "100.5" bukan pola ribuan (kelompoknya bukan tiga digit) -> desimal.
        $this->assertSame(100.5, $this->svc->parseAmount('100.5'));
        $this->assertSame(100000.5, $this->svc->parseAmount('100000.50'));
        $this->assertSame(0.75, $this->svc->parseAmount('0.75'));
    }

    public function test_koma_dibaca_sebagai_desimal_gaya_indonesia(): void
    {
        $this->assertSame(350000.5, $this->svc->parseAmount('350000,50'));
        $this->assertSame(1.25, $this->svc->parseAmount('1,25'));
    }

    public function test_koma_dibaca_sebagai_ribuan_bila_bentuknya_kelompok_tiga(): void
    {
        $this->assertSame(1500000.0, $this->svc->parseAmount('1,500,000'));
    }

    public function test_titik_dan_koma_bersama_pemisah_terakhir_adalah_desimal(): void
    {
        // Gaya Indonesia: titik ribuan, koma desimal.
        $this->assertSame(1500.5, $this->svc->parseAmount('1.500,50'));
        // Gaya Inggris: koma ribuan, titik desimal. Keduanya dibaca benar.
        $this->assertSame(1500.5, $this->svc->parseAmount('1,500.50'));
    }

    public function test_simbol_mata_uang_dan_spasi_dibuang(): void
    {
        $this->assertSame(1500000.0, $this->svc->parseAmount('Rp 1.500.000'));
        $this->assertSame(350000.0, $this->svc->parseAmount('  350.000  '));
        $this->assertSame(350000.0, $this->svc->parseAmount('IDR350.000'));
    }

    public function test_angka_yang_sudah_berupa_bilangan_lewat_apa_adanya(): void
    {
        $this->assertSame(1500000.0, $this->svc->parseAmount(1500000));
        $this->assertSame(1500.55, $this->svc->parseAmount(1500.554));
    }

    public function test_masukan_kosong_dan_tak_bermakna_menjadi_nol(): void
    {
        $this->assertSame(0.0, $this->svc->parseAmount(''));
        $this->assertSame(0.0, $this->svc->parseAmount('   '));
        $this->assertSame(0.0, $this->svc->parseAmount('abc'));
        $this->assertSame(0.0, $this->svc->parseAmount('-'));
    }

    public function test_nilai_negatif_tetap_negatif(): void
    {
        $this->assertSame(-1500.0, $this->svc->parseAmount('-1.500'));
        $this->assertSame(-1500.5, $this->svc->parseAmount('-1.500,50'));
    }

    // ── format ──────────────────────────────────────────────────────────────

    public function test_format_memakai_gaya_indonesia(): void
    {
        $this->assertSame('350.000,00', $this->svc->format(350000));
        $this->assertSame('350.000', $this->svc->format(350000, false));
        $this->assertSame('1.500.000,50', $this->svc->format(1500000.5));
    }

    public function test_parse_lalu_format_bolak_balik_tidak_mengubah_nilai(): void
    {
        foreach (['100.000', '1.500.000', '350.000,50', '99,99'] as $input) {
            $value = $this->svc->parseAmount($input);
            $this->assertSame($value, $this->svc->parseAmount($this->svc->format($value)));
        }
    }

    // ── settlement: SYARAT MENGIKAT D143 ────────────────────────────────────

    public function test_satu_laporan_realisasi_lebih_kecil_menghasilkan_refund(): void
    {
        $result = $this->svc->settlement(350000, [300000]);

        $this->assertSame(300000.0, $result['reported']);
        $this->assertSame(50000.0, $result['outstanding']);
        $this->assertSame(CashAdvanceReport::SETTLEMENT_REFUND, $result['type']);
    }

    public function test_realisasi_lebih_besar_menghasilkan_claim_dengan_sisa_negatif(): void
    {
        $result = $this->svc->settlement(350000, [400000]);

        $this->assertSame(-50000.0, $result['outstanding']);
        $this->assertSame(CashAdvanceReport::SETTLEMENT_CLAIM, $result['type']);
    }

    public function test_realisasi_pas_menghasilkan_exact_bukan_refund_nol(): void
    {
        $result = $this->svc->settlement(350000, [350000]);

        $this->assertSame(0.0, $result['outstanding']);
        $this->assertSame(CashAdvanceReport::SETTLEMENT_EXACT, $result['type']);
    }

    /**
     * 🔴 Inti Keputusan D143.
     *
     * Perhitungan menerima ARRAY, bukan satu angka, supaya menyalakan setelan
     * `car_multiple_per_ca` kelak tidak diam-diam menghasilkan sisa uang yang
     * salah. Uji ini yang menjaga sifat itu tetap ada.
     */
    public function test_beberapa_laporan_dijumlahkan_bukan_diambil_yang_terakhir(): void
    {
        $result = $this->svc->settlement(1000000, [300000, 250000, 200000]);

        $this->assertSame(750000.0, $result['reported'], 'ketiganya harus dijumlahkan');
        $this->assertSame(250000.0, $result['outstanding']);
        $this->assertSame(CashAdvanceReport::SETTLEMENT_REFUND, $result['type']);
    }

    public function test_beberapa_laporan_yang_totalnya_melebihi_menghasilkan_claim(): void
    {
        $result = $this->svc->settlement(1000000, [600000, 600000]);

        $this->assertSame(1200000.0, $result['reported']);
        $this->assertSame(-200000.0, $result['outstanding']);
        $this->assertSame(CashAdvanceReport::SETTLEMENT_CLAIM, $result['type']);
    }

    public function test_tanpa_laporan_seluruh_nominal_masih_menggantung(): void
    {
        $result = $this->svc->settlement(350000, []);

        $this->assertSame(0.0, $result['reported']);
        $this->assertSame(350000.0, $result['outstanding']);
        $this->assertSame(CashAdvanceReport::SETTLEMENT_REFUND, $result['type']);
    }

    /**
     * Angka pecahan biner tidak pernah persis nol setelah dijumlahkan.
     * Tanpa ambang, dokumen yang sebenarnya pas akan berlabel "Refund
     * Rp 0,0000001" — dan bagian keuangan akan mengejar uang yang tidak ada.
     */
    public function test_selisih_di_bawah_setengah_sen_dianggap_pas(): void
    {
        $result = $this->svc->settlement(1000000, [333333.33, 333333.33, 333333.34]);

        $this->assertSame(CashAdvanceReport::SETTLEMENT_EXACT, $result['type']);
    }

    public function test_settlement_type_langsung_dari_selisih(): void
    {
        $this->assertSame(CashAdvanceReport::SETTLEMENT_REFUND, $this->svc->settlementType(1));
        $this->assertSame(CashAdvanceReport::SETTLEMENT_CLAIM, $this->svc->settlementType(-1));
        $this->assertSame(CashAdvanceReport::SETTLEMENT_EXACT, $this->svc->settlementType(0));
        $this->assertSame(CashAdvanceReport::SETTLEMENT_EXACT, $this->svc->settlementType(0.004));
        $this->assertSame(CashAdvanceReport::SETTLEMENT_REFUND, $this->svc->settlementType(0.006));
    }

    // ── settlementStatus: sumbu KEDUA (D140) ────────────────────────────────

    public function test_tanpa_car_status_penyelesaiannya_unreported(): void
    {
        $this->assertSame(CashAdvance::SETTLE_UNREPORTED, $this->svc->settlementStatus(0, 0));
    }

    public function test_ada_car_tapi_belum_disetujui_berarti_masih_reporting(): void
    {
        $this->assertSame(CashAdvance::SETTLE_REPORTING, $this->svc->settlementStatus(1, 0));
        $this->assertSame(CashAdvance::SETTLE_REPORTING, $this->svc->settlementStatus(3, 2));
    }

    public function test_buku_ditutup_hanya_bila_seluruh_car_sudah_disetujui(): void
    {
        $this->assertSame(CashAdvance::SETTLE_SETTLED, $this->svc->settlementStatus(1, 1));
        $this->assertSame(CashAdvance::SETTLE_SETTLED, $this->svc->settlementStatus(3, 3));
    }

    // ── checkLimits ─────────────────────────────────────────────────────────

    public function test_nominal_nol_atau_negatif_selalu_ditolak(): void
    {
        $this->assertFalse($this->svc->checkLimits(0, $this->setting())['allowed']);
        $this->assertFalse($this->svc->checkLimits(-100, $this->setting())['allowed']);
    }

    public function test_batas_nol_berarti_tanpa_batas_bukan_nol(): void
    {
        $result = $this->svc->checkLimits(999_999_999, $this->setting());

        $this->assertTrue($result['allowed']);
        $this->assertFalse($result['flagged']);
    }

    public function test_di_bawah_batas_bawah_selalu_ditolak_meski_kebijakannya_flag(): void
    {
        $result = $this->svc->checkLimits(5000, $this->setting([
            'min_amount'        => 10000,
            'over_limit_policy' => CashAdvanceSetting::LIMIT_FLAG,
        ]));

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('minimum', $result['reason']);
    }

    public function test_kebijakan_flag_menerima_nominal_lewat_batas_tetapi_menandainya(): void
    {
        $result = $this->svc->checkLimits(2_000_000, $this->setting([
            'max_amount'        => 1_000_000,
            'over_limit_policy' => CashAdvanceSetting::LIMIT_FLAG,
        ]));

        $this->assertTrue($result['allowed'], 'kebijakan flag tidak boleh menolak');
        $this->assertTrue($result['flagged']);
        $this->assertArrayHasKey('over_limit', $result['flags']);
    }

    public function test_kebijakan_block_menolak_nominal_lewat_batas(): void
    {
        $result = $this->svc->checkLimits(2_000_000, $this->setting([
            'max_amount'        => 1_000_000,
            'over_limit_policy' => CashAdvanceSetting::LIMIT_BLOCK,
        ]));

        $this->assertFalse($result['allowed']);
        $this->assertFalse($result['flagged']);
        $this->assertStringContainsString('maximum', $result['reason']);
    }

    public function test_tepat_di_batas_atas_masih_diterima_tanpa_ditandai(): void
    {
        $result = $this->svc->checkLimits(1_000_000, $this->setting([
            'max_amount'        => 1_000_000,
            'over_limit_policy' => CashAdvanceSetting::LIMIT_BLOCK,
        ]));

        $this->assertTrue($result['allowed']);
        $this->assertFalse($result['flagged']);
    }

    // ── checkReportedAgainstAdvance ─────────────────────────────────────────

    public function test_realisasi_nol_ditolak(): void
    {
        $this->assertFalse(
            $this->svc->checkReportedAgainstAdvance(0, 350000, $this->setting())['allowed']
        );
    }

    public function test_realisasi_melebihi_ca_diterima_bila_setelannya_mengizinkan(): void
    {
        $result = $this->svc->checkReportedAgainstAdvance(400000, 350000, $this->setting([
            'car_allow_over_amount' => true,
        ]));

        $this->assertTrue($result['allowed'], 'melebihi nominal adalah keadaan claim yang sah');
    }

    public function test_realisasi_melebihi_ca_ditolak_bila_setelannya_melarang(): void
    {
        $result = $this->svc->checkReportedAgainstAdvance(400000, 350000, $this->setting([
            'car_allow_over_amount' => false,
        ]));

        $this->assertFalse($result['allowed']);
        $this->assertStringContainsString('exceeds', $result['reason']);
    }

    public function test_realisasi_pas_diterima_meski_setelannya_melarang_kelebihan(): void
    {
        $result = $this->svc->checkReportedAgainstAdvance(350000, 350000, $this->setting([
            'car_allow_over_amount' => false,
        ]));

        $this->assertTrue($result['allowed']);
    }

    // ── reportDueStatus (C10) ───────────────────────────────────────────────

    public function test_tenggat_nol_tidak_pernah_menandai_dokumen_siapa_pun(): void
    {
        $result = $this->svc->reportDueStatus(9999, $this->setting(['car_due_days' => 0]));

        $this->assertFalse($result['overdue']);
        $this->assertSame(0, $result['days_late']);
        $this->assertNull($result['due_in']);
    }

    public function test_belum_lewat_tenggat_melaporkan_sisa_hari(): void
    {
        $result = $this->svc->reportDueStatus(4, $this->setting(['car_due_days' => 14]));

        $this->assertFalse($result['overdue']);
        $this->assertSame(10, $result['due_in']);
        $this->assertSame(0, $result['days_late']);
    }

    public function test_tepat_di_hari_tenggat_belum_terlambat(): void
    {
        $result = $this->svc->reportDueStatus(14, $this->setting(['car_due_days' => 14]));

        $this->assertFalse($result['overdue']);
        $this->assertSame(0, $result['due_in']);
    }

    public function test_lewat_tenggat_menghitung_keterlambatan(): void
    {
        $result = $this->svc->reportDueStatus(20, $this->setting(['car_due_days' => 14]));

        $this->assertTrue($result['overdue']);
        $this->assertSame(6, $result['days_late']);
        $this->assertSame(0, $result['due_in']);
    }
}
