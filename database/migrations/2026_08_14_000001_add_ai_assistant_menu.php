<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu AI Assistant (root, sejajar Home / Calendar / Reporting).
 *
 * MenuRegistrar::register() hanya bisa membuat menu di bawah parent, jadi baris
 * menu-nya ditulis di sini — tapi grant-nya tetap lewat MenuRegistrar supaya
 * aturan baku berlaku: menu BARU aktif HANYA untuk EC Administrator, mati untuk
 * seluruh role lain. Role lain menyusul lewat Control Center → Menu Access.
 */
return new class extends Migration
{
    private const SLUG = 'ai-assistant';

    public function up(): void
    {
        if (!DB::table('menu')->where('slug', self::SLUG)->exists()) {
            $now = now();

            DB::table('menu')->insert([
                'parent_id'  => null,
                'name'       => 'AI Assistant',
                'slug'       => self::SLUG,
                'type'       => 'page',
                'route_name' => 'ai-assistant',
                'icon'       => 'fa-robot',
                'order_seq'  => 2,
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
