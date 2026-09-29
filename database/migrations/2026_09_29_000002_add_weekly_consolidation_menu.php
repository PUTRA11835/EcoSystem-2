<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu Reporting → Weekly Consolidation.
 *
 * Baris ditulis manual (bukan lewat MenuRegistrar::register) karena
 * ensureMenu() tidak mengisi route_name, sama seperti precedent migrasi
 * Seasonal Theme (2026_09_25_000001).
 *
 * Beda dari kebanyakan menu baru lain: fitur ini memang harus bisa diakses
 * oleh module lead biasa (role Delivery Support User), bukan cuma Admin/HOS/
 * Helpdesk/RPMO — pembatasan "hanya modul yang dia pimpin" dilakukan di
 * WeeklyConsolidationController (data-driven lewat module_leads), BUKAN lewat
 * menu permission ini. Jadi setelah grant admin-only baku, grant diperluas
 * lewat grantToAdminAndRoles ke role-role terkait.
 */
return new class extends Migration
{
    private const SLUG = 'reporting.weekly-consolidation';

    public function up(): void
    {
        $parentId = DB::table('menu')->where('slug', 'reporting')->value('id');

        if (!$parentId) {
            return;
        }

        if (!DB::table('menu')->where('slug', self::SLUG)->exists()) {
            $now = now();

            DB::table('menu')->insert([
                'parent_id'  => $parentId,
                'name'       => 'Weekly Consolidation',
                'slug'       => self::SLUG,
                'type'       => 'page',
                'route_name' => 'reporting.weekly-consolidation',
                'icon'       => null,
                'order_seq'  => 10,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        MenuRegistrar::grantToAdminOnly([self::SLUG]);

        MenuRegistrar::grantToAdminAndRoles([self::SLUG], [
            'Delivery Support User',
            'Delivery Support Head',
            'Delivery Support Service Helpdesk',
            'Delivery RPMO Head',
        ]);
    }

    public function down(): void
    {
        MenuRegistrar::remove([self::SLUG]);
    }
};
