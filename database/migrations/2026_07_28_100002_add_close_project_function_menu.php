<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Function menu "Close / Reopen Project" di bawah Delivery → Project, agar izin
 * meng-close/reopen bisa diatur per-role di Menu Access. Grant awal mengikuti
 * role yang sudah punya izin "Delete Project" (aksi setingkat sensitif).
 * ID role tidak di-hardcode (lihat divergensi ID role migrate:fresh).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now    = now();
        $parent = DB::table('menu')->where('slug', 'delivery.project')->first();
        if (!$parent) {
            return;
        }

        $slug = 'delivery-project.close-project';
        $existing = DB::table('menu')->where('slug', $slug)->first();
        $menuId = $existing->id ?? DB::table('menu')->insertGetId([
            'parent_id'  => $parent->id,
            'name'       => 'Close / Reopen Project',
            'slug'       => $slug,
            'type'       => 'function',
            'route_name' => null,
            'icon'       => null,
            'order_seq'  => 12,
            'is_active'  => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Acuan grant: role yang boleh Delete Project (fallback: yang bisa lihat menu Project).
        $refMenu = DB::table('menu')->where('slug', 'delivery-project.delete-project')->first()
                ?? DB::table('menu')->where('slug', 'delivery.project')->first();

        $roleIds = $refMenu
            ? DB::table('role_menu')->where('menu_id', $refMenu->id)->where('can_view', true)->pluck('role_id')
            : collect();

        foreach ($roleIds as $roleId) {
            DB::table('role_menu')->updateOrInsert(
                ['role_id' => $roleId, 'menu_id' => $menuId],
                ['can_view' => true, 'can_create' => false, 'can_edit' => false, 'can_delete' => false, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        $menu = DB::table('menu')->where('slug', 'delivery-project.close-project')->first();
        if (!$menu) {
            return;
        }
        DB::table('role_menu')->where('menu_id', $menu->id)->delete();
        DB::table('menu')->where('id', $menu->id)->delete();
    }
};
