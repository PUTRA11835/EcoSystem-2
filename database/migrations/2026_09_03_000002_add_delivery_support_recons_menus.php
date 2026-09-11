<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Slug izin section "Recons" pada halaman detail Delivery Support.
 *
 * Mengikuti konvensi section granular Delivery Support yang sudah ada
 * (lihat migrasi 2026_07_29_000001 dan MenuSeeder::SUPPORT_SECTIONS):
 *
 *   delivery-support.recons.view   → tab Recons tampil
 *   delivery-support.recons.edit   → simpan perubahan draft + aksi Submit
 *   delivery-support.recons.manage → buat Recons baru + hapus draft
 *
 * Sesuai aturan baku MenuRegistrar, slug baru lahir aktif HANYA untuk
 * EC Administrator. Pemberian ke role lain (Delivery Support Head, Manager,
 * dst) dilakukan pemilik sistem lewat Control Center → Menu Access.
 */
return new class extends Migration
{
    private const SLUGS = [
        'delivery-support.recons.view'   => 'Recons — View',
        'delivery-support.recons.edit'   => 'Recons — Edit',
        'delivery-support.recons.manage' => 'Recons — Create / Delete',
    ];

    public function up(): void
    {
        MenuRegistrar::register('delivery.support', self::SLUGS, 50, 'function');
    }

    public function down(): void
    {
        MenuRegistrar::remove(array_keys(self::SLUGS));
    }
};
