<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $parent = DB::table('menu')->where('slug', 'control-center')->value('id');
        if (!$parent) return;

        $menuId = DB::table('menu')->where('slug', 'control-center.login-log')->value('id');

        if (!$menuId) {
            $menuId = DB::table('menu')->insertGetId([
                'slug'       => 'control-center.login-log',
                'name'       => 'Login Log',
                'type'       => 'page',
                'parent_id'  => $parent,
                'route_name' => 'admin.login-log',
                'icon'       => null,
                'order_seq'  => 7,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Beri akses default ke EC Administrator (role 1), sama seperti menu
        // Control Center lainnya. Idempotent.
        DB::table('role_menu')->updateOrInsert(
            ['role_id' => 1, 'menu_id' => $menuId],
            [
                'can_view'   => true,
                'can_create' => false,
                'can_edit'   => false,
                'can_delete' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        $menuId = DB::table('menu')->where('slug', 'control-center.login-log')->value('id');
        if ($menuId) {
            DB::table('role_menu')->where('menu_id', $menuId)->delete();
            DB::table('menu')->where('id', $menuId)->delete();
        }
    }
};
