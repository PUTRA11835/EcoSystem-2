<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu AI Research (root, tepat di bawah AI Assistant).
 *
 * Sama seperti migrasi AI Assistant: barisnya ditulis manual karena
 * MenuRegistrar::register() hanya bisa membuat menu di bawah parent, tapi
 * grant-nya tetap lewat MenuRegistrar supaya aturan baku berlaku — menu BARU
 * aktif HANYA untuk EC Administrator. Role lain menyusul lewat Control Center
 * → Menu Access.
 */
return new class extends Migration
{
    private const SLUG = 'ai-research';

    public function up(): void
    {
        if (!DB::table('menu')->where('slug', self::SLUG)->exists()) {
            $now = now();

            DB::table('menu')->insert([
                'parent_id'  => null,
                'name'       => 'AI Research',
                'slug'       => self::SLUG,
                'type'       => 'page',
                'route_name' => 'ai-research',
                'icon'       => 'fa-magnifying-glass-chart',
                'order_seq'  => 3,
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
