<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Who can be SELECTED as Ticket Lead / Ticket Member was hardcoded in PHP:
 * - Ticket Lead dropdown: Employee::withAnyRole([RoleId::DELIVERY_SUPPORT_USER])
 * - Member dropdown: no restriction at all (any active employee)
 * This adds two menu permissions so eligibility is configurable per role via
 * Management > Permissions instead of a code change. Default grant preserves/tightens
 * to Delivery Support User only (role_id 2) for both, per product decision.
 */
return new class extends Migration
{
    public function up(): void
    {
        $parent = DB::table('menu')->where('slug', 'tickets.inbox')->first();
        if (!$parent) {
            return;
        }

        $now = now();
        $menus = [
            ['slug' => 'ticket.eligible-ticket-lead',   'name' => 'Eligible as Ticket Lead',   'order_seq' => 23],
            ['slug' => 'ticket.eligible-ticket-member', 'name' => 'Eligible as Ticket Member', 'order_seq' => 24],
        ];

        $menuIds = [];
        foreach ($menus as $menu) {
            $existing = DB::table('menu')->where('slug', $menu['slug'])->first();
            if ($existing) {
                $menuIds[$menu['slug']] = $existing->id;
                continue;
            }
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

        // Default: role 2 (Delivery Support User) only.
        foreach ($menuIds as $menuId) {
            DB::table('role_menu')->updateOrInsert(
                ['role_id' => 2, 'menu_id' => $menuId],
                ['can_view' => true, 'can_create' => false, 'can_edit' => false, 'can_delete' => false, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        $slugs = ['ticket.eligible-ticket-lead', 'ticket.eligible-ticket-member'];
        foreach ($slugs as $slug) {
            $menu = DB::table('menu')->where('slug', $slug)->first();
            if ($menu) {
                DB::table('role_menu')->where('menu_id', $menu->id)->delete();
                DB::table('menu')->where('id', $menu->id)->delete();
            }
        }
    }
};
