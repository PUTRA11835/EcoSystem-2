<?php

namespace Tests\Unit\PurchaseRequest;

use App\Models\PurchaseRequest\PurchaseRequest;
use App\Models\PurchaseRequest\PurchaseRequestItem;
use App\Services\PurchaseRequest\PurchaseRequestSummaryService;
use PHPUnit\Framework\TestCase;

/**
 * Uji unit PurchaseRequestSummaryService.
 *
 * Sengaja memakai PHPUnit\Framework\TestCase polos, BUKAN Tests\TestCase milik
 * Laravel: service ini tidak menyentuh database maupun container. Itu justru
 * intinya — meringkas kuantitas, menurunkan pembebanan, dan menilai kelengkapan
 * adalah tiga hal yang harus memberi jawaban SAMA saat karyawan mengajukan, saat
 * admin membuat atas nama orang lain, dan saat HR mengedit. Menguji satu fungsi
 * murni jauh lebih murah daripada menguji ketiga jalur itu masing-masing.
 */
class PurchaseRequestSummaryServiceTest extends TestCase
{
    private PurchaseRequestSummaryService $summary;

    /** Urutan satuan bawaan, menyamai `allowed_units` di Settings. */
    private const UNITS = ['PC', 'UNIT', 'SET', 'BOX', 'LOT'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->summary = new PurchaseRequestSummaryService();
    }

    /** Pembantu: satu baris item dengan nilai bawaan yang sah. */
    private function item(array $overrides = []): array
    {
        return array_merge([
            'description'         => 'Laptop 14 inci',
            'qty'                 => 2,
            'unit'                => 'UNIT',
            'period_from'         => '2026-09-01',
            'period_to'           => '2026-09-30',
            'use_date'            => '2026-09-05',
            'cost_center_type'    => PurchaseRequestItem::COST_CENTER_BRANCH,
            'branch_id'           => 2,
            'delivery_project_id' => null,
            'cost_center_label'   => 'EC-JOGJA – Eclectic Yogyakarta',
        ], $overrides);
    }

    // ── Ringkasan kuantitas ─────────────────────────────────────────────────

    public function test_ringkasan_satu_satuan(): void
    {
        $text = $this->summary->qtySummary([
            $this->item(['qty' => 10, 'unit' => 'PC']),
            $this->item(['qty' => 5, 'unit' => 'PC']),
        ], self::UNITS);

        $this->assertSame('15 PC', $text);
    }

    public function test_satuan_berbeda_tidak_dijumlahkan_jadi_satu_angka(): void
    {
        $text = $this->summary->qtySummary([
            $this->item(['qty' => 10, 'unit' => 'PC']),
            $this->item(['qty' => 5, 'unit' => 'SET']),
        ], self::UNITS);

        $this->assertSame('10 PC · 5 SET', $text);
    }

    /**
     * Urutan mengikuti daftar satuan di Settings, bukan urutan pengetikan —
     * kalau tidak, dua dokumen yang isinya sama akan terlihat berbeda di rekap.
     */
    public function test_urutan_satuan_mengikuti_settings_bukan_urutan_ketik(): void
    {
        $text = $this->summary->qtySummary([
            $this->item(['qty' => 1, 'unit' => 'LOT']),
            $this->item(['qty' => 2, 'unit' => 'PC']),
            $this->item(['qty' => 3, 'unit' => 'BOX']),
        ], self::UNITS);

        $this->assertSame('2 PC · 3 BOX · 1 LOT', $text);
    }

    public function test_satuan_di_luar_daftar_menyusul_di_belakang_secara_alfabetis(): void
    {
        $text = $this->summary->qtySummary([
            $this->item(['qty' => 1, 'unit' => 'ROLL']),
            $this->item(['qty' => 2, 'unit' => 'PC']),
            $this->item(['qty' => 3, 'unit' => 'DRUM']),
        ], self::UNITS);

        $this->assertSame('2 PC · 3 DRUM · 1 ROLL', $text);
    }

    public function test_huruf_kecil_dan_besar_bukan_dua_satuan_berbeda(): void
    {
        $text = $this->summary->qtySummary([
            $this->item(['qty' => 2, 'unit' => 'pc']),
            $this->item(['qty' => 3, 'unit' => 'PC']),
        ], self::UNITS);

        $this->assertSame('5 PC', $text);
    }

    public function test_kuantitas_pecahan_tidak_kehilangan_desimalnya(): void
    {
        $text = $this->summary->qtySummary([
            $this->item(['qty' => 0.5, 'unit' => 'LOT']),
        ], self::UNITS);

        $this->assertSame('0,5 LOT', $text);
    }

    /** 2.00 dicetak "2", bukan "2,00" — dokumen ini sengaja tidak punya nominal. */
    public function test_kuantitas_bulat_dicetak_tanpa_desimal(): void
    {
        $this->assertSame('2', PurchaseRequestItem::formatQty(2.00));
        $this->assertSame('0,5', PurchaseRequestItem::formatQty(0.50));
        $this->assertSame('1.500', PurchaseRequestItem::formatQty(1500));
    }

    public function test_baris_tanpa_kuantitas_tidak_masuk_ringkasan(): void
    {
        $text = $this->summary->qtySummary([
            $this->item(['qty' => 0, 'unit' => 'PC']),
            $this->item(['qty' => 3, 'unit' => 'PC']),
        ], self::UNITS);

        $this->assertSame('3 PC', $text);
    }

    public function test_dokumen_kosong_menghasilkan_ringkasan_kosong(): void
    {
        $this->assertSame('', $this->summary->qtySummary([], self::UNITS));
    }

    // ── Penurunan pembebanan ────────────────────────────────────────────────

    public function test_seluruh_item_satu_cabang_menghasilkan_id_cabang_itu(): void
    {
        $charged = $this->summary->chargedTo([
            $this->item(['branch_id' => 2]),
            $this->item(['branch_id' => 2]),
        ]);

        $this->assertSame(PurchaseRequestItem::COST_CENTER_BRANCH, $charged['type']);
        $this->assertSame(2, $charged['branch_id']);
        $this->assertNull($charged['project_id']);
        $this->assertSame('EC-JOGJA – Eclectic Yogyakarta', $charged['label']);
    }

    public function test_seluruh_item_satu_proyek_menghasilkan_id_proyek_itu(): void
    {
        $charged = $this->summary->chargedTo([
            $this->item([
                'cost_center_type'    => PurchaseRequestItem::COST_CENTER_PROJECT,
                'branch_id'           => null,
                'delivery_project_id' => 3,
                'cost_center_label'   => '7600000084 – Implementasi SAP',
            ]),
        ]);

        $this->assertSame(PurchaseRequestItem::COST_CENTER_PROJECT, $charged['type']);
        $this->assertNull($charged['branch_id']);
        $this->assertSame(3, $charged['project_id']);
        $this->assertSame('7600000084 – Implementasi SAP', $charged['label']);
    }

    /** Inti Keputusan D127: cabang dan proyek boleh bercampur antar baris. */
    public function test_cabang_dan_proyek_bercampur_menghasilkan_mixed(): void
    {
        $charged = $this->summary->chargedTo([
            $this->item(['branch_id' => 2]),
            $this->item([
                'cost_center_type'    => PurchaseRequestItem::COST_CENTER_PROJECT,
                'branch_id'           => null,
                'delivery_project_id' => 3,
                'cost_center_label'   => '7600000084 – Implementasi SAP',
            ]),
        ]);

        $this->assertSame(PurchaseRequest::COST_CENTER_MIXED, $charged['type']);
        $this->assertNull($charged['branch_id']);
        $this->assertNull($charged['project_id']);
        $this->assertSame(PurchaseRequest::LABEL_MULTIPLE, $charged['label']);
    }

    public function test_dua_cabang_berbeda_juga_menghasilkan_mixed(): void
    {
        $charged = $this->summary->chargedTo([
            $this->item(['branch_id' => 2]),
            $this->item(['branch_id' => 3, 'cost_center_label' => 'EC-SBY – Eclectic Surabaya']),
        ]);

        $this->assertSame(PurchaseRequest::COST_CENTER_MIXED, $charged['type']);
        $this->assertSame(PurchaseRequest::LABEL_MULTIPLE, $charged['label']);
    }

    /**
     * Cabang id 2 dan proyek id 2 adalah dua tempat berbeda. Kalau kuncinya
     * hanya angka, keduanya akan terlihat sebagai pembebanan yang sama.
     */
    public function test_cabang_dan_proyek_ber_id_sama_tetap_dianggap_berbeda(): void
    {
        $charged = $this->summary->chargedTo([
            $this->item(['branch_id' => 2]),
            $this->item([
                'cost_center_type'    => PurchaseRequestItem::COST_CENTER_PROJECT,
                'branch_id'           => null,
                'delivery_project_id' => 2,
                'cost_center_label'   => '7600000069 – SAP PM',
            ]),
        ]);

        $this->assertSame(PurchaseRequest::COST_CENTER_MIXED, $charged['type']);
    }

    public function test_item_tanpa_pembebanan_menghasilkan_tipe_null(): void
    {
        $charged = $this->summary->chargedTo([
            $this->item(['branch_id' => null, 'cost_center_label' => null]),
        ]);

        $this->assertNull($charged['type']);
        $this->assertNull($charged['branch_id']);
        $this->assertSame('—', $charged['label']);
    }

    /** Label diambil dari yang DIBEKUKAN; cadangannya bukan nama tabel sumber. */
    public function test_label_kosong_jatuh_ke_penanda_id_bukan_string_kosong(): void
    {
        $charged = $this->summary->chargedTo([
            $this->item(['cost_center_label' => '']),
        ]);

        $this->assertSame('Branch #2', $charged['label']);
    }

    // ── Satu baris satu tipe ────────────────────────────────────────────────

    public function test_baris_mengisi_cabang_dan_proyek_sekaligus_ditolak(): void
    {
        $result = $this->summary->assertSingleCostCenter(
            $this->item(['branch_id' => 2, 'delivery_project_id' => 3])
        );

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('not both', $result['reason']);
    }

    public function test_baris_bertipe_branch_tetapi_membawa_proyek_ditolak(): void
    {
        $result = $this->summary->assertSingleCostCenter(
            $this->item(['branch_id' => null, 'delivery_project_id' => 3])
        );

        $this->assertFalse($result['ok']);
    }

    public function test_baris_bertipe_project_tetapi_membawa_cabang_ditolak(): void
    {
        $result = $this->summary->assertSingleCostCenter($this->item([
            'cost_center_type'    => PurchaseRequestItem::COST_CENTER_PROJECT,
            'branch_id'           => 2,
            'delivery_project_id' => null,
        ]));

        $this->assertFalse($result['ok']);
    }

    public function test_tipe_pembebanan_tak_dikenal_ditolak(): void
    {
        $result = $this->summary->assertSingleCostCenter(
            $this->item(['cost_center_type' => 'department'])
        );

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('Unknown cost center type', $result['reason']);
    }

    public function test_baris_yang_sah_diterima(): void
    {
        $this->assertTrue($this->summary->assertSingleCostCenter($this->item())['ok']);
    }

    // ── Penataan ulang baris ────────────────────────────────────────────────

    /**
     * Form mengirim item berkunci UUID. Nomor urut yang dilihat pengguna dibangun
     * dari urutan kedatangannya, bukan dari kunci arraynya.
     */
    public function test_line_no_dibangun_ulang_dari_kunci_acak(): void
    {
        $items = $this->summary->normaliseItems([
            'b7f2' => $this->item(['description' => 'Laptop']),
            '19ac' => $this->item(['description' => 'Monitor']),
            'ff01' => $this->item(['description' => 'Docking']),
        ]);

        $this->assertSame([1, 2, 3], array_column($items, 'line_no'));
        $this->assertSame(['Laptop', 'Monitor', 'Docking'], array_column($items, 'description'));
    }

    public function test_baris_kosong_dibuang_tanpa_protes(): void
    {
        $items = $this->summary->normaliseItems([
            $this->item(['description' => 'Laptop']),
            ['description' => '', 'qty' => 0],
            $this->item(['description' => 'Monitor']),
        ]);

        $this->assertCount(2, $items);
        $this->assertSame([1, 2], array_column($items, 'line_no'));
    }

    public function test_baris_bukan_array_dilewati(): void
    {
        $items = $this->summary->normaliseItems([
            'x' => 'bukan array',
            'y' => $this->item(),
        ]);

        $this->assertCount(1, $items);
    }

    /**
     * Pengguna yang berpindah Branch -> Project setelah memilih cabang akan
     * meninggalkan nilai lama di form. Nilai yang tidak sesuai tipenya dipaksa
     * null, bukan dibiarkan terbawa ke database.
     */
    public function test_kolom_yang_tidak_sesuai_tipe_dipaksa_null(): void
    {
        $items = $this->summary->normaliseItems([
            $this->item([
                'cost_center_type'    => PurchaseRequestItem::COST_CENTER_PROJECT,
                'branch_id'           => 2,
                'delivery_project_id' => 3,
            ]),
        ]);

        $this->assertNull($items[0]['branch_id']);
        $this->assertSame(3, $items[0]['delivery_project_id']);
    }

    public function test_periode_satu_hari_mengisi_kedua_ujungnya(): void
    {
        $items = $this->summary->normaliseItems([
            $this->item(['period_from' => '2026-09-01', 'period_to' => '']),
        ]);

        $this->assertSame('2026-09-01', $items[0]['period_from']);
        $this->assertSame('2026-09-01', $items[0]['period_to']);
    }

    public function test_periode_kosong_tetap_null_di_kedua_ujungnya(): void
    {
        $items = $this->summary->normaliseItems([
            $this->item(['period_from' => '', 'period_to' => '']),
        ]);

        $this->assertNull($items[0]['period_from']);
        $this->assertNull($items[0]['period_to']);
    }

    public function test_satuan_kosong_jatuh_ke_bawaan_dari_settings(): void
    {
        $items = $this->summary->normaliseItems([
            $this->item(['unit' => '']),
        ], 'BOX');

        $this->assertSame('BOX', $items[0]['unit']);
    }

    public function test_tipe_pembebanan_tak_dikenal_jatuh_ke_branch(): void
    {
        $items = $this->summary->normaliseItems([
            $this->item(['cost_center_type' => 'department']),
        ]);

        $this->assertSame(PurchaseRequestItem::COST_CENTER_BRANCH, $items[0]['cost_center_type']);
    }

    public function test_kuantitas_negatif_dipaksa_nol(): void
    {
        $items = $this->summary->normaliseItems([
            $this->item(['description' => 'Salah ketik', 'qty' => -5]),
        ]);

        $this->assertSame(0.0, $items[0]['qty']);
    }

    /** Label pembebanan sengaja BUKAN tugas service murni — butuh baca tabel. */
    public function test_label_pembebanan_dibiarkan_null_untuk_diisi_service_ber_db(): void
    {
        $items = $this->summary->normaliseItems([$this->item()]);

        $this->assertNull($items[0]['cost_center_label']);
    }

    // ── Penandaan ───────────────────────────────────────────────────────────

    public function test_dokumen_lengkap_tidak_menghasilkan_flag(): void
    {
        $result = $this->summary->evaluate(
            [$this->item()],
            ['unit_order' => self::UNITS, 'today' => '2026-08-28']
        );

        $this->assertSame([], $result['flags']);
    }

    public function test_baris_tanpa_tanggal_pakai_ditandai(): void
    {
        $result = $this->summary->evaluate(
            [$this->item(['use_date' => null])],
            ['unit_order' => self::UNITS, 'today' => '2026-08-28']
        );

        $this->assertContains(PurchaseRequest::FLAG_MISSING_USE_DATE, $result['flags']);
    }

    public function test_baris_tanpa_periode_ditandai(): void
    {
        $result = $this->summary->evaluate(
            [$this->item(['period_from' => null])],
            ['unit_order' => self::UNITS, 'today' => '2026-08-28']
        );

        $this->assertContains(PurchaseRequest::FLAG_MISSING_PERIOD, $result['flags']);
    }

    public function test_baris_tanpa_pembebanan_ditandai(): void
    {
        $result = $this->summary->evaluate(
            [$this->item(['branch_id' => null])],
            ['unit_order' => self::UNITS, 'today' => '2026-08-28']
        );

        $this->assertContains(PurchaseRequest::FLAG_MISSING_COST_CENTER, $result['flags']);
    }

    public function test_tanggal_pakai_yang_sudah_lewat_ditandai(): void
    {
        $result = $this->summary->evaluate(
            [$this->item(['use_date' => '2026-08-01'])],
            ['unit_order' => self::UNITS, 'today' => '2026-08-28']
        );

        $this->assertContains(PurchaseRequest::FLAG_USE_DATE_PASSED, $result['flags']);
    }

    public function test_pembebanan_campur_ditandai_netral(): void
    {
        $result = $this->summary->evaluate([
            $this->item(['branch_id' => 2]),
            $this->item([
                'cost_center_type'    => PurchaseRequestItem::COST_CENTER_PROJECT,
                'branch_id'           => null,
                'delivery_project_id' => 3,
                'cost_center_label'   => '7600000084 – Implementasi SAP',
            ]),
        ], ['unit_order' => self::UNITS, 'today' => '2026-08-28']);

        $this->assertContains(PurchaseRequest::FLAG_MIXED_COST_CENTER, $result['flags']);
    }

    /**
     * Penanda netral tidak boleh ikut menyorot baris di rekap. Menandai keadaan
     * yang lazim sebagai janggal membuat seluruh penandaan kehilangan artinya.
     */
    public function test_flag_netral_tidak_termasuk_flag_yang_perlu_perhatian(): void
    {
        $this->assertNotContains(PurchaseRequest::FLAG_MIXED_COST_CENTER, PurchaseRequest::ATTENTION_FLAGS);
        $this->assertNotContains(PurchaseRequest::FLAG_CREATED_ON_BEHALF, PurchaseRequest::ATTENTION_FLAGS);
        $this->assertNotContains(PurchaseRequest::FLAG_WORKFLOW_EXTENDED, PurchaseRequest::ATTENTION_FLAGS);
    }

    public function test_flag_tidak_pernah_terduplikasi_meski_banyak_baris_bermasalah(): void
    {
        $result = $this->summary->evaluate([
            $this->item(['use_date' => null]),
            $this->item(['use_date' => null]),
            $this->item(['use_date' => null]),
        ], ['unit_order' => self::UNITS, 'today' => '2026-08-28']);

        $this->assertSame(
            [PurchaseRequest::FLAG_MISSING_USE_DATE],
            array_values(array_intersect($result['flags'], [PurchaseRequest::FLAG_MISSING_USE_DATE]))
        );
        $this->assertCount(count(array_unique($result['flags'])), $result['flags']);
    }

    public function test_setiap_flag_punya_label_yang_dapat_dibaca(): void
    {
        foreach (PurchaseRequest::ATTENTION_FLAGS as $flag) {
            $this->assertArrayHasKey($flag, PurchaseRequest::FLAG_LABELS, "Flag {$flag} tidak punya label.");
        }
    }

    // ── evaluate() sebagai satu kesatuan ────────────────────────────────────

    public function test_evaluate_mengembalikan_seluruh_kepala_dokumen(): void
    {
        $result = $this->summary->evaluate([
            $this->item(['qty' => 2, 'unit' => 'UNIT']),
            $this->item(['qty' => 3, 'unit' => 'PC']),
        ], ['unit_order' => self::UNITS, 'today' => '2026-08-28']);

        $this->assertSame(2, $result['item_count']);
        $this->assertSame('3 PC · 2 UNIT', $result['qty_summary']);
        $this->assertSame(PurchaseRequestItem::COST_CENTER_BRANCH, $result['cost_center_type']);
        $this->assertSame(2, $result['charged_branch_id']);
        $this->assertNull($result['charged_project_id']);
        $this->assertSame('EC-JOGJA – Eclectic Yogyakarta', $result['charged_to_label']);
    }

    public function test_evaluate_dokumen_kosong_aman(): void
    {
        $result = $this->summary->evaluate([], ['unit_order' => self::UNITS]);

        $this->assertSame(0, $result['item_count']);
        $this->assertSame('', $result['qty_summary']);
        $this->assertNull($result['cost_center_type']);
        $this->assertSame([], $result['flags']);
    }
}
