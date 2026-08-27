<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengajuan lembur beserta hasil akhirnya.
 *
 * Satu baris = satu pengajuan. Beberapa pengajuan pada tanggal yang sama
 * DIIZINKAN selama jamnya tidak tumpang tindih (Keputusan D85) — orang bisa
 * lembur pagi dan malam pada hari yang sama, dan aturan "satu per hari" hanya
 * memaksa mereka menggabungkan dua kejadian berbeda ke dalam satu klaim.
 *
 * TIDAK MEMAKAI softDeletes: pengajuan yang dibatalkan tetap berstatus
 * `cancelled` dan terlihat. Dokumen yang berujung ke pembayaran tidak boleh
 * hilang dari pandangan.
 *
 * Catatan kolom:
 *
 *  - `duration_minutes` DIHITUNG DAN DISIMPAN, bukan dihitung ulang saat
 *    dibaca. Bila aturan lintas tengah malam diubah kemudian, riwayat tetap
 *    dapat diaudit apa adanya (alasan yang sama dengan Keputusan D9).
 *
 *  - `day_type` diambil dari HolidayService PADA SAAT PENGAJUAN, bukan saat
 *    dibaca. Ini syarat agar penundaan perhitungan rupiah tetap aman: tabel
 *    `holidays` dapat diperbaiki atau bertambah, dan menghitung ulang jenis
 *    hari di kemudian hari akan mengubah makna riwayat lembur yang sudah
 *    disetujui.
 *
 *  - `original_start_time` / `original_end_time` / `adjusted_by` menyimpan jam
 *    yang SEMULA diklaim karyawan bila penyetuju menyesuaikannya. Angka yang
 *    diklaim tidak pernah hilang, sehingga selisih antara klaim dan yang
 *    disetujui selalu dapat ditelusuri.
 *
 *  - `hourly_rate` / `rate_breakdown` / `amount` sengaja DIBIARKAN KOSONG.
 *    Basis data ini belum punya satu pun tabel gaji, sehingga nominalnya belum
 *    dapat dihitung. Kolomnya disiapkan sejak awal supaya penyambungan ke
 *    payroll nanti tidak memerlukan migrasi ubah-kolom.
 *
 *  - `flags` (JSON) meniru `attendance_records.flags`: sinyal baru dapat
 *    ditambahkan tanpa migrasi kolom setiap kali (Keputusan D10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();

            // ── Identitas ──────────────────────────────────────────────────
            $table->string('request_no', 30)->unique();
            $table->unsignedBigInteger('employee_id');

            // ── Waktu yang diklaim ─────────────────────────────────────────
            $table->date('overtime_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('crosses_midnight')->default(false);
            $table->unsignedInteger('duration_minutes')->default(0);

            // ── Penyesuaian oleh penyetuju ─────────────────────────────────
            $table->time('original_start_time')->nullable();
            $table->time('original_end_time')->nullable();
            $table->unsignedBigInteger('adjusted_by')->nullable();

            // ── Klasifikasi hari, dibekukan saat pengajuan ─────────────────
            $table->string('day_type', 20)->default('workday'); // workday|weekend|public_holiday

            $table->text('reason');

            // ── Status & posisi dalam alur ─────────────────────────────────
            $table->string('status', 20)->default('submitted');
            $table->unsignedTinyInteger('current_step_order')->nullable();

            // ── Pembanding terhadap absensi ────────────────────────────────
            $table->unsignedBigInteger('attendance_record_id')->nullable();
            $table->unsignedInteger('attendance_overtime_minutes')->nullable();

            // ── Nominal (menunggu modul Payroll) ───────────────────────────
            $table->decimal('hourly_rate', 15, 2)->nullable();
            $table->json('rate_breakdown')->nullable();
            $table->decimal('amount', 15, 2)->nullable();

            $table->json('flags')->nullable();
            $table->text('notes')->nullable();

            // ── Periode, menyamai attendance_records ───────────────────────
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');

            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            // PK tabel employee adalah employee_id, BUKAN id.
            $table->foreign('employee_id')
                  ->references('employee_id')->on('employee');

            $table->foreign('adjusted_by')
                  ->references('employee_id')->on('employee')
                  ->nullOnDelete();

            $table->foreign('attendance_record_id')
                  ->references('id')->on('attendance_records')
                  ->nullOnDelete();

            $table->index(['employee_id', 'overtime_date'], 'overtime_employee_date_idx');
            $table->index(['status'], 'overtime_status_idx');
            $table->index(['overtime_date'], 'overtime_date_idx');
            $table->index(['period_year', 'period_month'], 'overtime_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_requests');
    }
};
