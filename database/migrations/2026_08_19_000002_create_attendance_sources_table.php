<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Master sumber pencatatan presensi.
 *
 * Sebelumnya sumber presensi adalah dua nilai tetap di dalam kode
 * (`ess_login` dan `fingerprint_excel`) dan hanya SATU yang boleh aktif.
 * Kenyataannya perusahaan dapat menjalankan beberapa jalur sekaligus —
 * misalnya ESS untuk staf kantor dan mesin fingerprint untuk staf lapangan —
 * dan jalur baru bisa muncul kemudian (aplikasi mobile, kartu akses, impor
 * dari sistem lain).
 *
 * Karena itu daftarnya dijadikan DATA, bukan konstanta: HR dapat menambah
 * sumber baru dan menyalakan beberapa sekaligus tanpa menunggu perubahan kode.
 *
 * Catatan kolom:
 *  - `code` dipakai sebagai nilai yang tersimpan di `attendance_records.source`,
 *    jadi tidak boleh berubah setelah dipakai. Baris bawaan dikunci lewat
 *    `is_builtin` supaya kode-nya tidak dapat diubah atau dihapus dan riwayat
 *    presensi lama tetap dapat dijelaskan.
 *  - `is_web_checkin` menandai SATU sumber yang dicatat saat karyawan menekan
 *    tombol di halaman My Attendance. Tanpa penanda ini, sistem tidak tahu
 *    label apa yang harus disimpan ketika beberapa sumber aktif bersamaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_sources', function (Blueprint $table) {
            $table->id();

            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('description', 255)->nullable();

            $table->boolean('is_active')->default(false);
            $table->boolean('is_web_checkin')->default(false);
            $table->boolean('is_builtin')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(10);

            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'attendance_sources_active_idx');
        });

        $now = now();

        DB::table('attendance_sources')->insert([
            [
                'code'           => 'ess_login',
                'name'           => 'ESS Login',
                'description'    => 'Employees check in and out from their own account.',
                'is_active'      => true,
                'is_web_checkin' => true,
                'is_builtin'     => true,
                'sort_order'     => 10,
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
            [
                'code'           => 'fingerprint_excel',
                'name'           => 'Fingerprint Excel',
                'description'    => 'HR uploads the fingerprint machine file.',
                'is_active'      => false,
                'is_web_checkin' => false,
                'is_builtin'     => true,
                'sort_order'     => 20,
                'created_at'     => $now,
                'updated_at'     => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sources');
    }
};
