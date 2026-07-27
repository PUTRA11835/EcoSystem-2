<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Wiring menu untuk halaman "Collection Outlook (Support)" (Reporting):
 *  - Page menu  : reporting.collection-outlook-support
 *  - Function   : reporting.collection-outlook-support.edit (izin ubah status TOP)
 *
 * Akses awal mengikuti role yang SUDAH punya akses ke "Collection Outlook"
 * (project), supaya set role yang sama langsung bisa melihat versi Support.
 * ID role tidak di-hardcode (lihat catatan divergensi ID role migrate:fresh).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $reportingParent = DB::table('menu')->where('slug', 'reporting')->first();
        if (!$reportingParent) {
            return; // menu induk Reporting belum ada
        }

        // Role yang saat ini boleh melihat Collection Outlook (project) → jadi acuan grant.
        $sourceMenu = DB::table('menu')->where('slug', 'reporting.collection-outlook')->first();
        $roleIds    = $sourceMenu
            ? DB::table('role_menu')->where('menu_id', $sourceMenu->id)->where('can_view', true)->pluck('role_id')
            : collect();

        // ── Page menu ────────────────────────────────────────────────
        $pageSlug = 'reporting.collection-outlook-support';
        $page     = DB::table('menu')->where('slug', $pageSlug)->first();
        $pageId   = $page->id ?? DB::table('menu')->insertGetId([
            'parent_id'  => $reportingParent->id,
            'name'       => 'Collection Outlook (Support)',
            'slug'       => $pageSlug,
            'type'       => 'page',
            'route_name' => 'reporting.collection-outlook-support',
            'icon'       => null,
            'order_seq'  => 4,
            'is_active'  => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ── Function menu (edit payment status) ──────────────────────
        $fnSlug = 'reporting.collection-outlook-support.edit';
        $fn     = DB::table('menu')->where('slug', $fnSlug)->first();
        $fnId   = $fn->id ?? DB::table('menu')->insertGetId([
            'parent_id'  => $pageId,
            'name'       => 'Edit Payment Status',
            'slug'       => $fnSlug,
            'type'       => 'function',
            'route_name' => null,
            'icon'       => null,
            'order_seq'  => 1,
            'is_active'  => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ($roleIds as $roleId) {
            foreach ([$pageId, $fnId] as $menuId) {
                DB::table('role_menu')->updateOrInsert(
                    ['role_id' => $roleId, 'menu_id' => $menuId],
                    ['can_view' => true, 'can_create' => false, 'can_edit' => false, 'can_delete' => false, 'created_at' => $now, 'updated_at' => $now]
                );
            }
        }
    }

    public function down(): void
    {
        foreach (['reporting.collection-outlook-support.edit', 'reporting.collection-outlook-support'] as $slug) {
            $menu = DB::table('menu')->where('slug', $slug)->first();
            if (!$menu) {
                continue;
            }
            DB::table('role_menu')->where('menu_id', $menu->id)->delete();
            DB::table('menu')->where('id', $menu->id)->delete();
        }
    }
};
