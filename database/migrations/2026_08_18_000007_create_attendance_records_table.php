<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan presensi harian.
 *
 * SATU BARIS = SATU KARYAWAN PADA SATU TANGGAL. Check-out MEMPERBARUI baris
 * yang sama, tidak membuat baris baru. Alasannya: konsumen utama data ini
 * adalah rekap harian dan rekap bulanan (matriks karyawan x tanggal 1-31),
 * dan model per-tanggal membuat matriks itu dapat dirender dengan satu query
 * beragregasi ringan alih-alih mem-pivot ribuan event.
 *
 * Catatan kolom:
 *  - Kolom check_in_* dan check_out_* sengaja BERPASANGAN dan diulang, bukan
 *    dinormalisasi ke tabel punch terpisah. Presensi hanya punya dua momen
 *    per hari; tabel terpisah akan menambah join pada setiap pembacaan rekap
 *    demi fleksibilitas yang tidak dipakai.
 *  - `check_in_distance_m` menyimpan jarak TERHITUNG SAAT PUNCH, bukan
 *    dihitung ulang saat dibaca. Bila radius sebuah cabang diubah kemudian,
 *    riwayat lama tetap dapat diaudit apa adanya. Tanpa ini, mengubah radius
 *    akan mengubah makna seluruh catatan yang sudah lewat.
 *  - `check_in_gps_status` memakai kosakata yang sama persis dengan sistem
 *    referensi (gps_ok / gps_timeout / gps_permission_denied) supaya hasil
 *    uji terima dapat dibandingkan langsung tanpa penerjemahan.
 *  - `check_in_match_type` memuat SEKALIGUS jenis lokasi dan vonisnya:
 *    office_in / office_out / project_in / project_out / none. Vonis sengaja
 *    tidak disimpan di kolom terpisah maupun di `flags`, karena check-in dan
 *    check-out punya vonis masing-masing dan flags tidak bersisi. Nilai
 *    gabungan ini juga persis yang dibutuhkan filter STATUS di rekap harian,
 *    dan dapat dicari dengan LIKE 'office%'.
 *  - `flags` (JSON) meniru pola PeriodAuditLog.metadata: sinyal anomali baru
 *    bisa ditambahkan tanpa migrasi kolom setiap kali. MariaDB 10.4 memetakan
 *    JSON ke LONGTEXT + CHECK; cast 'array' di model bekerja normal, tetapi
 *    jangan mengandalkan indeks di dalam JSON.
 *  - `shift_id` menyimpan shift yang BERLAKU SAAT ITU, bukan shift karyawan
 *    saat ini, supaya mengubah shift tidak mengubah riwayat keterlambatan.
 *  - UNIQUE (employee_id, attendance_date) adalah penegak aturan satu baris
 *    per hari. Tanpanya, klik ganda pada tombol check-in menghasilkan baris
 *    kembar dan seluruh rekap salah hitung.
 *
 * TIDAK memakai softDeletes: catatan presensi tidak boleh dihapus, hanya
 * dikoreksi lewat alur attendance_corrections yang meninggalkan jejak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_records', function (Blueprint $table) {
            $table->id();

            // ── Identitas ──────────────────────────────────────────────────
            $table->unsignedBigInteger('employee_id');
            $table->date('attendance_date');

            // ── Punch masuk ────────────────────────────────────────────────
            $table->timestamp('check_in_at')->nullable();
            $table->decimal('check_in_latitude', 10, 8)->nullable();
            $table->decimal('check_in_longitude', 11, 8)->nullable();
            $table->decimal('check_in_accuracy_m', 8, 2)->nullable();
            $table->string('check_in_connection', 20)->nullable();      // wifi|cellular|4g|3g
            $table->string('check_in_gps_status', 30)->nullable();      // gps_ok|gps_timeout|...
            $table->string('check_in_match_type', 15)->nullable();      // office_in|office_out|project_in|project_out|none
            $table->unsignedBigInteger('check_in_branch_id')->nullable();
            $table->unsignedBigInteger('check_in_project_site_id')->nullable();
            $table->decimal('check_in_distance_m', 10, 2)->nullable();
            $table->string('check_in_ip', 45)->nullable();
            $table->string('check_in_device', 150)->nullable();

            // ── Punch keluar ───────────────────────────────────────────────
            $table->timestamp('check_out_at')->nullable();
            $table->decimal('check_out_latitude', 10, 8)->nullable();
            $table->decimal('check_out_longitude', 11, 8)->nullable();
            $table->decimal('check_out_accuracy_m', 8, 2)->nullable();
            $table->string('check_out_connection', 20)->nullable();
            $table->string('check_out_gps_status', 30)->nullable();
            $table->string('check_out_match_type', 15)->nullable();
            $table->unsignedBigInteger('check_out_branch_id')->nullable();
            $table->unsignedBigInteger('check_out_project_site_id')->nullable();
            $table->decimal('check_out_distance_m', 10, 2)->nullable();
            $table->string('check_out_ip', 45)->nullable();
            $table->string('check_out_device', 150)->nullable();

            // ── Turunan ────────────────────────────────────────────────────
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->unsignedSmallInteger('late_minutes')->default(0);
            $table->unsignedSmallInteger('early_leave_minutes')->default(0);
            $table->unsignedSmallInteger('work_minutes')->default(0);
            $table->unsignedSmallInteger('overtime_minutes')->default(0);

            // ── Status ─────────────────────────────────────────────────────
            // present|late|incomplete|holiday|weekend.
            // Nilai absent/sick/leave BELUM dipakai: tanpa modul Cuti sistem
            // tidak dapat membedakan alpa dari cuti, dan menandainya keliru
            // menimbulkan sengketa ketenagakerjaan.
            $table->string('day_status', 20)->default('present');
            $table->string('source', 20)->default('ess_login');         // ess_login|fingerprint_excel|manual_hr|correction
            $table->json('flags')->nullable();
            $table->string('notes', 255)->nullable();

            // ── Denormalisasi untuk rekap ──────────────────────────────────
            $table->smallInteger('period_year');
            $table->tinyInteger('period_month');

            $table->timestamps();

            $table->foreign('employee_id')
                  ->references('employee_id')->on('employee');

            $table->unique(['employee_id', 'attendance_date'], 'attendance_employee_date_unique');
            $table->index(['attendance_date'], 'attendance_date_idx');
            $table->index(['period_year', 'period_month'], 'attendance_period_idx');
            $table->index(['day_status'], 'attendance_day_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_records');
    }
};
