<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sakelar untuk menampilkan / menyembunyikan alat "Test location access" di
 * halaman My Attendance.
 *
 * Diminta pemilik sistem (11 Sep 2026): alat diagnosanya berguna bagi HR saat
 * menelusuri keluhan "tidak bisa check-in", tetapi terlalu teknis untuk
 * ditampilkan ke seluruh karyawan setiap hari. Keputusan tampil-atau-tidak
 * diserahkan ke Attendance Settings, bukan dipatok di kode.
 *
 * 🔴 Bawaannya TRUE — perilaku hari ini dipertahankan. Migrasi ini tidak boleh
 * mengubah apa yang dilihat siapa pun sampai HR sendiri yang mematikannya.
 *
 * Aditif: satu kolom, tidak ada data yang disentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->boolean('show_location_diagnostic')
                ->default(true)
                ->after('require_location')
                ->comment('Tampilkan tombol "Test location access" di My Attendance');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn('show_location_diagnostic');
        });
    }
};
