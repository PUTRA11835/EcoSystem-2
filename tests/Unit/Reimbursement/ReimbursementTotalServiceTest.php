<?php

namespace Tests\Unit\Reimbursement;

use App\Models\Reimbursement\ReimbursementRequest;
use App\Services\Reimbursement\ReimbursementTotalService;
use PHPUnit\Framework\TestCase;

/**
 * Uji unit ReimbursementTotalService.
 *
 * Sengaja memakai PHPUnit\Framework\TestCase polos, BUKAN Tests\TestCase milik
 * Laravel: service ini tidak menyentuh database maupun container. Itu justru
 * intinya — penjumlahan, penurunan pembebanan, dan penandaan kewajaran adalah
 * tiga hal yang harus memberi jawaban SAMA saat submit, saat HR mengedit, dan
 * saat impor Excel. Menguji satu fungsi murni jauh lebih murah daripada menguji
 * ketiga jalur itu masing-masing.
 *
 * Angka pada beberapa kasus diambil dari berkas acuan (reimbursement_2026-08.xlsx):
 * dua item 100.000 + 50.000 = 150.000 pada dokumen RB/2026/08/00001.
 */
class ReimbursementTotalServiceTest extends TestCase
{
    private ReimbursementTotalService $totals;

    protected function setUp(): void
    {
        parent::setUp();
        $this->totals = new ReimbursementTotalService();
    }

    /** Pembantu: satu baris item dengan nilai bawaan yang sah. */
    private function item(array $overrides = []): array
    {
        return array_merge([
            'description'       => 'Testing Reimbursement',
            'amount'            => 100000,
            'branch_id'         => 2,
            'cost_center_label' => 'EC-JOGJA – Eclectic Yogyakarta',
            'receipt_no'        => '1',
        ], $overrides);
    }

    // ── Penjumlahan ─────────────────────────────────────────────────────────

    public function test_total_dua_item_cocok_dengan_berkas_acuan(): void
    {
        $total = $this->totals->sum([
            $this->item(['amount' => 100000]),
            $this->item(['amount' => 50000]),
        ]);

        $this->assertSame(150000.0, $total);
    }

    public function test_dokumen_tanpa_item_bernilai_nol(): void
    {
        $this->assertSame(0.0, $this->totals->sum([]));
    }

    public function test_nominal_berbentuk_string_tetap_dijumlahkan(): void
    {
        // Nilai decimal dari Eloquent dikembalikan sebagai string, dan form
        // mengirim angka sebagai teks. Keduanya harus diterima apa adanya.
        $total = $this->totals->sum([
            $this->item(['amount' => '100000.50']),
            $this->item(['amount' => '49999.50']),
        ]);

        $this->assertSame(150000.0, $total);
    }

    public function test_nominal_negatif_diperlakukan_sebagai_nol(): void
    {
        // Reimbursement adalah penggantian biaya; baris negatif hanya dapat lahir
        // dari kesalahan masukan, dan membiarkannya akan MENGURANGI total.
        $total = $this->totals->sum([
            $this->item(['amount' => 100000]),
            $this->item(['amount' => -50000]),
        ]);

        $this->assertSame(100000.0, $total);
    }

    public function test_total_selalu_sama_dengan_jumlah_baris_yang_tercetak(): void
    {
        // Kolom `amount` bertipe decimal(20,2), jadi tiap baris yang TERSIMPAN
        // dan TERCETAK sudah dua desimal: 0,335 menjadi 0,34. Total dokumen harus
        // sama dengan jumlah angka yang terbaca padanya (3 x 0,34 = 1,02) — bukan
        // hasil penjumlahan nilai penuh yang dibulatkan belakangan (1,01).
        // Selisih satu sen antara total dan isinya adalah hal pertama yang
        // ditemukan Finance saat mencocokkan.
        $amounts = [0.335, 0.335, 0.335];

        $total = $this->totals->sum(
            array_map(fn ($amount) => $this->item(['amount' => $amount]), $amounts)
        );

        $printedSum = array_sum(array_map(fn ($amount) => round($amount, 2), $amounts));

        $this->assertSame(1.02, $total);
        $this->assertSame($printedSum, $total);
    }

    // ── Pembebanan biaya (menggantikan "Jenis Beban") ────────────────────────

    public function test_seluruh_item_satu_cabang_memakai_nama_cabang_itu(): void
    {
        $result = $this->totals->chargedTo([
            $this->item(['branch_id' => 2]),
            $this->item(['branch_id' => 2]),
        ]);

        $this->assertSame(2, $result['branch_id']);
        $this->assertSame('EC-JOGJA – Eclectic Yogyakarta', $result['label']);
    }

    public function test_item_lintas_cabang_tidak_punya_cabang_tunggal(): void
    {
        $result = $this->totals->chargedTo([
            $this->item(['branch_id' => 2, 'cost_center_label' => 'EC-JOGJA – Eclectic Yogyakarta']),
            $this->item(['branch_id' => 3, 'cost_center_label' => 'EC-JKT – Eclectic Jakarta']),
        ]);

        $this->assertNull($result['branch_id']);
        $this->assertSame(ReimbursementRequest::LABEL_MULTIPLE_BRANCHES, $result['label']);
    }

    public function test_item_tanpa_cabang_menghasilkan_label_kosong(): void
    {
        $result = $this->totals->chargedTo([
            $this->item(['branch_id' => null, 'cost_center_label' => null]),
        ]);

        $this->assertNull($result['branch_id']);
        $this->assertSame('—', $result['label']);
    }

    public function test_label_cabang_yang_dibekukan_dipakai_apa_adanya(): void
    {
        // Nama cabang dapat berubah setelah dokumen disetujui (Keputusan D105).
        // Yang dipakai adalah label yang tersimpan pada item, bukan hasil
        // pembacaan ulang tabel branches.
        $result = $this->totals->chargedTo([
            $this->item(['cost_center_label' => 'EC-LAMA – Nama Cabang Sebelum Diubah']),
        ]);

        $this->assertSame('EC-LAMA – Nama Cabang Sebelum Diubah', $result['label']);
    }

    public function test_cabang_tunggal_tanpa_label_jatuh_ke_nomor_cabang(): void
    {
        $result = $this->totals->chargedTo([
            $this->item(['branch_id' => 7, 'cost_center_label' => '']),
        ]);

        $this->assertSame('Branch #7', $result['label']);
    }

    // ── Batas nominal ───────────────────────────────────────────────────────

    public function test_batas_nol_berarti_tanpa_batas(): void
    {
        $this->assertFalse($this->totals->exceedsLimit(999_999_999, 0));
    }

    public function test_total_di_atas_batas_terdeteksi(): void
    {
        $this->assertTrue($this->totals->exceedsLimit(1_500_000, 1_000_000));
    }

    public function test_total_tepat_di_batas_belum_melewatinya(): void
    {
        // Batas adalah nilai yang MASIH boleh, bukan nilai pertama yang ditolak.
        $this->assertFalse($this->totals->exceedsLimit(1_000_000, 1_000_000));
    }

    // ── Penandaan ───────────────────────────────────────────────────────────

    public function test_dokumen_lengkap_tidak_diberi_flag_apa_pun(): void
    {
        $result = $this->totals->evaluate(
            [$this->item()],
            ['has_supporting_url' => true, 'max_request_amount' => 0, 'min_item_amount' => 0]
        );

        $this->assertSame([], $result['flags']);
    }

    public function test_tanpa_tautan_bukti_diberi_flag(): void
    {
        $result = $this->totals->evaluate(
            [$this->item()],
            ['has_supporting_url' => false]
        );

        $this->assertContains(
            ReimbursementRequest::FLAG_MISSING_SUPPORTING_URL,
            $result['flags']
        );
    }

    public function test_item_tanpa_nomor_bukti_diberi_flag(): void
    {
        $result = $this->totals->evaluate(
            [$this->item(['receipt_no' => '  ']), $this->item()],
            ['has_supporting_url' => true]
        );

        $this->assertContains(
            ReimbursementRequest::FLAG_MISSING_RECEIPT_NO,
            $result['flags']
        );
    }

    public function test_melewati_batas_hanya_MENANDAI_bukan_menolak(): void
    {
        // Keputusan D107: service murni ini tidak pernah menolak. Penolakan
        // adalah keputusan kebijakan yang dikerjakan ReimbursementService saat
        // over_limit_policy = block.
        $result = $this->totals->evaluate(
            [$this->item(['amount' => 5_000_000])],
            ['has_supporting_url' => true, 'max_request_amount' => 1_000_000]
        );

        $this->assertContains(ReimbursementRequest::FLAG_EXCEEDS_LIMIT, $result['flags']);
        $this->assertSame(5_000_000.0, $result['total']);
    }

    public function test_item_di_bawah_nominal_minimum_diberi_flag(): void
    {
        $result = $this->totals->evaluate(
            [$this->item(['amount' => 5_000]), $this->item(['amount' => 100_000])],
            ['has_supporting_url' => true, 'min_item_amount' => 10_000]
        );

        $this->assertContains(ReimbursementRequest::FLAG_BELOW_MIN_ITEM, $result['flags']);
    }

    public function test_minimum_nol_tidak_pernah_menandai(): void
    {
        $result = $this->totals->evaluate(
            [$this->item(['amount' => 1])],
            ['has_supporting_url' => true, 'min_item_amount' => 0]
        );

        $this->assertNotContains(ReimbursementRequest::FLAG_BELOW_MIN_ITEM, $result['flags']);
    }

    public function test_lintas_cabang_ditandai_sebagai_penanda_netral(): void
    {
        $result = $this->totals->evaluate(
            [
                $this->item(['branch_id' => 2]),
                $this->item(['branch_id' => 3, 'cost_center_label' => 'EC-JKT – Eclectic Jakarta']),
            ],
            ['has_supporting_url' => true]
        );

        $this->assertContains(ReimbursementRequest::FLAG_MULTI_BRANCH, $result['flags']);

        // Netral berarti TIDAK menuntut perhatian HR. Kalau ia masuk daftar itu,
        // seluruh penandaan kehilangan artinya.
        $this->assertNotContains(
            ReimbursementRequest::FLAG_MULTI_BRANCH,
            ReimbursementRequest::ATTENTION_FLAGS
        );
    }

    public function test_satu_item_tanpa_cabang_bukan_lintas_cabang(): void
    {
        $result = $this->totals->evaluate(
            [$this->item(['branch_id' => null, 'cost_center_label' => null])],
            ['has_supporting_url' => true]
        );

        $this->assertNotContains(ReimbursementRequest::FLAG_MULTI_BRANCH, $result['flags']);
    }

    public function test_flag_tidak_pernah_berganda(): void
    {
        $result = $this->totals->evaluate(
            [
                $this->item(['receipt_no' => null, 'amount' => 1]),
                $this->item(['receipt_no' => '', 'amount' => 2]),
            ],
            ['has_supporting_url' => true, 'min_item_amount' => 10]
        );

        $this->assertSame(
            count($result['flags']),
            count(array_unique($result['flags']))
        );
    }

    // ── Bentuk keluaran ─────────────────────────────────────────────────────

    public function test_evaluate_mengembalikan_seluruh_kunci_yang_dipakai_pemanggil(): void
    {
        $result = $this->totals->evaluate([$this->item()], ['has_supporting_url' => true]);

        foreach (['total', 'item_count', 'charged_branch_id', 'charged_to_label', 'flags'] as $key) {
            $this->assertArrayHasKey($key, $result);
        }
    }

    public function test_jumlah_item_menghitung_baris_bukan_nominal(): void
    {
        $result = $this->totals->evaluate(
            [$this->item(), $this->item(), $this->item()],
            ['has_supporting_url' => true]
        );

        $this->assertSame(3, $result['item_count']);
    }

    public function test_aturan_kosong_tidak_menyebabkan_galat(): void
    {
        // Pemanggil yang tidak menyertakan aturan apa pun tetap harus mendapat
        // hasil yang sah — impor Excel nanti memakai jalur ini.
        $result = $this->totals->evaluate([$this->item()]);

        $this->assertSame(100000.0, $result['total']);
    }

    // ── Format rupiah ───────────────────────────────────────────────────────

    public function test_format_rupiah_mengikuti_gaya_indonesia(): void
    {
        $this->assertSame('Rp 300.000', ReimbursementRequest::formatRupiah(300000));
        $this->assertSame('Rp 150.000', ReimbursementRequest::formatRupiah('150000.00'));
        $this->assertSame('Rp 0', ReimbursementRequest::formatRupiah(0));
    }
}
