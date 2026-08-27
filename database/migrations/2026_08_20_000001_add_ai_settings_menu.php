<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu Control Center → AI Settings.
 *
 * Barisnya ditulis manual (bukan lewat MenuRegistrar::register) karena
 * ensureMenu() tidak mengisi route_name, sementara item sidebar membutuhkannya.
 * Grant-nya tetap lewat MenuRegistrar supaya aturan baku berlaku: menu BARU
 * aktif HANYA untuk EC Administrator.
 */
return new class extends Migration
{
    private const SLUG = 'control-center.ai-settings';

    public function up(): void
    {
        $parentId = DB::table('menu')->where('slug', 'control-center')->value('id');

        if (!$parentId) {
            return;
        }

        if (!DB::table('menu')->where('slug', self::SLUG)->exists()) {
            $now = now();

            DB::table('menu')->insert([
                'parent_id'  => $parentId,
                'name'       => 'AI Settings',
                'slug'       => self::SLUG,
                'type'       => 'page',
                'route_name' => 'admin.ai-settings',
                'icon'       => 'fa-microchip',
                'order_seq'  => 8,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        MenuRegistrar::grantToAdminOnly([self::SLUG]);
    }

    public function down(): void
    {
        MenuRegistrar::remove([self::SLUG]);
    }
};
