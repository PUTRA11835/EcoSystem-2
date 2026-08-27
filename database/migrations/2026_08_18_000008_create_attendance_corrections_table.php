<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengajuan koreksi jam presensi beserta persetujuannya.
 *
 * Alur: karyawan mengajukan dari My Attendance -> pemegang izin
 * `general.attendance.correction.approve` meninjau -> disetujui berarti jam di
 * attendance_records diperbarui dan menit keterlambatan dihitung ulang.
 *
 * Catatan kolom:
 *  - `attendance_record_id` NULLABLE karena koreksi bisa diajukan untuk
 *    tanggal yang SAMA SEKALI belum punya catatan (karyawan lupa absen).
 *    Saat disetujui, barisnya dibuatkan.
 *  - `original_*` diisi SAAT PERSETUJUAN, bukan saat pengajuan. Nilainya
 *    diambil dari record tepat sebelum ditimpa, sehingga jejaknya akurat
 *    meskipun jamnya sempat berubah antara pengajuan dan persetujuan.
 *    Menyimpannya di baris koreksi (bukan di record) memberi riwayat
 *    berurutan bila satu tanggal dikoreksi lebih dari sekali, tanpa perlu
 *    tabel riwayat terpisah.
 *  - `approved_by_type` disediakan sejak awal meski MVP hanya mengisi 'hr'.
 *    Nilai 'supervisor' menyusul setelah hierarki atasan dibangun — dan
 *    menambah kolom pada tabel yang sudah berisi data produksi jauh lebih
 *    mahal daripada menyediakannya sekarang.
 *  - `hr_note` opsional saat menyetujui, tetapi divalidasi WAJIB saat menolak:
 *    penolakan tanpa alasan hanya memindahkan pertanyaan ke jalur lain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_corrections', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('attendance_record_id')->nullable();
            $table->unsignedBigInteger('employee_id');
            $table->date('attendance_date');

            // ── Yang diminta ───────────────────────────────────────────────
            $table->time('requested_check_in')->nullable();
            $table->time('requested_check_out')->nullable();
            $table->text('reason');

            // ── Yang tergantikan (diisi saat disetujui) ────────────────────
            $table->time('original_check_in')->nullable();
            $table->time('original_check_out')->nullable();

            // ── Persetujuan ────────────────────────────────────────────────
            $table->string('status', 15)->default('pending');   // pending|approved|rejected|cancelled
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->string('approved_by_type', 15)->nullable();  // hr|supervisor
            $table->timestamp('approved_at')->nullable();
            $table->string('hr_note', 255)->nullable();

            $table->timestamps();

            $table->foreign('employee_id')
                  ->references('employee_id')->on('employee');

            $table->foreign('attendance_record_id')
                  ->references('id')->on('attendance_records')
                  ->onDelete('cascade');

            $table->index(['employee_id', 'status'], 'corrections_employee_status_idx');
            $table->index(['status', 'attendance_date'], 'corrections_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_corrections');
    }
};
