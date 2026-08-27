<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baris biaya di dalam satu dokumen reimbursement.
 *
 * Satu baris = satu nota. Inilah yang membedakan Reimbursement dari Overtime:
 * di sana satu pengajuan adalah satu klaim tunggal, di sini satu dokumen memuat
 * beberapa pengeluaran yang masing-masing punya bukti dan nominal sendiri.
 *
 * `onDelete('cascade')` disengaja: item tidak punya makna di luar dokumennya.
 * Perhatikan bahwa dokumen memakai softDeletes, jadi cascade ini HANYA berjalan
 * saat baris induknya benar-benar dihapus permanen dari database — yang tidak
 * dilakukan aplikasi.
 *
 * Catatan kolom:
 *
 *  - `cost_center_type` bernilai 'branch' untuk seluruh baris hari ini
 *    (jawaban R1, Keputusan D103). Nilai 'project' beserta kolom
 *    `delivery_project_id` SENGAJA DIBUAT meski pilihannya dinonaktifkan di UI:
 *    perusahaan ini konsultan yang menjalankan 23 proyek, dan kebutuhan
 *    membebankan biaya ke proyek wajar muncul. Dua kolom nullable jauh lebih
 *    murah daripada mengubah tabel yang sudah berisi dokumen keuangan. Pola yang
 *    sama dengan `approver_type = direct_manager` (Keputusan D81).
 *
 *  - `cost_center_label` DIBEKUKAN saat submit (Keputusan D105), berisi
 *    "EC-JOGJA – Eclectic Yogyakarta". Nama dan status aktif cabang dapat
 *    berubah; dokumen yang berujung ke pembayaran harus tetap terbaca persis
 *    seperti saat disetujui. `branch_id` tetap disimpan untuk penyaringan.
 *
 *  - `receipt_date_from` / `receipt_date_to` adalah kolom "TANGGAL NOTA" beserta
 *    "s.d" pada aplikasi acuan. Untuk nota satu hari, keduanya diisi tanggal
 *    yang sama — bukan `to` dibiarkan NULL. Dengan begitu setiap penyaringan
 *    rentang tanggal bekerja tanpa cabang khusus untuk baris satu hari.
 *
 *  - `amount` memakai decimal(20,2), menyamai `delivery_projects.revenue` dan
 *    kolom uang lain di basis kode ini. Tidak pernah FLOAT: pembulatan biner
 *    pada nilai uang menghasilkan selisih yang tidak dapat dijelaskan ke Finance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reimbursement_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reimbursement_request_id')
                  ->constrained('reimbursement_requests')
                  ->onDelete('cascade');

            $table->unsignedTinyInteger('line_no');

            $table->string('description', 200);

            // ── Pembebanan biaya ───────────────────────────────────────────
            $table->string('cost_center_type', 20)->default('branch'); // branch|project
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('delivery_project_id')->nullable();
            $table->string('cost_center_label', 200)->nullable();

            // ── Bukti ──────────────────────────────────────────────────────
            $table->string('receipt_no', 50)->nullable();
            $table->date('receipt_date_from');
            $table->date('receipt_date_to');

            // ── Nominal ────────────────────────────────────────────────────
            $table->char('currency', 3)->default('IDR');
            $table->decimal('amount', 20, 2)->default(0);

            $table->timestamps();

            $table->foreign('branch_id')
                  ->references('id')->on('branches')
                  ->nullOnDelete();

            $table->foreign('delivery_project_id')
                  ->references('id')->on('delivery_projects')
                  ->nullOnDelete();

            $table->unique(['reimbursement_request_id', 'line_no'], 'reimb_item_request_line_unique');
            $table->index(['branch_id'], 'reimb_item_branch_idx');
            $table->index(['receipt_date_from'], 'reimb_item_receipt_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reimbursement_items');
    }
};
