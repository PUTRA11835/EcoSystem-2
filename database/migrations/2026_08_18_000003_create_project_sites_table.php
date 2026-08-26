<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Titik geofence untuk lokasi kerja sebuah delivery project (kantor klien).
 *
 * Konsultan yang sedang menjalankan proyek sering bekerja di kantor klien,
 * bukan di kantor sendiri. Tanpa tabel ini presensi mereka selalu terbaca
 * "di luar radius" dan seluruh riwayatnya penuh tanda peringatan palsu.
 *
 * Catatan desain:
 *  - Dibuat sebagai tabel TERPISAH, bukan kolom di `delivery_projects`.
 *    Alasannya: `delivery_projects` adalah tabel produksi yang dipakai modul
 *    Delivery, dan satu proyek bisa punya lebih dari satu lokasi kerja.
 *  - Karyawan mana yang berhak dievaluasi terhadap lokasi ini TIDAK disimpan
 *    di sini. Sumbernya `delivery_project_employee` yang sudah ada dan sudah
 *    berisi 100 baris beserta start_date/end_date — satu sumber kebenaran.
 *  - Radius default 150 m, lebih longgar dari kantor sendiri (100 m), karena
 *    gedung klien biasanya lebih besar dan tim tidak selalu tahu titik
 *    persisnya saat mendaftarkan lokasi.
 *  - UI pengisian lokasi proyek menyusul pada tahap lanjutan. Tabel dan mesin
 *    perhitungannya disiapkan sekarang supaya penambahan UI nanti tidak
 *    memerlukan migrasi ubah-tabel pada tabel yang sudah berisi data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_sites', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('delivery_projects_id');
            $table->string('name', 150);                       // "Kantor Klien — Menara X"
            $table->text('address')->nullable();

            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->unsignedSmallInteger('radius_meters')->default(150);

            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('delivery_projects_id')
                  ->references('id')->on('delivery_projects')
                  ->onDelete('cascade');

            $table->index(['delivery_projects_id', 'is_active'], 'project_sites_project_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_sites');
    }
};
