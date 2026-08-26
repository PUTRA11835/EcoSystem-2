<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rapikan penamaan dan letak menu Overtime.
 *
 * DUA PERUBAHAN, KEDUANYA HANYA PADA APA YANG DILIHAT PENGGUNA:
 *
 * 1. "My Overtime" -> "Overtime", dan barisnya dipindah ke TINGKAT ATAS.
 *    Alasannya sama persis dengan yang sudah diterapkan pada My Attendance
 *    (migrasi 2026_08_19_000004): sisi karyawan dan sisi HR adalah dua hal
 *    berbeda, dan letak izin sebaiknya mencerminkan apa yang dilihat pengguna.
 *    Selama baris ini bersarang di bawah "HR & General", pemilik sistem yang
 *    hanya ingin memberi hak mengajukan lembur terdorong ikut mencentang
 *    induknya — dan itulah yang dulu memunculkan dropdown terbuka kosong.
 *
 * 2. "Overtime — Review" -> "Overtime Management".
 *    Halaman itu bukan sekadar peninjauan: ia memuat rekap, kartu statistik,
 *    penyaringan, persetujuan bertingkat, dan ekspor. "Review" hanya menyebut
 *    satu di antaranya. "Management" juga sejajar dengan letak konfigurasinya
 *    di Management -> HR & General.
 *
 * YANG SENGAJA TIDAK BERUBAH:
 *  - `slug` tetap `general.my-overtime` dan `general.overtime`. Slug dirujuk
 *    middleware rute dan `$can()` di Blade; mengubahnya berarti menyunting kode
 *    tanpa manfaat bagi pengguna, yang hanya melihat NAMA menunya. Ini aturan
 *    yang sudah berlaku sejak Keputusan D66 dan D70.
 *  - `role_menu` tidak disentuh sama sekali. Grant merujuk `menu.id`, bukan
 *    posisi maupun nama, sehingga izin yang sudah diberikan tetap utuh — itu
 *    sebabnya memakai UPDATE, bukan hapus-buat-ulang (Keputusan D71).
 *
 * order_seq 6 menyamai My Attendance; dengan id yang lebih besar, barisnya
 * muncul tepat SESUDAHNYA — persis urutannya di sidebar.
 */
return new class extends Migration
{
    private const SLUG_EMPLOYEE = 'general.my-overtime';
    private const SLUG_ADMIN    = 'general.overtime';

    public function up(): void
    {
        DB::table('menu')->where('slug', self::SLUG_EMPLOYEE)->update([
            'name'       => 'Overtime',
            'parent_id'  => null,
            'order_seq'  => 6,
            'updated_at' => now(),
        ]);

        DB::table('menu')->where('slug', self::SLUG_ADMIN)->update([
            'name'       => 'Overtime Management',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $parentId = DB::table('menu')->where('slug', 'general')->value('id');

        DB::table('menu')->where('slug', self::SLUG_EMPLOYEE)->update([
            'name'       => 'My Overtime',
            'parent_id'  => $parentId,
            'order_seq'  => 30,
            'updated_at' => now(),
        ]);

        DB::table('menu')->where('slug', self::SLUG_ADMIN)->update([
            'name'       => 'Overtime — Review',
            'updated_at' => now(),
        ]);
    }
};
