<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Section "Report Templates" di halaman detail Customer (master.customer),
 * pola sama persis dengan section lain (Attachment, Credential, dst — lihat
 * 2026_07_02_193812_restructure_profile_section_menus.php): slug
 * `customer.section.report_templates.{view|update}`, digate lewat
 * middleware `customer.section:report_templates,...` (CheckCustomerSectionAccess).
 *
 * SENGAJA lebih ketat dari akses edit Customer pada umumnya (bukan otomatis
 * ikut siapa pun yang boleh edit master.customer) — admin bisa membuka akses
 * ke role lain lewat Control Center → Menu Access kalau diperlukan.
 */
return new class extends Migration
{
    private const KEY = 'report_templates';
    private const NAME = 'Report Templates';

    public function up(): void
    {
        $parentId = DB::table('menu')->where('slug', 'master.customer')->value('id');
        if (!$parentId) {
            return;
        }

        $groupSlug = 'customer.section.' . self::KEY;
        if (DB::table('menu')->where('slug', $groupSlug)->exists()) {
            return;
        }

        $now = now();

        $groupId = DB::table('menu')->insertGetId([
            'parent_id'  => $parentId,
            'name'       => self::NAME,
            'slug'       => $groupSlug,
            'type'       => 'group',
            'route_name' => null,
            'icon'       => null,
            'order_seq'  => 18,
            'is_active'  => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('menu')->insert([
            [
                'parent_id'  => $groupId,
                'name'       => 'View ' . self::NAME,
                'slug'       => $groupSlug . '.view',
                'type'       => 'function',
                'route_name' => null,
                'icon'       => null,
                'order_seq'  => 1,
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'parent_id'  => $groupId,
                'name'       => 'Update ' . self::NAME,
                'slug'       => $groupSlug . '.update',
                'type'       => 'function',
                'route_name' => null,
                'icon'       => null,
                'order_seq'  => 2,
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        MenuRegistrar::grantToAdminOnly([$groupSlug . '.view', $groupSlug . '.update']);
    }

    public function down(): void
    {
        $groupSlug = 'customer.section.' . self::KEY;

        MenuRegistrar::remove([$groupSlug . '.view', $groupSlug . '.update']);
        DB::table('menu')->where('slug', $groupSlug)->delete();
    }
};
