<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // Cari parent menu tickets.inbox
        $parent = DB::table('menu')->where('slug', 'tickets.inbox')->first();
        if (!$parent) {
            return;
        }

        // ── Insert 6 menu items baru ─────────────────────────────────────────────
        $newMenus = [
            ['slug' => 'ticket.propose-mandays',  'name' => 'Propose Mandays',           'order_seq' => 13],
            ['slug' => 'ticket.review-mandays',   'name' => 'Review Mandays Proposal',   'order_seq' => 14],
            ['slug' => 'ticket.head-mandays',     'name' => 'Head Mandays & Resolution', 'order_seq' => 15],
            ['slug' => 'ticket.view-credential',  'name' => 'View Customer Credential',  'order_seq' => 16],
            ['slug' => 'ticket.meeting',          'name' => 'Meeting',                   'order_seq' => 17],
            ['slug' => 'ticket.delete',           'name' => 'Delete Ticket',             'order_seq' => 18],
        ];

        $menuIds = [];
        foreach ($newMenus as $menu) {
            $existing = DB::table('menu')->where('slug', $menu['slug'])->first();
            if ($existing) {
                $menuIds[$menu['slug']] = $existing->id;
            } else {
                $menuIds[$menu['slug']] = DB::table('menu')->insertGetId([
                    'parent_id'  => $parent->id,
                    'name'       => $menu['name'],
                    'slug'       => $menu['slug'],
                    'type'       => 'function',
                    'route_name' => null,
                    'icon'       => null,
                    'order_seq'  => $menu['order_seq'],
                    'is_active'  => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // ── Default permissions untuk menu baru ──────────────────────────────────
        // ticket.propose-mandays  → role 2 (EMPLOYEE)
        $this->grantRoles($menuIds['ticket.propose-mandays'], [2], $now);

        // ticket.review-mandays   → role 6 (HELPDESK), 7 (RPMO)
        $this->grantRoles($menuIds['ticket.review-mandays'], [6, 7], $now);

        // ticket.head-mandays     → role 5 (HOS)
        $this->grantRoles($menuIds['ticket.head-mandays'], [5], $now);

        // ticket.view-credential  → role 1,5,6,7
        $this->grantRoles($menuIds['ticket.view-credential'], [1, 5, 6, 7], $now);

        // ticket.meeting          → role 1,5,6,7
        $this->grantRoles($menuIds['ticket.meeting'], [1, 5, 6, 7], $now);

        // ticket.delete           → role 1 (ADMIN)
        $this->grantRoles($menuIds['ticket.delete'], [1], $now);

        // ── Update existing menus: tambah role 5 (HOS) ───────────────────────────
        $existingSlugs = [
            'ui.ticket.sidebar-tabs',
            'ui.ticket.edit-fields',
            'ui.ticket.manage-members',
        ];

        foreach ($existingSlugs as $slug) {
            $menu = DB::table('menu')->where('slug', $slug)->first();
            if (!$menu) {
                continue;
            }
            DB::table('role_menu')->updateOrInsert(
                ['role_id' => 5, 'menu_id' => $menu->id],
                [
                    'can_view'   => true,
                    'can_create' => false,
                    'can_edit'   => false,
                    'can_delete' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        $slugsToRemove = [
            'ticket.propose-mandays',
            'ticket.review-mandays',
            'ticket.head-mandays',
            'ticket.view-credential',
            'ticket.meeting',
            'ticket.delete',
        ];

        foreach ($slugsToRemove as $slug) {
            $menu = DB::table('menu')->where('slug', $slug)->first();
            if ($menu) {
                DB::table('role_menu')->where('menu_id', $menu->id)->delete();
                DB::table('menu')->where('id', $menu->id)->delete();
            }
        }

        // Revert role 5 (HOS) from the three existing menus
        $existingSlugs = [
            'ui.ticket.sidebar-tabs',
            'ui.ticket.edit-fields',
            'ui.ticket.manage-members',
        ];

        foreach ($existingSlugs as $slug) {
            $menu = DB::table('menu')->where('slug', $slug)->first();
            if ($menu) {
                DB::table('role_menu')
                    ->where('role_id', 5)
                    ->where('menu_id', $menu->id)
                    ->delete();
            }
        }
    }

    private function grantRoles(int $menuId, array $roleIds, $now): void
    {
        foreach ($roleIds as $roleId) {
            DB::table('role_menu')->updateOrInsert(
                ['role_id' => $roleId, 'menu_id' => $menuId],
                [
                    'can_view'   => true,
                    'can_create' => false,
                    'can_edit'   => false,
                    'can_delete' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
};
