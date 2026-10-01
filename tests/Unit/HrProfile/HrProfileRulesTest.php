<?php

namespace Tests\Unit\HrProfile;

use App\Services\HrProfile\EmploymentStatus;
use App\Services\HrProfile\HrProfileFieldPolicy;
use App\Services\HrProfile\ProfileLockPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Uji aturan murni profil HR (H3.2): status kepegawaian, daftar field per peran, kunci profil.
 * Tanpa database maupun router.
 */
class HrProfileRulesTest extends TestCase
{
    // ── Status kepegawaian ─────────────────────────────────────────────────

    public function test_status_valid_dan_tidak_valid(): void
    {
        foreach (['probation', 'contract', 'permanent', 'internship', 'consultant'] as $s) {
            $this->assertTrue(EmploymentStatus::isValid($s), $s);
        }
        foreach ([null, '', 'PROBATION', 'tetap', 'owner'] as $s) {
            $this->assertFalse(EmploymentStatus::isValid($s), var_export($s, true));
        }
    }

    public function test_probation_wajib_tanggal_akhir(): void
    {
        $this->assertArrayHasKey('probation_end_date', EmploymentStatus::validate('probation', null));
        $this->assertArrayHasKey('probation_end_date', EmploymentStatus::validate('probation', '  '));
        $this->assertSame([], EmploymentStatus::validate('probation', '2026-12-31'));
        $this->assertSame([], EmploymentStatus::validate('permanent', null));
    }

    public function test_status_kosong_dibolehkan_dan_nilai_asing_ditolak(): void
    {
        $this->assertSame([], EmploymentStatus::validate(null, null));
        $this->assertSame([], EmploymentStatus::validate('', null));
        $this->assertArrayHasKey('employment_status', EmploymentStatus::validate('boss', null));
    }

    public function test_deskripsi_riwayat_memuat_dari_ke_dan_alasan(): void
    {
        $this->assertSame('Employment status: — → Probation', EmploymentStatus::changeDescription(null, 'probation', null));
        $this->assertSame(
            'Employment status: Probation → Permanent (PKWTT). Reason: Passed evaluation',
            EmploymentStatus::changeDescription('probation', 'permanent', ' Passed evaluation ')
        );
    }

    // ── Daftar field per peran ─────────────────────────────────────────────

    public function test_pemilik_hanya_boleh_data_pribadi(): void
    {
        $r = HrProfileFieldPolicy::filter([
            'blood_type' => 'O', 'emergency_contact_phone' => '0812',
            'employment_status' => 'permanent', 'grade_id' => 3, 'hr_notes' => 'x',
        ], 'self');

        $this->assertSame(['blood_type' => 'O', 'emergency_contact_phone' => '0812'], $r['allowed']);
        $this->assertSame(['employment_status', 'grade_id', 'hr_notes'], $r['rejected']);
    }

    public function test_hr_boleh_kepegawaian_dan_data_pribadi(): void
    {
        $r = HrProfileFieldPolicy::filter(['employment_status' => 'contract', 'blood_type' => 'A', 'hr_notes' => 'ok'], 'hr');
        $this->assertSame([], $r['rejected']);
        $this->assertCount(3, $r['allowed']);
    }

    public function test_field_sistem_payroll_dan_asing_tak_pernah_diterima(): void
    {
        $bad = ['id' => 1, 'employee_id' => 9, 'locked_at' => 'now', 'locked_by' => 1, 'updated_by' => 1,
            'photo_path' => '../../x', 'signature_path' => 'x', 'ptkp_code' => 'K/1', 'dependents_count' => 2,
            'bpjs_health_active' => 1, 'payroll_activated' => 1, 'sembarang' => 'x'];

        foreach (['hr', 'self'] as $actor) {
            $r = HrProfileFieldPolicy::filter($bad, $actor);
            $this->assertSame([], $r['allowed'], $actor);
            $this->assertCount(count($bad), $r['rejected'], $actor);
        }
    }

    public function test_peran_tak_dikenal_tidak_boleh_apa_apa(): void
    {
        $this->assertSame([], HrProfileFieldPolicy::allowedFor('customer'));
        $this->assertSame([], HrProfileFieldPolicy::filter(['blood_type' => 'A'], 'customer')['allowed']);
    }

    public function test_golongan_darah_dinormalkan(): void
    {
        $this->assertSame('AB', HrProfileFieldPolicy::normalizeBloodType(' ab '));
        $this->assertNull(HrProfileFieldPolicy::normalizeBloodType('X'));
        $this->assertNull(HrProfileFieldPolicy::normalizeBloodType(''));
        $this->assertNull(HrProfileFieldPolicy::normalizeBloodType(null));
    }

    // ── Kunci profil ───────────────────────────────────────────────────────

    public function test_hanya_progres_penuh_yang_boleh_dikunci(): void
    {
        $this->assertTrue(ProfileLockPolicy::canLock('complete'));
        $this->assertFalse(ProfileLockPolicy::canLock('in_progress'));
        $this->assertFalse(ProfileLockPolicy::canLock(null));
    }

    public function test_tanpa_kunci_tidak_ada_yang_diblokir_sehingga_rilis_tidak_mengubah_perilaku(): void
    {
        foreach (['basic_data', 'address', 'identification', 'bank', 'hr_profile', 'family', 'attachment'] as $s) {
            $this->assertFalse(ProfileLockPolicy::blocksOwnerUpdate(false, $s), $s);
        }
    }

    public function test_terkunci_memblokir_seksi_yang_dinilai_onboarding_saja(): void
    {
        foreach (['basic_data', 'basic-data', 'address', 'identification', 'bank', 'hr_profile'] as $s) {
            $this->assertTrue(ProfileLockPolicy::blocksOwnerUpdate(true, $s), $s);
        }
        foreach (['family', 'education', 'qualification', 'payment', 'attachment', 'contract'] as $s) {
            $this->assertFalse(ProfileLockPolicy::blocksOwnerUpdate(true, $s), $s);
        }
    }

    public function test_seksi_yang_dikunci_selaras_dengan_seksi_yang_dinilai_onboarding(): void
    {
        $config = require __DIR__ . '/../../../config/hc_onboarding.php';
        $assessed = array_unique(array_map(
            fn ($i) => str_replace('-', '_', $i['section']),
            $config['items']
        ));

        // 'contract' dinilai tetapi dikelola HR (pemilik memang tak bisa mengubahnya) — tak perlu dikunci.
        foreach (array_diff($assessed, ['contract']) as $section) {
            $this->assertContains($section, ProfileLockPolicy::LOCKED_SECTIONS, "seksi '$section' dinilai Onboarding tetapi tidak dikunci");
        }
    }

    public function test_alasan_buka_kunci_wajib_bermakna(): void
    {
        $this->assertFalse(ProfileLockPolicy::validUnlockReason(null));
        $this->assertFalse(ProfileLockPolicy::validUnlockReason('  ok '));
        $this->assertTrue(ProfileLockPolicy::validUnlockReason('Bank account changed'));
    }

    public function test_deskripsi_riwayat_kunci(): void
    {
        $this->assertSame('Profile verified and locked by HR', ProfileLockPolicy::historyDescription('lock'));
        $this->assertSame('Profile unlocked by HR. Reason: New bank', ProfileLockPolicy::historyDescription('unlock', ' New bank '));
    }
}
