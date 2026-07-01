<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $parent = DB::table('menu')->where('slug', 'delivery.support')->first();
        if (!$parent) {
            return;
        }

        $existing = DB::table('menu')->where('slug', 'delivery-support.manage-customer-pic')->first();
        if ($existing) {
            $menuId = $existing->id;
        } else {
            $menuId = DB::table('menu')->insertGetId([
                'parent_id'  => $parent->id,
                'name'       => 'Manage Customer PIC',
                'slug'       => 'delivery-support.manage-customer-pic',
                'type'       => 'function',
                'route_name' => null,
                'icon'       => null,
                'order_seq'  => 10,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Default: EC Administrator (1) dan DS Head (5)
        foreach ([1, 5] as $roleId) {
            DB::table('role_menu')->updateOrInsert(
                ['role_id' => $roleId, 'menu_id' => $menuId],
                ['can_view' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        $menu = DB::table('menu')->where('slug', 'delivery-support.manage-customer-pic')->first();
        if ($menu) {
            DB::table('role_menu')->where('menu_id', $menu->id)->delete();
            DB::table('menu')->where('id', $menu->id)->delete();
        }
    }
};
