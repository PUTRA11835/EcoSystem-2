<?php

namespace Tests\Unit\Permissions;

use App\Services\Permissions\MenuAccessLayout;
use PHPUnit\Framework\TestCase;

/** Penyajian Menu Access: aksi dilipat ke halaman, tab jadi baris sendiri (tanpa mengubah slug apa pun). */
class MenuAccessLayoutTest extends TestCase
{
    private function build(array $menus): array
    {
        $cfg = [
            'crud_enforced' => ['general.letters.requests'],
            'hubs' => [['key' => 'att', 'label' => 'Attendance', 'tabs' => ['general.attendance', 'general.attendance.monthly', 'general.settings.shifts']]],
            'section_hubs' => ['employee.section.' => 'Employee detail'],
        ];
        $out = [];
        foreach ((new MenuAccessLayout($cfg))->build($menus) as $r) {
            $out[$r['slug']] = $r;
        }

        return $out;
    }

    private function m(int $id, ?int $parent, string $slug, string $type, ?string $name = null): array
    {
        return ['id' => $id, 'parent_id' => $parent, 'name' => $name ?? $slug, 'slug' => $slug, 'type' => $type];
    }

    public function test_aksi_dilipat_ke_kolom_halaman_dan_sisanya_other_actions(): void
    {
        $rows = $this->build([
            $this->m(1, null, 'general', 'page'),
            $this->m(2, 1, 'general.kpi-evaluation', 'page', 'KPI Evaluation — HR Dashboard'),
            $this->m(3, 1, 'general.kpi-evaluation.create', 'function', 'KPI Evaluation — Create Evaluation'),
            $this->m(4, 1, 'general.kpi-evaluation.approve', 'function', 'KPI Evaluation — Approve / Reject'),
            $this->m(5, 1, 'general.kpi-evaluation.manage', 'function', 'KPI Evaluation — Edit / Delete'),
        ]);

        $r = $rows['general.kpi-evaluation'];
        $this->assertSame('page', $r['kind']);
        $this->assertSame(3, $r['cells']['c']['id']);
        $this->assertSame(5, $r['cells']['e']['id']);
        $this->assertSame(5, $r['cells']['d']['id'], 'manage "Edit / Delete" menguasai dua kolom');
        $this->assertSame(['Approve / Reject'], array_column($r['actions'], 'label'));
        $this->assertSame([2, 3, 4, 5], $r['menu_ids']);
        $this->assertArrayNotHasKey('general.kpi-evaluation.create', $rows, 'fungsi tidak jadi baris sendiri');
    }

    public function test_tab_hub_adalah_baris_sendiri_dan_fungsi_tab_tetap_tab(): void
    {
        $rows = $this->build([
            $this->m(1, null, 'general', 'page'),
            $this->m(2, 1, 'general.attendance', 'page'),
            $this->m(3, 1, 'general.attendance.monthly', 'function', 'Attendance — Monthly Recap'),
            $this->m(4, 1, 'general.attendance.export', 'function', 'Attendance — Export Excel'),
            $this->m(5, 1, 'general.settings.shifts', 'page'),
        ]);

        $this->assertSame('tab', $rows['general.attendance.monthly']['kind']);
        $this->assertSame('Attendance', $rows['general.settings.shifts']['hub']['label']);
        $this->assertSame(['Export Excel'], array_column($rows['general.attendance']['actions'], 'label'));
        $this->assertNull($rows['general']['hub']);
    }

    public function test_seksi_view_update_menjadi_satu_baris_tab(): void
    {
        $rows = $this->build([
            $this->m(1, null, 'master.employee', 'page', 'Employee'),
            $this->m(2, 1, 'employee.section.bank', 'group', 'Bank Account'),
            $this->m(3, 2, 'employee.section.bank.view', 'function', 'View Bank Account'),
            $this->m(4, 2, 'employee.section.bank.update', 'function', 'Update Bank Account'),
        ]);

        $this->assertCount(2, $rows);
        $s = $rows['employee.section.bank'];
        $this->assertSame('section', $s['kind']);
        $this->assertSame(3, $s['main'], 'View = slug .view');
        $this->assertSame(4, $s['cells']['e']['id']);
        $this->assertSame('Employee detail', $s['hub']['label']);
        $this->assertSame('m1', $s['parent_key']);
    }

    public function test_seksi_yang_menegakkan_crud_mendapat_create_dan_delete_pada_baris_view(): void
    {
        $menus = [
            $this->m(1, null, 'master.employee', 'page'),
            $this->m(2, 1, 'employee.section.bank', 'group', 'Bank Account'),
            $this->m(3, 2, 'employee.section.bank.view', 'function', 'View Bank Account'),
            $this->m(4, 2, 'employee.section.bank.update', 'function', 'Update Bank Account'),
            $this->m(5, 1, 'employee.section.basic_data', 'group', 'Basic Data'),
            $this->m(6, 5, 'employee.section.basic_data.view', 'function', 'View Basic Data'),
            $this->m(7, 5, 'employee.section.basic_data.update', 'function', 'Update Basic Data'),
        ];

        $out = [];
        $layout = new MenuAccessLayout([
            'section_hubs' => ['employee.section.' => 'Employee detail'],
            'crud_enforced' => ['employee.section.bank.view'],
        ]);
        foreach ($layout->build($menus) as $r) {
            $out[$r['slug']] = $r;
        }

        $bank = $out['employee.section.bank'];
        $this->assertSame(['id' => 3, 'k' => 'c'], ['id' => $bank['cells']['c']['id'], 'k' => $bank['cells']['c']['k']], 'Create = flag pada baris .view');
        $this->assertSame(['id' => 3, 'k' => 'd'], ['id' => $bank['cells']['d']['id'], 'k' => $bank['cells']['d']['k']], 'Delete = flag pada baris .view');
        $this->assertSame(4, $bank['cells']['e']['id'], 'Edit tetap slug .update');

        $basic = $out['employee.section.basic_data'];
        $this->assertNull($basic['cells']['c'], 'satu rekaman per karyawan: tidak ada Create');
        $this->assertNull($basic['cells']['d']);
        $this->assertSame(7, $basic['cells']['e']['id']);
    }

    public function test_flag_crud_halaman_dipakai_dan_fungsi_bentrok_masuk_other_actions(): void
    {
        $rows = $this->build([
            $this->m(1, null, 'general.letters.requests', 'page'),
            $this->m(2, 1, 'general.letters.requests.create', 'function', 'Create'),
        ]);

        $r = $rows['general.letters.requests'];
        $this->assertSame('c', $r['cells']['c']['k'], 'kolom Create memakai flag halaman');
        $this->assertSame(1, $r['cells']['c']['id']);
        $this->assertCount(1, $r['actions']);
    }

    public function test_sub_aksi_dari_aksi_tidak_dipetakan_ke_kolom(): void
    {
        $rows = $this->build([
            $this->m(1, null, 'tickets.inbox', 'page'),
            $this->m(2, 1, 'ticket.review-mandays', 'function', 'Review Mandays Proposal'),
            $this->m(3, 2, 'ticket.review-mandays.edit-activity', 'function', 'Edit Activity'),
        ]);

        $r = $rows['tickets.inbox'];
        $this->assertNull($r['cells']['e'], '"Edit Activity" bukan Edit halaman tiket');
        $this->assertSame(['Review Mandays Proposal', 'Review Mandays Proposal › Edit Activity'], array_column($r['actions'], 'label'));
    }

    public function test_setiap_menu_muncul_tepat_sekali_kecuali_kontainer_seksi(): void
    {
        $menus = [
            $this->m(1, null, 'general', 'page'),
            $this->m(2, 1, 'general.attendance', 'page'),
            $this->m(3, 1, 'general.attendance.export', 'function'),
            $this->m(4, 1, 'general.command-center.pending-approval', 'function'),
        ];
        $seen = [];
        foreach ($this->build($menus) as $r) {
            foreach ($r['menu_ids'] as $id) {
                $this->assertArrayNotHasKey($id, $seen, "menu {$id} muncul dua kali");
                $seen[$id] = true;
            }
        }
        $this->assertCount(4, $seen);
    }
}
