<?php

namespace Tests\Unit\Onboarding;

use App\Services\Onboarding\OnboardingRules;
use PHPUnit\Framework\TestCase;

/**
 * Uji unit OnboardingRules.
 *
 * Memakai PHPUnit\Framework\TestCase polos: aturan ini murni (tanpa database
 * maupun container), sama seperti ReimbursementTotalService. Konfigurasi dimuat
 * langsung dari config/hc_onboarding.php supaya uji ikut gagal bila daftar butir
 * diubah tanpa disengaja.
 */
class OnboardingRulesTest extends TestCase
{
    private array $items;
    private array $groups;

    protected function setUp(): void
    {
        parent::setUp();
        $config = require __DIR__ . '/../../../config/hc_onboarding.php';
        $this->items  = $config['items'];
        $this->groups = $config['groups'];
    }

    /** Fakta karyawan Internal yang datanya lengkap pada seluruh butir. */
    private function completeInternal(): array
    {
        return [
            'employee_type' => 'Internal',
            'basic' => [
                'gender' => 'Male', 'birth_place' => 'Yogyakarta', 'birth_date' => '1995-01-02',
                'religion' => 'Islam', 'marital_status' => 'Single', 'since_date' => '2024-03-01',
            ],
            'address' => [['street' => 'Jl. Contoh 1', 'cell_phone' => '0812345678', 'email_work' => 'a@example.test']],
            'identification' => [
                ['identification_type' => 'KTP', 'identification_number' => '3404010101010001'],
                ['identification_type' => 'NPWP', 'identification_number' => '123456789012345'],
                ['identification_type' => 'BPJS_KESEHATAN', 'identification_number' => '0001234567890'],
                ['identification_type' => 'BPJS_KETENAGAKERJAAN', 'identification_number' => '19012345678'],
            ],
            'bank' => [['bank_name' => 'BCA', 'account_number' => '1234567890', 'account_holder' => 'A B']],
            'contract' => [['is_active' => 1, 'start_date' => '2024-03-01']],
        ];
    }

    public function test_daftar_butir_berjumlah_17_dalam_4_kelompok(): void
    {
        $this->assertCount(17, $this->items);
        $this->assertSame(['profile', 'payroll', 'bpjs', 'contract'], array_keys($this->groups));
        foreach ($this->items as $item) {
            $this->assertArrayHasKey($item['group'], $this->groups, "Butir {$item['key']} merujuk kelompok yang tak ada");
        }
        $this->assertCount(17, array_unique(array_column($this->items, 'key')), 'Kunci butir harus unik');
    }

    public function test_internal_lengkap_menjadi_tuntas_100_persen(): void
    {
        $r = OnboardingRules::evaluate($this->items, $this->groups, $this->completeInternal());

        $this->assertSame(17, $r['total']);
        $this->assertSame(17, $r['done']);
        $this->assertSame(100, $r['percent']);
        $this->assertSame('complete', $r['status']);
        $this->assertSame([], $r['missing']);
    }

    public function test_data_kosong_menjadi_nol_persen_dan_semua_butir_kurang(): void
    {
        $r = OnboardingRules::evaluate($this->items, $this->groups, ['employee_type' => 'Internal']);

        $this->assertSame(17, $r['total']);
        $this->assertSame(0, $r['done']);
        $this->assertSame(0, $r['percent']);
        $this->assertSame('in_progress', $r['status']);
        $this->assertCount(17, $r['missing']);
    }

    public function test_tanpa_ktp_butir_nik_kurang_saja(): void
    {
        $facts = $this->completeInternal();
        $facts['identification'] = array_values(array_filter(
            $facts['identification'],
            fn ($row) => $row['identification_type'] !== 'KTP'
        ));

        $r = OnboardingRules::evaluate($this->items, $this->groups, $facts);

        $this->assertSame(['National ID (NIK / KTP)'], $r['missing']);
        $this->assertSame(16, $r['done']);
        $this->assertSame('in_progress', $r['status']);
        $this->assertSame(1, count($r['groups']['profile']['missing']));
        $this->assertSame([], $r['groups']['payroll']['missing']);
    }

    public function test_baris_identifikasi_tanpa_nomor_tidak_dihitung(): void
    {
        $facts = $this->completeInternal();
        $facts['identification'][1]['identification_number'] = '   '; // NPWP kosong

        $r = OnboardingRules::evaluate($this->items, $this->groups, $facts);

        $this->assertSame(['Tax ID (NPWP)'], $r['missing']);
    }

    public function test_penanda_kosong_lama_dianggap_kosong(): void
    {
        $this->assertFalse(OnboardingRules::filled(null));
        $this->assertFalse(OnboardingRules::filled(''));
        $this->assertFalse(OnboardingRules::filled('  '));
        $this->assertFalse(OnboardingRules::filled('-'));
        $this->assertFalse(OnboardingRules::filled('N/A'));
        $this->assertTrue(OnboardingRules::filled('0'));
        $this->assertTrue(OnboardingRules::filled('Islam'));
    }

    public function test_kontrak_nonaktif_atau_tanpa_tanggal_mulai_tidak_dihitung(): void
    {
        $facts = $this->completeInternal();

        $facts['contract'] = [['is_active' => 0, 'start_date' => '2024-03-01']];
        $r = OnboardingRules::evaluate($this->items, $this->groups, $facts);
        $this->assertContains('Active employment contract', $r['missing']);

        $facts['contract'] = [['is_active' => 1, 'start_date' => null]];
        $r = OnboardingRules::evaluate($this->items, $this->groups, $facts);
        $this->assertContains('Active employment contract', $r['missing']);
    }

    public function test_external_hanya_dinilai_pada_butir_profil_dasar(): void
    {
        $facts = $this->completeInternal();
        $facts['employee_type'] = 'External';
        $facts['identification'] = [];
        $facts['bank'] = [];
        $facts['contract'] = [];

        $r = OnboardingRules::evaluate($this->items, $this->groups, $facts);

        // 8 butir Internal-saja (NIK, 4 payroll, 2 BPJS, kontrak) tidak berlaku bagi
        // External; tersisa 9 butir profil dasar + tanggal bergabung.
        $this->assertSame(9, $r['total']);
        $this->assertSame(9, $r['done']);
        $this->assertSame('complete', $r['status']);
        $this->assertSame(0, $r['groups']['payroll']['total']);
        $this->assertSame(0, $r['groups']['bpjs']['total']);
    }

    public function test_jenis_karyawan_kosong_diperlakukan_sebagai_internal(): void
    {
        $r = OnboardingRules::evaluate($this->items, $this->groups, ['employee_type' => null]);
        $this->assertSame(17, $r['total']);
    }

    public function test_persen_dibulatkan_tetapi_status_tuntas_tidak(): void
    {
        $facts = $this->completeInternal();
        $facts['basic']['since_date'] = null; // 16/17 = 94,1%

        $r = OnboardingRules::evaluate($this->items, $this->groups, $facts);

        $this->assertSame(94, $r['percent']);
        $this->assertSame('in_progress', $r['status']);
    }
}
