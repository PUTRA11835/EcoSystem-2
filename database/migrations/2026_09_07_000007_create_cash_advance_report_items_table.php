<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baris realisasi belanja pada satu Cash Advance Report.
 *
 * Di sinilah letak asimetri pokok sub-modul ini: CA punya SATU deskripsi dan
 * SATU nominal (permintaannya sederhana — "saya butuh 350.000 untuk beli
 * kamera"), sedangkan CAR hampir selalu MULTI-BARIS, karena uangnya terpakai
 * untuk beberapa hal dengan beberapa nota. Menyamakan bentuk keduanya demi
 * kerapian justru memaksa salah satunya jadi canggung.
 *
 * ── cascadeOnDelete, berbeda dari relasi CAR→CA ────────────────────────────
 * Baris item tidak punya arti tanpa headernya, jadi hard delete header memang
 * harus menghapusnya. Yang TIDAK boleh: soft delete ikut menghapus item — dan
 * itu memang tidak terjadi, karena soft delete hanya mengisi `deleted_at` pada
 * header. Kedua perilaku itu dibuktikan uji asap di A1, bukan diasumsikan.
 *
 * ── NAMA FIELD DI FORM MEMAKAI KUNCI ACAK ──────────────────────────────────
 * 🔴 `items[<uuid>][amount]`, BUKAN `items[0][amount]`. Baris ditambah dan
 * dihapus lewat JavaScript; dengan indeks berurutan, menghapus baris di tengah
 * membuat sisanya bergeser dan validasi menunjuk baris yang salah. Server
 * mengurutkan ulang jadi `line_no` saat menyimpan. Pelajaran ini sudah dibayar
 * di Reimbursement dan diulang di Purchase Request.
 *
 * ── PEMBEBANAN PER BARIS ───────────────────────────────────────────────────
 * CA membebankan seluruh dokumen ke satu tempat (kalau dipakai sama sekali);
 * CAR membebankan PER BARIS, karena satu uang muka wajar terpakai di dua tempat.
 * Satu baris hanya boleh mengisi SATU tipe — `cost_center_type` menentukan kolom
 * mana yang boleh terisi, dan yang lain dipaksa NULL di server, bukan hanya di
 * layar (aturan D127).
 *
 * `cost_center_label` DIBEKUKAN saat submit: cabang bisa dinonaktifkan dan proyek
 * bisa ditutup, sementara dokumen lama harus tetap terbaca (D105).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_advance_report_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cash_advance_report_id')
                  ->constrained('cash_advance_reports')
                  ->cascadeOnDelete();

            // Nomor urut yang DILIHAT pengguna. Disusun ulang server, bukan
            // dikirim klien.
            $table->unsignedTinyInteger('line_no');

            $table->date('expense_date');
            $table->string('description', 200);

            // Nomor nota. Wajib atau tidaknya diatur setelan, bukan skema —
            // beberapa pengeluaran sah memang tidak bernota.
            $table->string('receipt_no', 60)->nullable();

            $table->decimal('amount', 20, 2);

            // Tautan bukti, bukan unggahan — pola `supporting_url` Reimbursement.
            // Host divalidasi terhadap `detail_url_allowed_hosts` di setelan.
            $table->string('receipt_url', 500)->nullable();

            // branch | project — satu baris satu tipe (lihat docblock).
            $table->string('cost_center_type', 20)->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('delivery_project_id')->nullable();
            // 🔴 DIBEKUKAN saat submit.
            $table->string('cost_center_label', 200)->nullable();

            $table->timestamps();

            $table->foreign('branch_id')
                  ->references('id')->on('branches')
                  ->nullOnDelete();

            $table->foreign('delivery_project_id')
                  ->references('id')->on('delivery_projects')
                  ->nullOnDelete();

            $table->unique(['cash_advance_report_id', 'line_no'], 'car_item_doc_line_unq');
            $table->index('expense_date', 'car_item_expense_date_idx');
            $table->index('branch_id', 'car_item_branch_idx');
            $table->index('delivery_project_id', 'car_item_project_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_advance_report_items');
    }
};
