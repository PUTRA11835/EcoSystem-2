<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kepala dokumen Purchase Request.
 *
 * Satu baris = satu dokumen, dan satu dokumen berisi BEBERAPA baris permintaan
 * yang disimpan di `purchase_request_items`. Item tidak disimpan sebagai JSON
 * karena kuantitas, satuan, tanggal pakai, dan pembebanannya perlu dijumlahkan,
 * disaring, dan diekspor PER BARIS — itu pekerjaan database, bukan PHP.
 *
 * ── APA YANG MEMBEDAKANNYA DARI `reimbursement_requests` ───────────────────
 *
 * Reimbursement mengganti uang yang SUDAH keluar; Purchase Request meminta barang
 * yang BELUM dibeli. Akibatnya, berurut:
 *
 *   TIDAK ADA `total_amount`, `currency`, `supporting_url`
 *       Harga baru muncul di Purchase Order. Yang diringkas di sini KUANTITAS.
 *   ADA `qty_summary`
 *       Pengganti "total" untuk dokumen tanpa nominal, mis. "10 PC · 5 SET".
 *   ADA `cancelled_at` / `cancelled_by`, status jadi LIMA
 *       Karyawan boleh membatalkan selama belum ditinjau (Keputusan D131).
 *   ADA `converted_at` / `converted_by`
 *       Jejak "PR ini sudah jadi PO" (Keputusan D133).
 *   ADA `charged_project_id` di samping `charged_branch_id`
 *       Pembebanan ke proyek dinyalakan sejak awal (Keputusan D127).
 *
 * ── Catatan kolom ──────────────────────────────────────────────────────────
 *
 *  - `title` adalah "Request Summary": satu baris yang merangkum seluruh dokumen,
 *    ditampilkan di kolom DESCRIPTION pada rekap dan menjadi baris ringkasan di
 *    berkas Excel. Ia BUKAN hal yang sama dengan `purchase_request_items
 *    .description`, yang menjelaskan satu baris permintaan. Pada aplikasi acuan
 *    kolom RINGKASAN di daftar ternyata diisi deskripsi item PERTAMA — perilaku
 *    itu sengaja TIDAK ditiru: dokumen dengan lima baris berbeda tidak terwakili
 *    oleh baris pertamanya.
 *
 *  - `notes` adalah field "Notes" pada acuan — catatan bebas, terpisah dari
 *    `title`.
 *
 *  - `qty_summary` DIHITUNG DAN DISIMPAN, bukan dijumlahkan saat dibaca. Daftar
 *    25 baris tidak perlu 25 subquery, dan yang tercetak di dokumen persetujuan
 *    harus jumlah yang DISETUJUI — bukan jumlah yang kebetulan berlaku saat
 *    halaman dibuka. Alasannya sama persis dengan `total_amount` di Reimbursement
 *    (Keputusan D104). Satu-satunya jalur tulisnya adalah
 *    PurchaseRequestService::recalculateSummary().
 *
 *  - `cost_center_type` di sini bernilai `branch`, `project`, atau `mixed`, dan
 *    merupakan TURUNAN dari item — bukan sesuatu yang diketik manusia.
 *    `charged_branch_id` / `charged_project_id` hanya terisi bila SELURUH item
 *    berada di satu tempat, dan keduanya ada untuk PENYARINGAN. Yang dibaca
 *    manusia adalah `charged_to_label`.
 *
 *  - `charged_to_label` DIBEKUKAN saat submit (Keputusan D105 & D127): nama
 *    cabang, nama proyek, atau "Multiple cost centers". Nama cabang dapat
 *    berubah dan proyek dapat ditutup; dokumen yang menjadi dasar pengadaan harus
 *    tetap terbaca persis seperti saat disetujui.
 *
 *  - `status` punya LIMA nilai (Keputusan D131): submitted, in_review, approved,
 *    rejected, cancelled. Berbeda dari Reimbursement yang hanya empat — di sana
 *    karyawan memang tidak dapat membatalkan (D111), dan alasannya adalah sifat
 *    dokumen KEUANGAN. PR belum menimbulkan komitmen uang, jadi sifat itu tidak
 *    berlaku. Kolomnya bertipe string, jadi menambah nilai kelak berarti satu
 *    konstanta di model — BUKAN migrasi.
 *
 *  - `created_by` hanya terisi bila ADMIN membuat dokumen atas nama karyawan
 *    lewat tombol "New PR". Untuk pengajuan mandiri nilainya NULL, sehingga
 *    "diajukan sendiri" dan "dibuatkan" selalu dapat dibedakan.
 *
 *  - `estimated_total` DISIAPKAN TAPI TIDAK DIPAKAI (Keputusan D132). Nol UI,
 *    nol validasi, nol tampilan. Ini bukan pelanggaran D52: D52 melarang SETELAN
 *    yang tidak berpengaruh — setelan mati menipu pemilik sistem. Kolom kosong
 *    tidak terlihat siapa pun, dan menambahkannya kelak berarti ALTER TABLE pada
 *    tabel yang sudah berisi dokumen berjalan.
 *
 *  - `flags` (JSON) meniru `attendance_records.flags` dan `reimbursement_requests
 *    .flags`: sinyal baru dapat ditambahkan tanpa migrasi kolom (Keputusan D10).
 *
 *  - MEMAKAI softDeletes dengan alasan yang sama seperti Reimbursement (D109),
 *    meski PR bukan dokumen pembayaran: PR yang disetujui adalah dasar pengadaan,
 *    dan bila barisnya lenyap tidak ada yang dapat menjawab dokumen mana yang
 *    hilang, siapa yang menghapusnya, dan atas dasar apa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();

            // ── Identitas ──────────────────────────────────────────────────
            $table->string('request_no', 30)->unique();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('created_by')->nullable();

            // ── Isi dokumen ────────────────────────────────────────────────
            $table->date('request_date');
            $table->string('title', 200);
            $table->text('notes')->nullable();

            // ── Pembebanan biaya, dibekukan saat submit ────────────────────
            $table->string('cost_center_type', 20)->nullable(); // branch|project|mixed
            $table->unsignedBigInteger('charged_branch_id')->nullable();
            $table->unsignedBigInteger('charged_project_id')->nullable();
            $table->string('charged_to_label', 200)->nullable();

            // ── Ringkasan isi ──────────────────────────────────────────────
            $table->unsignedSmallInteger('item_count')->default(0);
            $table->string('qty_summary', 120)->nullable();

            // Disiapkan untuk Purchase Order. UI MATI (D132).
            $table->decimal('estimated_total', 20, 2)->nullable();

            // ── Status & posisi dalam alur ─────────────────────────────────
            $table->string('status', 20)->default('submitted');
            $table->unsignedTinyInteger('current_step_order')->nullable();

            $table->json('flags')->nullable();

            // ── Periode, menyamai overtime_requests & reimbursement_requests ─
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');

            $table->timestamp('completed_at')->nullable();

            // ── Pembatalan oleh pemohon (D131) ─────────────────────────────
            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();

            // ── Jejak konversi ke Purchase Order (D133) — nol UI hari ini ──
            $table->timestamp('converted_at')->nullable();
            $table->unsignedBigInteger('converted_by')->nullable();

            // ── Penghapusan yang tetap dapat diaudit ───────────────────────
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->string('delete_reason', 255)->nullable();
            $table->softDeletes();

            $table->timestamps();

            // PK tabel employee adalah employee_id, BUKAN id.
            $table->foreign('employee_id')
                  ->references('employee_id')->on('employee');

            $table->foreign('created_by')
                  ->references('employee_id')->on('employee')
                  ->nullOnDelete();

            $table->foreign('cancelled_by')
                  ->references('employee_id')->on('employee')
                  ->nullOnDelete();

            $table->foreign('converted_by')
                  ->references('employee_id')->on('employee')
                  ->nullOnDelete();

            $table->foreign('deleted_by')
                  ->references('employee_id')->on('employee')
                  ->nullOnDelete();

            $table->foreign('charged_branch_id')
                  ->references('id')->on('branches')
                  ->nullOnDelete();

            $table->foreign('charged_project_id')
                  ->references('id')->on('delivery_projects')
                  ->nullOnDelete();

            $table->index(['employee_id', 'request_date'], 'pr_employee_date_idx');
            $table->index(['status'], 'pr_status_idx');
            $table->index(['request_date'], 'pr_date_idx');
            $table->index(['period_year', 'period_month'], 'pr_period_idx');
            $table->index(['converted_at'], 'pr_converted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_requests');
    }
};
