<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master cabang / kantor perusahaan beserta titik geofence-nya.
 *
 * Satu baris = satu kantor fisik. Dipakai modul Attendance untuk memutuskan
 * apakah sebuah presensi terjadi di dalam area kantor.
 *
 * Catatan desain:
 *  - Koordinat memakai DECIMAL, BUKAN FLOAT. Galat pembulatan biner pada FLOAT
 *    membuat perbandingan jarak tidak deterministik: nilai yang sama bisa
 *    menghasilkan "di dalam" atau "di luar" radius pada dua pemanggilan.
 *    DECIMAL(10,8) cukup untuk lintang (-90..90) dan (11,8) untuk bujur
 *    (-180..180), dengan presisi ~1 mm.
 *  - Presensi TIDAK memakai tabel penugasan karyawan->cabang. Jarak dihitung
 *    ke SELURUH cabang aktif lalu diambil yang terdekat. Alasannya: konsultan
 *    di perusahaan ini berpindah antar kantor dan antar lokasi klien, sehingga
 *    penugasan tetap justru sering salah. Pendekatan "terdekat menang" selalu
 *    benar tanpa data tambahan yang harus dipelihara.
 *  - `home_base_key` disediakan untuk memetakan cabang ke nilai
 *    employee_basic_data.home_base (Jakarta / Surabaya / Yogyakarta / Others).
 *    TIDAK dipakai untuk geofence — hanya untuk pengelompokan pada laporan.
 *  - `geofence_override` NULL berarti mengikuti kebijakan global di
 *    attendance_settings. Dengan begitu mengubah kebijakan perusahaan berlaku
 *    ke semua cabang kecuali yang sengaja dikecualikan, bukan sebaliknya.
 *  - softDeletes dipakai supaya menghapus cabang tidak membuat baris presensi
 *    lama menunjuk ke cabang yang hilang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();

            // ── Identitas ──────────────────────────────────────────────────
            $table->string('code', 50)->unique();              // ESH-JKT-6100000000
            $table->string('name', 150);                       // Eclectic Solusi Handal Jakarta Selatan
            $table->string('city', 100)->nullable();           // Jakarta Selatan
            $table->string('province', 100)->nullable();       // DKI Jakarta
            $table->text('address')->nullable();
            $table->string('phone', 50)->nullable();           // (021) 38252 777

            // ── Titik geofence ─────────────────────────────────────────────
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->unsignedSmallInteger('radius_meters')->default(100);
            $table->string('geofence_override', 10)->nullable();  // off|flag|enforce; NULL = ikut global

            // ── Penanda ────────────────────────────────────────────────────
            $table->boolean('is_head_office')->default(false);
            $table->string('home_base_key', 100)->nullable();  // pemetaan ke employee_basic_data.home_base
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active'], 'branches_active_idx');
            $table->index(['home_base_key'], 'branches_home_base_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
