<?php

namespace Tests\Unit\Permissions;

use App\Services\Permissions\MenuAccessRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Uji unit aturan murni Menu Access (HC-D63/D64): klasifikasi, normalisasi perubahan, perbandingan keadaan.
 * Konfigurasi dimuat langsung dari config/menu_access.php supaya uji ikut gagal bila pengelompokan berubah tanpa sengaja.
 */
class MenuAccessRulesTest extends TestCase
{
    private MenuAccessRules $rules;
    private array $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = require __DIR__ . '/../../../config/menu_access.php';
        $this->rules = new MenuAccessRules($this->config);
    }

    public function test_kunci_modul_unik_dan_modul_lainnya_ada(): void
    {
        $keys = array_column($this->config['modules'], 'key');
        $this->assertSame($keys, array_values(array_unique($keys)), 'kunci modul harus unik');
        $this->assertSame('other', $this->config['other_module']['key']);
        $mods = $this->rules->modules();
        $this->assertSame('other', end($mods)['key'], 'modul "lainnya" harus terakhir');
    }

    #[DataProvider('slugModul')]
    public function test_pengelompokan_modul(string $slug, string $expected): void
    {
        $this->assertSame($expected, $this->rules->moduleOf($slug)['key'], $slug);
    }

    public static function slugModul(): array
    {
        return [
            ['general.attendance.export', 'hr-attendance'],
            ['general.settings.shifts', 'hr-attendance'],
            ['general.overtime.approve', 'hr-overtime'],
            ['general.cash-advance-report', 'hr-ca'],
            ['management.cash-advance-settings', 'hr-ca'],
            ['general.recruitment.offers', 'hr-recruit'],
            ['general.letter-templates', 'hr-recruit'],
            ['general.kpi-evaluation.create', 'hr-kpi'],
            ['general.onboarding.lock', 'hr-onboard'],
            ['general.command-center.pending-approval', 'hr-onboard'],
            ['general.approval-workflow.overtime', 'hr-approval'],
            ['hr_general.leave_permit', 'hr-leave'],
            ['general', 'hr-other'],
            ['general.my-attendance', 'ess'],
            ['my-profile.section.bank.update', 'ess'],
            ['ess.my_leave_permit', 'ess'],
            ['employee.section.contract.view', 'employee'],
            ['customer.section.bank.view', 'customer'],
            ['master.customer', 'customer'],
            ['ticket.delete', 'ticketing'],
            ['delivery-project.team.delete', 'delivery'],
            ['reporting.md-recap', 'reporting'],
            ['calendar.events', 'calendar'],
            ['rpmo.overview', 'sla-rpmo'],
            ['management.roles', 'management'],
            ['control-center.audit-log', 'control'],
            ['dashboard', 'apps'],
            ['modul.baru.yang.belum.dipetakan', 'other'],  // TIDAK PERNAH tersembunyi
        ];
    }

    public function test_kelas_izin_ess_sensitif_standar(): void
    {
        $this->assertSame('ess', $this->rules->classOf('general.my-attendance'));
        $this->assertSame('ess', $this->rules->classOf('ess.my_leave_permit'));
        $this->assertSame('sensitive', $this->rules->classOf('management.roles'));
        $this->assertSame('sensitive', $this->rules->classOf('control-center.sessions'));
        $this->assertSame('sensitive', $this->rules->classOf('employee.section.bank.update'));
        $this->assertSame('standard', $this->rules->classOf('calendar.events'));
        $this->assertSame('standard', $this->rules->classOf('general.overtime'));
    }

    public function test_crud_hanya_untuk_slug_yang_menegakkannya(): void
    {
        $this->assertTrue($this->rules->crudEnforced('general.recruitment.jobs'));
        $this->assertTrue($this->rules->crudEnforced('general.letter-templates'));
        $this->assertFalse($this->rules->crudEnforced('ticket.delete'));
        $this->assertFalse($this->rules->crudEnforced('general.overtime'));
    }

    public function test_slug_terlindung_hanya_untuk_role_admin(): void
    {
        $this->assertTrue($this->rules->isProtected(1, 'management.roles'));
        $this->assertTrue($this->rules->isProtected(1, 'management.permissions'));
        $this->assertFalse($this->rules->isProtected(2, 'management.roles'));
        $this->assertFalse($this->rules->isProtected(1, 'calendar.events'));
    }

    public function test_normalisasi_view_otomatis_dan_pencabutan(): void
    {
        $this->assertSame(['menu_id' => 5, 'after' => ['v' => 1, 'c' => 0, 'e' => 0, 'd' => 0]], MenuAccessRules::normalize(['menu_id' => 5, 'can_view' => true]));
        // memberi C/E/D otomatis memberi View
        $n = MenuAccessRules::normalize(['menu_id' => 5, 'can_edit' => true], true);
        $this->assertSame(['v' => 1, 'c' => 0, 'e' => 1, 'd' => 0], $n['after']);
        // keempat mati → cabut
        $this->assertNull(MenuAccessRules::normalize(['menu_id' => 5, 'can_view' => false, 'can_create' => false])['after']);
        $this->assertNull(MenuAccessRules::normalize(['menu_id' => 5, 'revoke' => true, 'can_view' => true])['after']);
    }

    public function test_menu_tanpa_penegakan_crud_mempertahankan_flag_lama_dan_mengabaikan_kiriman(): void
    {
        $before = ['v' => 1, 'c' => 1, 'e' => 1, 'd' => 0];
        // kiriman C/E/D diabaikan; flag tersimpan dipertahankan (tidak dihapus diam-diam)
        $n = MenuAccessRules::normalize(['menu_id' => 9, 'can_view' => true, 'can_create' => false, 'can_delete' => true], false, $before);
        $this->assertSame($before, $n['after']);
        // memberi baru pada menu tak-ditegakkan: hanya View
        $n = MenuAccessRules::normalize(['menu_id' => 9, 'can_view' => true, 'can_edit' => true], false, null);
        $this->assertSame(['v' => 1, 'c' => 0, 'e' => 0, 'd' => 0], $n['after']);
        // flag lama saja TIDAK menyalakan View
        $this->assertNull(MenuAccessRules::normalize(['menu_id' => 9, 'can_view' => false], false, $before)['after']);
    }

    public function test_menu_id_wajib(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        MenuAccessRules::normalize(['can_view' => true]);
    }

    public function test_jenis_perubahan_dan_ringkasan(): void
    {
        $v = ['v' => 1, 'c' => 0, 'e' => 0, 'd' => 0];
        $ve = ['v' => 1, 'c' => 0, 'e' => 1, 'd' => 0];
        $this->assertSame('none', MenuAccessRules::kind($v, $v));
        $this->assertSame('none', MenuAccessRules::kind(null, null));
        $this->assertSame('grant', MenuAccessRules::kind(null, $v));
        $this->assertSame('revoke', MenuAccessRules::kind($v, null));
        $this->assertSame('change', MenuAccessRules::kind($v, $ve));

        $s = MenuAccessRules::summarize([['kind' => 'grant'], ['kind' => 'grant'], ['kind' => 'revoke'], ['kind' => 'change']]);
        $this->assertSame(['grant' => 2, 'revoke' => 1, 'change' => 1, 'total' => 4], $s);
    }

    public function test_balik_catatan_riwayat_untuk_undo(): void
    {
        $this->assertSame(['menu_id' => 7, 'revoke' => true], MenuAccessRules::invert(['menu_id' => 7, 'before' => null, 'after' => ['v' => 1, 'c' => 0, 'e' => 0, 'd' => 0]]));
        $this->assertSame(
            ['menu_id' => 7, 'can_view' => true, 'can_create' => true, 'can_edit' => false, 'can_delete' => false],
            MenuAccessRules::invert(['menu_id' => 7, 'before' => ['v' => 1, 'c' => 1, 'e' => 0, 'd' => 0], 'after' => null])
        );
    }

    /** Regresi: kolom JSON MySQL mengurutkan ulang kunci (c,d,e,v) — `===` antar-array lalu salah menganggap "berubah" dan undo tak pernah berhasil. */
    public function test_perbandingan_keadaan_tidak_bergantung_urutan_kunci(): void
    {
        $this->assertTrue(MenuAccessRules::same(['v' => 1, 'c' => 1, 'e' => 0, 'd' => 0], ['c' => 1, 'd' => 0, 'e' => 0, 'v' => 1]));
        $this->assertTrue(MenuAccessRules::same(null, null));
        $this->assertFalse(MenuAccessRules::same(null, ['v' => 1, 'c' => 0, 'e' => 0, 'd' => 0]));
        $this->assertFalse(MenuAccessRules::same(['v' => 1, 'c' => 0, 'e' => 0, 'd' => 0], ['v' => 1, 'c' => 0, 'e' => 1, 'd' => 0]));
        $this->assertSame('none', MenuAccessRules::kind(['v' => 1, 'c' => 1, 'e' => 0, 'd' => 0], ['d' => 0, 'e' => 0, 'c' => 1, 'v' => 1]));
    }

    public function test_keadaan_dari_baris_pivot(): void
    {
        $this->assertNull(MenuAccessRules::stateFromRow(null));
        $this->assertSame(['v' => 1, 'c' => 1, 'e' => 0, 'd' => 1], MenuAccessRules::stateFromRow((object) ['can_create' => 1, 'can_edit' => 0, 'can_delete' => 1]));
    }
}
