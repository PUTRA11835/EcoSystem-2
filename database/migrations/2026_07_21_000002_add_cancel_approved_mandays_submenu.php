<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $parent = DB::table('menu')->where('slug', 'ticket.review-mandays')->first();
        if (!$parent) {
            return;
        }

        $slug = 'ticket.review-mandays.cancel-approved';

        $existing = DB::table('menu')->where('slug', $slug)->first();
        if ($existing) {
            $menuId = $existing->id;
        } else {
            $menuId = DB::table('menu')->insertGetId([
                'parent_id'  => $parent->id,
                'name'       => 'Cancel Approved Proposal',
                'slug'       => $slug,
                'type'       => 'function',
                'route_name' => null,
                'icon'       => null,
                'order_seq'  => 6,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Same roles as the base 'ticket.review-mandays.cancel' permission (Admin + Helpdesk).
        foreach ([1, 6] as $roleId) {
            DB::table('role_menu')->updateOrInsert(
                ['role_id' => $roleId, 'menu_id' => $menuId],
                ['can_view' => true, 'can_create' => false, 'can_edit' => false, 'can_delete' => false, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        $menu = DB::table('menu')->where('slug', 'ticket.review-mandays.cancel-approved')->first();
        if ($menu) {
            DB::table('role_menu')->where('menu_id', $menu->id)->delete();
            DB::table('menu')->where('id', $menu->id)->delete();
        }
    }
};
