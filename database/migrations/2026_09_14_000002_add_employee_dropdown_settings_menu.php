<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu Management → Employee → Dropdown Settings — the admin CRUD page for
 * the generic dropdown master data (see create_dropdown_configs_tables).
 *
 * This is a full page (needs a route_name for the sidebar link), so unlike
 * MenuRegistrar::register() (which is for 'function' sub-permissions and
 * always leaves route_name null) the menu row is inserted directly here.
 * Sesuai aturan baku: menu BARU lahir aktif HANYA untuk EC Administrator.
 */
return new class extends Migration
{
    private const SLUG = 'management.employee.dropdown-settings';

    public function up(): void
    {
        $parent = DB::table('menu')->where('slug', 'management.employee')->first();
        if (!$parent) {
            return;
        }

        if (!DB::table('menu')->where('slug', self::SLUG)->exists()) {
            $now    = now();
            $maxSeq = (int) DB::table('menu')->where('parent_id', $parent->id)->max('order_seq');

            DB::table('menu')->insert([
                'parent_id'  => $parent->id,
                'name'       => 'Dropdown Settings',
                'slug'       => self::SLUG,
                'type'       => 'page',
                'route_name' => 'management.employee.dropdown-settings.index',
                'icon'       => null,
                'order_seq'  => $maxSeq + 1,
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
