<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Baris permintaan di dalam satu dokumen Purchase Request.
 *
 * Satu baris = satu barang/jasa yang diminta. Inilah yang membedakan Purchase
 * Request dari Overtime: di sana satu pengajuan adalah satu klaim tunggal, di
 * sini satu dokumen memuat beberapa permintaan dengan jumlah, satuan, jadwal, dan
 * pembebanan masing-masing.
 *
 * `onDelete('cascade')` disengaja: item tidak punya makna di luar dokumennya.
 * Perhatikan bahwa dokumen memakai softDeletes, jadi cascade ini HANYA berjalan
 * saat baris induknya benar-benar dihapus permanen dari database — yang tidak
 * dilakukan aplikasi.
 *
 * ── PEMBEBANAN: CABANG **ATAU** PROYEK (Keputusan D127) ────────────────────
 *
 * Berbeda dari `reimbursement_items`, yang menyiapkan `delivery_project_id`
 * tetapi mematikan pilihannya di UI (Keputusan D103), di sini KEDUANYA HIDUP.
 * Alasannya bukan selera: datanya terbukti ada — `delivery_projects` berisi 23
 * baris dengan 22 di antaranya belum ditutup, lengkap dengan `io_number` yang
 * menyerupai kode cost center pada aplikasi acuan.
 *
 * ATURAN YANG WAJIB DITEGAKKAN DI SERVER, karena skema tidak dapat
 * menegakkannya sendiri:
 *
 *   1. Satu BARIS hanya boleh satu tipe. `cost_center_type` menentukan kolom mana
 *      yang boleh terisi; kolom yang lain DIPAKSA NULL sebelum disimpan. Tanpa
 *      itu, satu baris bisa membebani cabang dan proyek sekaligus, dan tidak ada
 *      yang tahu mana yang berlaku saat pengadaan berjalan.
 *   2. Baris BERBEDA dalam satu dokumen BOLEH berbeda tipe. Header lalu diberi
 *      `cost_center_type = 'mixed'` dan label "Multiple cost centers".
 *   3. Hanya proyek `is_closed = 0` yang boleh dipilih. Proyek yang ditutup
 *      SETELAH dokumen dibuat tidak memengaruhi apa pun — labelnya sudah dibekukan.
 *
 * Kenapa tidak dipakai CHECK constraint: MariaDB 10.4 mendukungnya, tetapi
 * aturan ini perlu menghasilkan PESAN yang dapat dibaca pemohon ("pilih cabang
 * atau proyek, jangan keduanya"), dan pesan itu lahir di lapisan validasi. Dua
 * tempat penegakan yang bisa berbeda pendapat lebih berbahaya daripada satu.
 *
 * ── Catatan kolom ──────────────────────────────────────────────────────────
 *
 *  - `cost_center_label` DIBEKUKAN saat submit, berbentuk:
 *        branch   ->  "EC-JOGJA – Eclectic Yogyakarta"      ({code} – {name})
 *        project  ->  "7600000084 – Implementasi SAP PM"    ({io_number} – {name})
 *    `io_number` dipakai karena itulah padanan terdekat kode "ESH-SBY-
 *    6100000001" pada aplikasi acuan, dan karena nama proyek terlalu panjang
 *    untuk berdiri sendiri di kolom cetakan.
 *
 *  - `qty` memakai decimal(12,2), BUKAN integer: 0,5 LOT dan 1,5 SET adalah
 *    permintaan yang wajar. Tidak pernah FLOAT — pembulatan biner menghasilkan
 *    selisih yang tidak dapat dijelaskan.
 *
 *  - `unit` divalidasi terhadap `allowed_units` di purchase_request_settings
 *    (Keputusan D128) dan dirender sebagai dropdown, bukan teks bebas. Satuan
 *    yang diketik bebas membuat `qty_summary` tidak dapat dijumlahkan per satuan.
 *
 *  - `period_from` / `period_to` adalah kolom "PERIOD" beserta "s.d" pada acuan,
 *    dan `use_date` adalah kolom "USE DATE" — kapan barang dibutuhkan. Ketiganya
 *    NULLABLE karena wajib-tidaknya diatur dari Settings (`require_period`,
 *    `require_use_date`), bukan dipatok di skema. Aturan yang dipatok di skema
 *    tidak dapat dilonggarkan tanpa migrasi, dan itu melanggar D52.
 *
 *  - `estimated_unit_price` / `estimated_amount` DISIAPKAN TAPI TIDAK DIPAKAI
 *    (Keputusan D132) — menunggu Purchase Order. Nol UI hari ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_request_id')
                  ->constrained('purchase_requests')
                  ->onDelete('cascade');

            $table->unsignedTinyInteger('line_no');

            $table->string('description', 200);

            // ── Jumlah & satuan ────────────────────────────────────────────
            $table->decimal('qty', 12, 2)->default(0);
            $table->string('unit', 20);

            // ── Jadwal ─────────────────────────────────────────────────────
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->date('use_date')->nullable();

            // ── Pembebanan biaya: cabang ATAU proyek ───────────────────────
            $table->string('cost_center_type', 20)->default('branch'); // branch|project
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->unsignedBigInteger('delivery_project_id')->nullable();
            $table->string('cost_center_label', 200)->nullable();

            // Disiapkan untuk Purchase Order. UI MATI (D132).
            $table->decimal('estimated_unit_price', 20, 2)->nullable();
            $table->decimal('estimated_amount', 20, 2)->nullable();

            $table->timestamps();

            $table->foreign('branch_id')
                  ->references('id')->on('branches')
                  ->nullOnDelete();

            $table->foreign('delivery_project_id')
                  ->references('id')->on('delivery_projects')
                  ->nullOnDelete();

            $table->unique(['purchase_request_id', 'line_no'], 'pr_item_request_line_unique');
            $table->index(['branch_id'], 'pr_item_branch_idx');
            $table->index(['delivery_project_id'], 'pr_item_project_idx');
            $table->index(['use_date'], 'pr_item_use_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_items');
    }
};
