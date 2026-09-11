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

        // Insert menu item jika belum ada
        $existing = DB::table('menu')->where('slug', 'ticket.assign-delivery-support')->first();
        if ($existing) {
            $menuId = $existing->id;
        } else {
            $menuId = DB::table('menu')->insertGetId([
                'parent_id'  => $parent->id,
                'name'       => 'Assign to Delivery Support',
                'slug'       => 'ticket.assign-delivery-support',
                'type'       => 'function',
                'route_name' => null,
                'icon'       => null,
                'order_seq'  => 12,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Default permission: Admin(1), Delivery Support Head(5), Helpdesk(6), RPMO Head(7)
        $roles = [1, 5, 6, 7];
        foreach ($roles as $roleId) {
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

    public function down(): void
    {
        $menu = DB::table('menu')->where('slug', 'ticket.assign-delivery-support')->first();
        if ($menu) {
            DB::table('role_menu')->where('menu_id', $menu->id)->delete();
            DB::table('menu')->where('id', $menu->id)->delete();
        }
    }
};
