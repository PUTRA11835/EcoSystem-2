<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Jadikan "My Attendance" menu tingkat atas, sejajar dengan HR & General.
 *
 * MASALAH YANG DISELESAIKAN:
 * Di sidebar, My Attendance memang dirender sebagai item tingkat atas — setiap
 * karyawan melakukan presensi, sementara hanya sebagian yang mengakses modul
 * HR. Tetapi di pohon Menu Access ia masih berada DI DALAM "HR & General",
 * sehingga pemilik sistem yang hanya ingin memberi hak presensi terdorong ikut
 * mencentang induknya. Akibatnya role tersebut melihat dropdown "HR & General"
 * yang terbuka kosong, karena tidak satu pun isinya ia miliki.
 *
 * Letak izin sebaiknya mencerminkan apa yang dilihat pengguna. Karena itu
 * baris menunya dipindah ke tingkat atas.
 *
 * YANG TIDAK BERUBAH:
 *  - `slug` tetap `general.my-attendance`. Slug dirujuk middleware rute dan
 *    `$can()` di Blade; mengubahnya berarti menyunting kode tanpa manfaat bagi
 *    pengguna, yang hanya melihat NAMA menunya.
 *  - `role_menu` tidak disentuh sama sekali. Grant merujuk `menu.id`, bukan
 *    posisi, sehingga izin yang sudah diberikan tetap utuh.
 *
 * order_seq 6 menyamai HR & General; dengan id yang lebih besar, barisnya
 * muncul tepat SESUDAH HR & General — persis urutannya di sidebar.
 */
return new class extends Migration
{
    private const SLUG = 'general.my-attendance';

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
            'order_seq'  => 12,
            'updated_at' => now(),
        ]);
    }
};
