<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu Control Center → Two-Factor Enforcement.
 *
 * Baris ditulis manual (bukan lewat MenuRegistrar::register) karena
 * ensureMenu() tidak mengisi route_name, sama seperti precedent migrasi
 * AI Settings (2026_08_20_000001) & Seasonal Theme (2026_09_25_000001).
 * Grant tetap lewat MenuRegistrar supaya aturan baku berlaku: menu BARU
 * aktif HANYA untuk EC Administrator.
 */
return new class extends Migration
{
    private const SLUG = 'control-center.two-factor-enforcement';

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
                'name'       => 'Two-Factor Enforcement',
                'slug'       => self::SLUG,
                'type'       => 'page',
                'route_name' => 'admin.two-factor-enforcement',
                'icon'       => 'fa-shield-halved',
                'order_seq'  => 10,
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
