<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu Word Report Generator (root, tepat di bawah AI Research).
 *
 * Sama seperti migrasi AI Assistant/AI Research: barisnya ditulis manual
 * karena MenuRegistrar::register() hanya bisa membuat menu di bawah parent,
 * tapi grant-nya tetap lewat MenuRegistrar supaya aturan baku berlaku — menu
 * BARU aktif HANYA untuk EC Administrator. Role lain menyusul lewat Control
 * Center → Menu Access.
 */
return new class extends Migration
{
    private const SLUG = 'word-report-generator';

    public function up(): void
    {
        if (!DB::table('menu')->where('slug', self::SLUG)->exists()) {
            $now = now();

            DB::table('menu')->insert([
                'parent_id'  => null,
                'name'       => 'Word Report Generator',
                'slug'       => self::SLUG,
                'type'       => 'page',
                'route_name' => 'reports.generate.page',
                'icon'       => 'fa-file-word',
                'order_seq'  => 4,
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
