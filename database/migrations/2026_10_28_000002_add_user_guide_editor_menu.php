<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu Control Center → User Guide Editor.
 *
 * Baris ditulis manual karena MenuRegistrar::register() tidak mengisi
 * route_name (pola sama dengan Two-Factor Enforcement). Grant lewat
 * MenuRegistrar: menu BARU aktif HANYA untuk EC Administrator.
 */
return new class extends Migration
{
    private const SLUG = 'control-center.user-guide';

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
                'name'       => 'User Guide Editor',
                'slug'       => self::SLUG,
                'type'       => 'page',
                'route_name' => 'admin.user-guide.index',
                'icon'       => 'fa-book',
                'order_seq'  => 12,
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
