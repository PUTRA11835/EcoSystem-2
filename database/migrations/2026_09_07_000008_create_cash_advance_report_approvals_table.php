<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RIWAYAT persetujuan milik satu dokumen Cash Advance Report — cermin
 * `cash_advance_approvals`, dengan alasan yang persis sama.
 *
 * Seluruh docblock migrasi 000005 berlaku di sini: langkah DISALIN bukan
 * dirujuk, `step_name` dan `actor_role` ikut dibekukan, `approver_employee_ids`
 * diisi SATU id bila penyetujunya dipilih pemohon, dan status `skipped` membuat
 * cetakan dokumen batal tidak menampilkan kolom tanda tangan untuk orang yang
 * tidak akan pernah bertindak.
 *
 * ── KENAPA TABEL SENDIRI, PADAHAL TABEL LANGKAHNYA DIBAGI ──────────────────
 * 🔴 Pertanyaan yang wajar, dan jawabannya bukan konsistensi melainkan bentuk
 * kunci asing. `cash_advance_approvals.cash_advance_id` menunjuk `cash_advances`;
 * baris CAR harus menunjuk `cash_advance_reports`. Menyatukannya berarti membuang
 * kunci asing dan menggantinya dengan pasangan (`document_type`, `document_id`)
 * yang tidak dapat ditegakkan basis data — persis jenis relasi yang membiarkan
 * baris yatim lahir diam-diam. Tabel CETAKAN langkah boleh dibagi karena ia
 * tidak menunjuk dokumen apa pun; tabel RIWAYAT tidak, karena justru itu satu-
 * satunya tugasnya.
 *
 * Perbedaannya dengan CA hanya satu, dan itu ada di service bukan di skema:
 * menyetujui CAR pada langkah TERAKHIR ikut menutup buku CA induknya —
 * `settlement_status`, `reported_amount`, `outstanding_amount`, dan `settled_at`
 * pada `cash_advances` diperbarui dalam transaksi yang SAMA. Menyetujui laporan
 * lalu gagal menandai uang mukanya sebagai selesai adalah keadaan setengah jadi
 * yang tidak boleh bisa terjadi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_advance_report_approvals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cash_advance_report_id')
                  ->constrained('cash_advance_reports')
                  ->cascadeOnDelete();

            $table->unsignedTinyInteger('order_seq');

            // Disalin dari cash_advance_approval_steps (module =
            // 'cash_advance_report') saat dokumen dibuat.
            $table->string('step_name', 100);
            $table->string('actor_role', 20)->default('approver'); // verificator | approver
            $table->string('approver_type', 20)->default('role');
            $table->unsignedBigInteger('approver_role_id')->nullable();
            $table->json('approver_employee_ids')->nullable();

            $table->boolean('chosen_by_requester')->default(false);

            // pending | approved | rejected | skipped
            $table->string('status', 20)->default('pending');

            $table->unsignedBigInteger('acted_by')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->string('note', 500)->nullable();

            $table->json('flags')->nullable();
            $table->timestamps();

            $table->foreign('approver_role_id')
                  ->references('id')->on('employee_role')
                  ->nullOnDelete();

            $table->foreign('acted_by')
                  ->references('employee_id')->on('employee')
                  ->nullOnDelete();

            $table->unique(['cash_advance_report_id', 'order_seq'], 'car_appr_doc_order_unq');
            $table->index('status', 'car_appr_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_advance_report_approvals');
    }
};
