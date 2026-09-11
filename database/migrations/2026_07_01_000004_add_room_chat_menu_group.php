<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Roles that previously had ui.ticket.sidebar-tabs (see MenuSeeder matrix)
    private const ROLE_IDS = [1, 2, 5, 6, 7]; // ADMIN, EMPLOYEE, HOS, HELPDESK, RPMO

    public function up(): void
    {
        $now = now();

        // ── Room Chat group (expandable in Management > Role) ──────────────────
        $groupId = DB::table('menu')->insertGetId([
            'parent_id'  => null,
            'name'       => 'Room Chat',
            'slug'       => 'room-chat',
            'type'       => 'group',
            'route_name' => null,
            'icon'       => 'fa-comments',
            'order_seq'  => 8,
            'is_active'  => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $children = [
            ['slug' => 'room-chat.tab-all-ticket', 'name' => 'All Ticket Button', 'order_seq' => 1],
            ['slug' => 'room-chat.tab-my-ticket',  'name' => 'My Ticket Button',  'order_seq' => 2],
        ];

        $childIds = [];
        foreach ($children as $child) {
            $childIds[$child['slug']] = DB::table('menu')->insertGetId([
                'parent_id'  => $groupId,
                'name'       => $child['name'],
                'slug'       => $child['slug'],
                'type'       => 'function',
                'route_name' => null,
                'icon'       => null,
                'order_seq'  => $child['order_seq'],
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->grantRoles($groupId, self::ROLE_IDS, $now);
        foreach ($childIds as $menuId) {
            $this->grantRoles($menuId, self::ROLE_IDS, $now);
        }

        // ── Retire the old combined toggle (superseded by the two above) ───────
        $old = DB::table('menu')->where('slug', 'ui.ticket.sidebar-tabs')->first();
        if ($old) {
            DB::table('role_menu')->where('menu_id', $old->id)->delete();
            DB::table('menu')->where('id', $old->id)->delete();
        }
    }

    public function down(): void
    {
        // Restore the old combined toggle
        $parent = DB::table('menu')->where('slug', 'tickets.inbox')->first();
        if ($parent && !DB::table('menu')->where('slug', 'ui.ticket.sidebar-tabs')->exists()) {
            $now = now();
            $oldId = DB::table('menu')->insertGetId([
                'parent_id'  => $parent->id,
                'name'       => 'Sidebar Tabs',
                'slug'       => 'ui.ticket.sidebar-tabs',
                'type'       => 'function',
                'route_name' => null,
                'icon'       => null,
                'order_seq'  => 8,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->grantRoles($oldId, self::ROLE_IDS, $now);
        }

        foreach (['room-chat.tab-all-ticket', 'room-chat.tab-my-ticket', 'room-chat'] as $slug) {
            $menu = DB::table('menu')->where('slug', $slug)->first();
            if ($menu) {
                DB::table('role_menu')->where('menu_id', $menu->id)->delete();
                DB::table('menu')->where('id', $menu->id)->delete();
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
