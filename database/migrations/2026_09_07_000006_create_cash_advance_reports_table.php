<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Header Cash Advance Report (CAR) — pertanggungjawaban atas satu Cash Advance.
 *
 * CA menjawab "berapa uang yang saya minta"; CAR menjawab "berapa yang benar-benar
 * saya belanjakan, dan berapa sisanya". Tanpa CAR, sebuah CA yang sudah disetujui
 * adalah uang keluar yang tidak pernah ditutup bukunya.
 *
 * ── advance_amount DIBEKUKAN, TIDAK DIBACA DARI RELASI ─────────────────────
 * 🔴 Ini kolom yang paling mudah dianggap mubazir — "kan tinggal ambil dari
 * cash_advances.amount". Justru itu masalahnya. Bila HR mengedit nominal CA
 * setelah CAR dibuat (dan `allow_approver_adjust_amount` memang membuka jalan
 * itu), selisih pada CAR yang SUDAH DITANDATANGANI akan berubah sendiri, dan
 * kertas yang sudah dicetak jadi berbeda dari layar. Alasan yang sama persis
 * dengan `charged_to_label` (D105) dan `total_amount` (D104).
 *
 * ── TIGA ANGKA, SATU JALUR TULIS ───────────────────────────────────────────
 *   reported_amount    = jumlah seluruh baris di cash_advance_report_items
 *   difference_amount  = advance_amount - reported_amount
 *   settlement_type    = refund | claim | exact  (turunan tanda difference)
 *
 * Ketiganya HANYA boleh ditulis CashAdvanceReportService::recalculateTotals(),
 * di dalam transaksi yang sama dengan penyimpanan itemnya. Menghitungnya saat
 * dibaca terdengar lebih aman sampai seseorang mengedit item dokumen yang sudah
 * disetujui — yang tercetak harus angka yang DISETUJUI, bukan angka yang
 * kebetulan berlaku saat halaman dibuka.
 *
 * `settlement_type` disimpan meski bisa diturunkan dari tanda `difference_amount`,
 * karena ia dipakai untuk MENYARING ("tampilkan semua CAR yang masih menunggu
 * pengembalian") — dan menyaring berdasarkan ekspresi menghalangi indeks.
 * Nilai `exact` bukan kemewahan: selisih nol adalah keadaan yang berbeda artinya
 * dari "belum dihitung", dan keduanya tidak boleh terlihat sama.
 *
 * ── SATU CAR PER CA (jawaban sementara C8) ─────────────────────────────────
 * Relasinya sudah satu-ke-banyak, jadi mengizinkan pertanggungjawaban bercicil
 * kelak TIDAK menuntut migrasi — yang berubah hanya aturan di service dan
 * tampilannya. Batas satu-CAR ditegakkan di service, bukan oleh indeks unik,
 * supaya pelonggarannya nanti tidak berarti menghapus indeks pada tabel berisi.
 *
 * `currency` disalin dari CA dan tidak boleh berbeda: tidak ada tabel kurs di
 * basis data ini, jadi CAR ber-mata-uang lain berarti selisih yang tidak punya
 * arti.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_advance_reports', function (Blueprint $table) {
            $table->id();

            // Format sejajar CA: 26/IX/CAR/00001
            $table->string('report_no', 30)->unique();

            // 🔴 WAJIB. CAR tidak punya alasan untuk ada tanpa CA.
            // restrictOnDelete: CA yang punya CAR tidak boleh dihapus keras —
            // penghapusan dokumen keuangan di modul ini memang selalu soft delete
            // beralasan (D109), jadi batasan ini hanya menjaga jalur yang tidak
            // seharusnya dipakai.
            $table->foreignId('cash_advance_id')
                  ->constrained('cash_advances')
                  ->restrictOnDelete();

            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('created_by')->nullable();

            $table->date('report_date');
            $table->string('description', 255);

            // Disalin dari CA — lihat docblock.
            $table->string('currency', 3)->default('IDR');
            $table->decimal('advance_amount', 20, 2);

            // Dihitung lalu disimpan; satu-satunya jalur tulis recalculateTotals().
            $table->decimal('reported_amount', 20, 2)->default(0);
            $table->decimal('difference_amount', 20, 2)->default(0);
            // refund = ada sisa dikembalikan · claim = kurang, ditagihkan · exact
            $table->string('settlement_type', 20)->default('exact');
            $table->unsignedSmallInteger('item_count')->default(0);

            $table->text('notes')->nullable();

            // Lima nilai, sama dengan CA.
            $table->string('status', 20)->default('submitted');
            $table->unsignedTinyInteger('current_step_order')->nullable();

            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');

            $table->json('flags')->nullable();

            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->string('delete_reason', 255)->nullable();

            $table->timestamps();

            $table->foreign('employee_id')
                  ->references('employee_id')->on('employee');

            $table->foreign('created_by')
                  ->references('employee_id')->on('employee')
                  ->nullOnDelete();

            $table->foreign('cancelled_by')
                  ->references('employee_id')->on('employee')
                  ->nullOnDelete();

            $table->foreign('deleted_by')
                  ->references('employee_id')->on('employee')
                  ->nullOnDelete();

            $table->index(['employee_id', 'report_date'], 'car_employee_date_idx');
            $table->index('status', 'car_status_idx');
            $table->index('settlement_type', 'car_settlement_type_idx');
            $table->index(['period_year', 'period_month'], 'car_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_advance_reports');
    }
};
