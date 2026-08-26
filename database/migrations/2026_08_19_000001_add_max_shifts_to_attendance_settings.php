<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Batas jumlah shift aktif yang boleh dimiliki satu karyawan.
 *
 * Aturan awal modul ini adalah "satu karyawan tepat satu shift aktif". Tim HR
 * kemudian menyatakan sebagian karyawan memang menjalani lebih dari satu pola
 * jam kerja, sehingga batasnya dijadikan KONFIGURASI, bukan aturan mati di
 * dalam kode — kebijakan semacam ini berubah lebih sering daripada kodenya.
 *
 * Nilai 1 mempertahankan perilaku lama. Menaikkannya mengizinkan penugasan
 * ganda; saat presensi, shift yang dipakai adalah yang jam masuknya PALING
 * DEKAT dengan waktu punch (lihat AttendanceService::activeShift).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('max_shifts_per_employee')
                  ->default(1)
                  ->after('attendance_source');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $table->dropColumn('max_shifts_per_employee');
        });
    }
};
