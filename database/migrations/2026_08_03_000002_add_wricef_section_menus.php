<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Slug Menu Access untuk section baru Delivery Project → WRICEF Log.
 *
 * Mengikuti konvensi granular per section (lihat
 * 2026_07_29_000001_add_delivery_section_granular_menus.php):
 *
 *   delivery-project.wricef.view    → section tampil
 *   delivery-project.wricef.edit    → boleh mengubah baris yang sudah ada
 *   delivery-project.wricef.manage  → boleh menambah & menghapus baris
 *
 * Keadaan awal grant diatur MenuRegistrar: aktif HANYA untuk EC Administrator,
 * mati untuk seluruh role lain. Pemberian ke role lain dilakukan manual lewat
 * Control Center → Menu Access.
 */
return new class extends Migration
{
    private const PARENT_SLUG = 'delivery.project';

    private const MENUS = [
        'delivery-project.wricef.view'   => 'WRICEF Log — View',
        'delivery-project.wricef.edit'   => 'WRICEF Log — Edit',
        'delivery-project.wricef.manage' => 'WRICEF Log — Create / Delete',
    ];

    public function up(): void
    {
        MenuRegistrar::register(self::PARENT_SLUG, self::MENUS, 61);
    }

    public function down(): void
    {
        MenuRegistrar::remove(array_keys(self::MENUS));
    }
};
