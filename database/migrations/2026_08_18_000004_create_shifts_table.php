<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pola jam kerja (shift) beserta toleransi keterlambatannya.
 *
 * Tanpa tabel ini kata "terlambat" tidak terdefinisi: sistem tidak punya
 * pembanding untuk jam check-in.
 *
 * Catatan desain:
 *  - `work_days` disimpan sebagai string "1,2,3,4,5" (ISO-8601, 1 = Senin)
 *    alih-alih tabel pivot tersendiri. Nilainya hanya 7 kemungkinan, tidak
 *    pernah di-join, dan tidak pernah difilter di SQL — tabel terpisah hanya
 *    akan menambah join tanpa manfaat.
 *  - `crosses_midnight` disiapkan sejak awal meski shift malam belum tentu
 *    dipakai. Presedennya sudah ada di Timesheet::boot() yang menangani durasi
 *    lintas tengah malam; menyiapkan kolomnya sekarang jauh lebih murah
 *    daripada mengubah tabel berisi data nanti.
 *  - `is_default` menandai shift yang dipakai karyawan tanpa penugasan shift.
 *    Hanya boleh satu; ditegakkan di service, bukan di constraint, karena
 *    MariaDB tidak mendukung partial unique index.
 *
 * Baris "Regular Office" 08:00-17:00 di-seed di dalam migrasi ini, bukan di
 * seeder terpisah, karena aplikasi mengharapkan selalu ada shift default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();

            $table->string('name', 100);                        // "Regular Office"
            $table->time('check_in_time')->default('08:00:00');
            $table->time('check_out_time')->default('17:00:00');
            $table->unsignedSmallInteger('late_tolerance_minutes')->default(0);
            $table->unsignedSmallInteger('break_minutes')->default(60);
            $table->string('work_days', 20)->default('1,2,3,4,5');
            $table->boolean('crosses_midnight')->default(false);

            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['is_active', 'is_default'], 'shifts_active_default_idx');
        });

        \Illuminate\Support\Facades\DB::table('shifts')->insert([
            'name'                   => 'Regular Office',
            'check_in_time'          => '08:00:00',
            'check_out_time'         => '17:00:00',
            'late_tolerance_minutes' => 0,
            'break_minutes'          => 60,
            'work_days'              => '1,2,3,4,5',
            'crosses_midnight'       => false,
            'is_default'             => true,
            'is_active'              => true,
            'notes'                  => 'Jam kerja standar perusahaan. Dibuat otomatis saat modul Attendance dipasang.',
            'created_at'             => now(),
            'updated_at'             => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
