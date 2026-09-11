<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu "Master Ticket Settings" (group, baru) → "Document Type" (page) di
 * bawah Management. Barisnya butuh insert manual untuk grup-nya sendiri
 * karena MenuRegistrar::register() hanya bisa membuat menu di bawah parent
 * yang SUDAH ada (lihat catatan di migrasi AI Assistant/AI Research) — sekali
 * grup-nya ada, child "document-type" baru lewat register() biasa.
 *
 * order_seq 7 dipilih karena children `management` yang sudah ada memakai
 * 1-6 (lihat MenuSeeder + 2026_08_19_000006_add_module_group_menu).
 *
 * Grant admin-only sesuai aturan baku MenuRegistrar — role lain menyusul
 * lewat Control Center → Menu Access.
 */
return new class extends Migration
{
    private const GROUP_SLUG = 'management.ticket';
    private const CHILD_SLUG = 'management.ticket.document-type';

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
                'name'       => 'Master Ticket Settings',
                'slug'       => self::GROUP_SLUG,
                'type'       => 'group',
                'route_name' => null,
                'icon'       => null,
                'order_seq'  => 7,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        MenuRegistrar::grantToAdminOnly([self::GROUP_SLUG]);

        MenuRegistrar::register(self::GROUP_SLUG, [
            self::CHILD_SLUG => 'Document Type',
        ], 1, 'page');
    }

    public function down(): void
    {
        MenuRegistrar::remove([self::CHILD_SLUG, self::GROUP_SLUG]);
    }
};
