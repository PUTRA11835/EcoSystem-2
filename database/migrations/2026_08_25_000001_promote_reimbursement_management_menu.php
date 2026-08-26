<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pindahkan "Reimbursement Management" ke TINGKAT ATAS, keluar dari dropdown
 * "HR & General".
 *
 * ALASANNYA — PRINSIP HAK SEKECIL MUNGKIN, BUKAN KERAPIAN:
 *
 * Selama menu ini bersarang di bawah "HR & General", pemilik sistem TERPAKSA
 * memberikan slug induk `general` kepada setiap orang yang perlu meninjau
 * reimbursement — karena dropdown-nya tidak dirender tanpa induknya. Padahal
 * penyetuju reimbursement adalah orang GA dan Finance, yang tidak punya urusan
 * dengan Attendance maupun Overtime.
 *
 * Setelah dipindah, mereka cukup diberi `general.reimbursement` dan
 * `general.reimbursement.approve`. Slug `general` tidak perlu disentuh sama
 * sekali.
 *
 * CATATAN JUJUR — risiko yang dikhawatirkan tidak pernah terjadi:
 * Memberikan `general` TIDAK membuka halaman Reimbursement Settings. Halaman itu
 * dilindungi slug tersendiri (`general.settings.reimbursement`) dan letaknya di
 * Management -> HR & General, yang menuntut slug `management` pula. Nol rute di
 * seluruh aplikasi yang dilindungi `menu:general` — slug itu murni penanda
 * sidebar. Jadi perubahan ini BUKAN menambal lubang; ia menghapus keharusan
 * memberi izin yang tidak diperlukan.
 *
 * Pola dan alasannya persis sama dengan migrasi 2026_08_19_000004 (My
 * Attendance) dan 2026_08_21_000001 (Overtime).
 *
 * YANG SENGAJA TIDAK BERUBAH:
 *  - `slug` tetap `general.reimbursement`. Ia dirujuk middleware rute dan
 *    $can() di Blade; yang dilihat pengguna hanyalah NAMA dan LETAKNYA
 *    (Keputusan D66, D70).
 *  - `role_menu` tidak disentuh. Grant merujuk `menu.id`, bukan posisi, sehingga
 *    izin yang sudah diberikan tetap utuh — itu sebabnya memakai UPDATE, bukan
 *    hapus-buat-ulang (Keputusan D71).
 *  - "Overtime Management" SENGAJA dibiarkan di dalam dropdown. Modul itu sudah
 *    selesai dan teruji; memindahkannya adalah perubahan tersendiri yang harus
 *    diminta secara terpisah.
 *
 * order_seq 6 menyamai My Attendance, Overtime, dan Reimbursement; dengan id
 * yang lebih kecil, barisnya muncul tepat SEBELUM ketiganya di daftar Menu
 * Access — dan di sidebar urutannya diatur berkas Blade, bukan kolom ini.
 */
return new class extends Migration
{
    private const SLUG = 'general.reimbursement';

    public function up(): void
    {
        DB::table('menu')->where('slug', self::SLUG)->update([
            'parent_id'  => null,
            'order_seq'  => 6,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $parentId = DB::table('menu')->where('slug', 'general')->value('id');

        DB::table('menu')->where('slug', self::SLUG)->update([
            'parent_id'  => $parentId,
            'order_seq'  => 51,
            'updated_at' => now(),
        ]);
    }
};
