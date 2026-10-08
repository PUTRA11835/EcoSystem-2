<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saklar INDUK pengaruh absensi pada Payroll (UAT / soft go-live): selama mati, potongan alpa dan denda terlambat tidak
 * pernah dihitung — apa pun isi dua toggle rincinya. Opsional: tanggal mulai berlaku, sehingga absensi baru
 * memengaruhi periode yang DIMULAI pada/sesudah tanggal itu (periode sebelumnya tidak berubah saat dihitung ulang).
 *
 * Default MATI (absensi belum pasti dipakai). Hanya menambah kolom; down() menghapusnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->boolean('attendance_enabled')->default(false);
            $table->date('attendance_effective_from')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropColumn(['attendance_enabled', 'attendance_effective_from']);
        });
    }
};
