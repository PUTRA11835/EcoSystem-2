<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Control Center → Security Center.
 *
 * Baris ini sudah lama ada di database/seeders/MenuSeeder.php (parent
 * control-center, route admin.security-center, grant admin-only) dan
 * kodenya (SecurityCenterController + view + sidebar link) sudah lengkap
 * dan aktif — tapi MenuSeeder tidak pernah benar-benar dijalankan ulang
 * setelah baris ini ditambahkan ke file-nya, jadi baris menu-nya tidak
 * pernah masuk ke database manapun yang tidak di-reseed dari nol. Migration
 * ini menutup celah itu tanpa menjalankan MenuSeeder penuh (yang berisiko
 * menimpa role_menu milik menu lain yang sudah diubah manual lewat Control
 * Center → Menu Access).
 */
return new class extends Migration
{
    private const SLUG = 'control-center.security';

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
                'name'       => 'Security Center',
                'slug'       => self::SLUG,
                'type'       => 'page',
                'route_name' => 'admin.security-center',
                'icon'       => 'fa-shield-alt',
                'order_seq'  => 9,
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
