<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Konfigurasi global modul Attendance. Tabel satu baris.
 *
 * Disimpan sebagai tabel, bukan berkas config/, karena nilainya diubah HR
 * lewat UI saat aplikasi berjalan. Berkas config memerlukan deploy ulang dan
 * tidak meninggalkan jejak siapa yang mengubah.
 *
 * Tentang `geofence_mode` — ini keputusan kebijakan, bukan keputusan teknis:
 *   off      : lokasi tidak diperiksa sama sekali
 *   flag     : presensi di luar radius TETAP DICATAT lalu ditandai (default)
 *   enforce  : presensi di luar radius ditolak
 *
 * Default sengaja `flag`. Aplikasi ini berbasis web, dan browser tidak punya
 * cara mendeteksi lokasi palsu — mode enforce karena itu memberi rasa aman
 * yang keliru sambil menghukum karyawan jujur yang GPS-nya meleset di dalam
 * gedung (akurasi indoor lazimnya 20-100 m). Mode enforce tetap disediakan
 * karena sebagian perusahaan mensyaratkannya, dengan peringatan di UI.
 *
 * Baris default dibuat di dalam migrasi ini karena aplikasi mengharapkan
 * barisnya selalu ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->id();

            // ── Geofence ───────────────────────────────────────────────────
            $table->string('geofence_mode', 10)->default('flag');       // off|flag|enforce
            $table->unsignedSmallInteger('min_accuracy_meters')->default(100);
            $table->boolean('require_location')->default(true);         // wajib minta izin GPS di UI

            // ── Sumber data ────────────────────────────────────────────────
            $table->string('attendance_source', 20)->default('ess_login'); // ess_login|fingerprint_excel

            // ── Jam kerja cadangan bila tidak ada shift sama sekali ────────
            $table->time('default_check_in')->default('08:00:00');
            $table->time('default_check_out')->default('17:00:00');
            $table->unsignedSmallInteger('default_late_tolerance_minutes')->default(0);

            // ── Koreksi ────────────────────────────────────────────────────
            $table->boolean('allow_self_correction')->default(true);
            $table->unsignedSmallInteger('correction_max_days')->default(30);

            // ── Pemeliharaan ───────────────────────────────────────────────
            $table->unsignedTinyInteger('auto_close_hours')->default(12);

            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        DB::table('attendance_settings')->insert([
            'geofence_mode'                  => 'flag',
            'min_accuracy_meters'            => 100,
            'require_location'               => true,
            'attendance_source'              => 'ess_login',
            'default_check_in'               => '08:00:00',
            'default_check_out'              => '17:00:00',
            'default_late_tolerance_minutes' => 0,
            'allow_self_correction'          => true,
            'correction_max_days'            => 30,
            'auto_close_hours'               => 12,
            'created_at'                     => now(),
            'updated_at'                     => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_settings');
    }
};
