<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penugasan shift ke karyawan.
 *
 * Karyawan TANPA baris di sini memakai shift yang bertanda `is_default`.
 * Artinya 207 karyawan tidak perlu diberi baris satu per satu agar modul
 * berjalan — penugasan eksplisit hanya untuk yang jam kerjanya berbeda.
 *
 * Mengikuti konvensi periode yang sudah berlaku di tabel employee_* lain:
 * `end_date IS NULL` berarti penugasan masih aktif. Mencabut penugasan
 * mengisi end_date, tidak menghapus baris, supaya presensi lama tetap dapat
 * dijelaskan dengan shift yang berlaku saat itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_shifts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('employee_id');
            $table->foreignId('shift_id')->constrained('shifts')->onDelete('cascade');

            $table->date('start_date');
            $table->date('end_date')->nullable();               // NULL = aktif
            $table->unsignedBigInteger('assigned_by')->nullable();

            $table->timestamps();

            $table->foreign('employee_id')
                  ->references('employee_id')->on('employee')
                  ->onDelete('cascade');

            $table->index(['employee_id', 'end_date'], 'employee_shifts_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_shifts');
    }
};
