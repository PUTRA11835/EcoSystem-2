<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Slug Menu Access untuk Human Capital → Onboarding (progres kelengkapan data master).
 *
 * SATU SLUG SAJA (Keputusan HC-D14, D10):
 *
 *   general.onboarding   halaman daftar + detail progres kelengkapan data karyawan
 *
 * Slug `.manage` / `.export` SENGAJA belum didaftarkan: v1 tidak punya tombol
 * tambah/hapus maupun ekspor, dan slug yang halamannya belum ada hanya
 * membingungkan pemilik sistem saat membagi izin di Control Center
 * (alasan yang sama dengan `general.purchase-request.import`, D130).
 *
 * Awalan `general.*` dan induk `general` mengikuti konvensi HR & General
 * (W6/HC-D11); grup sidebar "Human Capital" baru dibuat bila modul HC lain
 * (Rekrutmen, Kontrak, dst.) sudah ada.
 *
 * Keadaan awal grant diatur MenuRegistrar: aktif HANYA untuk EC Administrator.
 * 🔴 Migrasi TIDAK membagikan izin ke role HR (HO HR Administrator/Head/User,
 * id 31-33). Pemilik sistem membaginya lewat Control Center → Menu Access.
 * Halaman ini menampilkan progres SEMUA karyawan, jadi izinnya sebaiknya hanya
 * untuk HR; karyawan biasa melihat progres dirinya sendiri di My Profile
 * (tanpa slug baru).
 *
 * Tidak ada perubahan skema: migrasi ini hanya menulis baris ke `menu` dan
 * `role_menu`, sehingga rollback-nya bersih.
 */
return new class extends Migration
{
    private const PARENT_SLUG = 'general';

    /** slug => nama tampilan (sama dengan label sidebar) */
    private const PAGES = [
        'general.onboarding' => 'Onboarding',
    ];

    public function up(): void
    {
        // order_seq 130: di atas seluruh anak `general` yang sudah ada (maks 122).
        MenuRegistrar::register(self::PARENT_SLUG, self::PAGES, 130, 'page');
    }

    public function down(): void
    {
        MenuRegistrar::remove(array_keys(self::PAGES));
    }
};
