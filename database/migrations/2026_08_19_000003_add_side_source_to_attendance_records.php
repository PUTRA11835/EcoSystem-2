<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sumber pencatatan TERPISAH untuk check-in dan check-out.
 *
 * Sebelumnya hanya ada satu kolom `source` untuk seluruh baris, padahal kedua
 * sisi bisa berasal dari jalur yang berbeda. Kasus nyatanya: karyawan hanya
 * mengoreksi jam masuk, tetapi karena `source` bersifat sebaris, badge
 * "Correction" ikut muncul di jam keluar yang sebenarnya tidak pernah diubah —
 * dan riwayatnya jadi menyesatkan.
 *
 * Kolom `source` lama DIPERTAHANKAN sebagai ringkasan tingkat baris (dipakai
 * rekap harian dan ekspor); dua kolom baru ini yang menjadi sumber kebenaran
 * per sisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->string('check_in_source', 30)->nullable()->after('check_in_device');
            $table->string('check_out_source', 30)->nullable()->after('check_out_device');
        });

        // Baris yang sudah ada: kedua sisi mewarisi sumber tingkat baris, tetapi
        // HANYA bila sisi itu memang terisi. Sisi yang kosong tetap null supaya
        // tidak muncul badge untuk presensi yang tidak pernah terjadi.
        DB::statement('
            UPDATE attendance_records
               SET check_in_source  = CASE WHEN check_in_at  IS NOT NULL THEN source END,
                   check_out_source = CASE WHEN check_out_at IS NOT NULL THEN source END
        ');
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['check_in_source', 'check_out_source']);
        });
    }
};
