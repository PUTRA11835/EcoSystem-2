<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = 20; // DELIVERY_SUPPORT_MANAGER

        $slugs = ['tickets.staging', 'staging.approve', 'staging.reject'];

        foreach ($slugs as $slug) {
            $menuId = DB::table('menu')->where('slug', $slug)->value('id');
            if (!$menuId) {
                continue;
            }

            DB::table('role_menu')->updateOrInsert(
                ['role_id' => $roleId, 'menu_id' => $menuId],
                [
                    'can_view'   => true,
                    'can_create' => true,
                    'can_edit'   => true,
                    'can_delete' => false,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        $roleId = 20;

        $slugs = ['tickets.staging', 'staging.approve', 'staging.reject'];

        foreach ($slugs as $slug) {
            $menuId = DB::table('menu')->where('slug', $slug)->value('id');
            if (!$menuId) {
                continue;
            }

            DB::table('role_menu')
                ->where('role_id', $roleId)
                ->where('menu_id', $menuId)
                ->delete();
        }
    }
};
