<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu "Master Delivery Settings" (group, baru) → "Project Type" & "Support
 * Type" (page) di bawah Management. Sengaja diberi slug management.delivery.*
 * — beda namespace dari slug `delivery.project` / `delivery.support` yang
 * sudah dipakai untuk menu app Delivery Project/Support itu sendiri.
 *
 * Grup-nya butuh insert manual (parent-nya sendiri belum ada saat migrasi ini
 * jalan — lihat catatan yang sama di 2026_09_07_000002_add_ticket_document_type_menu),
 * child-nya lewat MenuRegistrar::register() biasa.
 *
 * order_seq 8 dipilih karena children `management` yang sudah ada memakai
 * 1-7 (lihat MenuSeeder + 2026_08_19_000006_add_module_group_menu +
 * 2026_09_07_000002_add_ticket_document_type_menu).
 */
return new class extends Migration
{
    private const GROUP_SLUG   = 'management.delivery';
    private const PROJECT_SLUG = 'management.delivery.project';
    private const SUPPORT_SLUG = 'management.delivery.support';

    public function up(): void
    {
        if (!DB::table('menu')->where('slug', self::GROUP_SLUG)->exists()) {
            $managementId = DB::table('menu')->where('slug', 'management')->value('id');
            if (!$managementId) {
                return;
            }

            $now = now();
            DB::table('menu')->insert([
                'parent_id'  => $managementId,
                'name'       => 'Master Delivery Settings',
                'slug'       => self::GROUP_SLUG,
                'type'       => 'group',
                'route_name' => null,
                'icon'       => null,
                'order_seq'  => 8,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        MenuRegistrar::grantToAdminOnly([self::GROUP_SLUG]);

        MenuRegistrar::register(self::GROUP_SLUG, [
            self::PROJECT_SLUG => 'Project Type',
            self::SUPPORT_SLUG => 'Support Type',
        ], 1, 'page');
    }

    public function down(): void
    {
        MenuRegistrar::remove([self::PROJECT_SLUG, self::SUPPORT_SLUG, self::GROUP_SLUG]);
    }
};
