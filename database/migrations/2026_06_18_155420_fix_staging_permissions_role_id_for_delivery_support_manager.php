<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $correctRoleId = 14; // Delivery Support Manager (actual DB id)
        $wrongRoleId   = 20; // HO Accounting Head — salah di-assign oleh migration 2026_06_17

        $slugs = ['tickets.staging', 'staging.approve', 'staging.reject'];

        foreach ($slugs as $slug) {
            $menuId = DB::table('menu')->where('slug', $slug)->value('id');
            if (!$menuId) {
                continue;
            }

            // Pastikan role_id=14 punya permission yang benar
            DB::table('role_menu')->updateOrInsert(
                ['role_id' => $correctRoleId, 'menu_id' => $menuId],
                [
                    'can_view'   => true,
                    'can_create' => true,
                    'can_edit'   => true,
                    'can_delete' => false,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            // Hapus permission yang salah di-assign ke role_id=20 (HO Accounting Head)
            DB::table('role_menu')
                ->where('role_id', $wrongRoleId)
                ->where('menu_id', $menuId)
                ->delete();
        }
    }

    public function down(): void
    {
        $correctRoleId = 14;
        $wrongRoleId   = 20;

        $slugs = ['tickets.staging', 'staging.approve', 'staging.reject'];

        foreach ($slugs as $slug) {
            $menuId = DB::table('menu')->where('slug', $slug)->value('id');
            if (!$menuId) {
                continue;
            }

            DB::table('role_menu')->updateOrInsert(
                ['role_id' => $wrongRoleId, 'menu_id' => $menuId],
                [
                    'can_view'   => true,
                    'can_create' => true,
                    'can_edit'   => true,
                    'can_delete' => false,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

            DB::table('role_menu')
                ->where('role_id', $correctRoleId)
                ->where('menu_id', $menuId)
                ->delete();
        }
    }
};
