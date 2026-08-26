<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kepala dokumen pengajuan reimbursement.
 *
 * Satu baris = satu dokumen, dan satu dokumen berisi BEBERAPA baris biaya yang
 * disimpan di `reimbursement_items` (Keputusan D104). Item tidak disimpan sebagai
 * JSON karena nominal, tanggal nota, dan cabangnya perlu dijumlahkan, disaring,
 * dan diekspor PER BARIS — itu pekerjaan database, bukan pekerjaan PHP.
 *
 * 🔴 MEMAKAI softDeletes, BERBEDA dari `overtime_requests` (Keputusan D109).
 * Di Overtime tidak ada tombol hapus sama sekali; pembatalan cukup lewat status.
 * Di sini HR memang perlu menghapus dokumen — tetapi dokumen yang berujung ke
 * PEMBAYARAN tidak boleh lenyap tanpa jejak. Aplikasi acuan menghapusnya permanen
 * dan itulah yang sengaja tidak diikuti: bila barisnya hilang, tiga pertanyaan
 * yang pasti muncul saat audit tidak ada jawabannya — dokumen mana yang hilang,
 * siapa yang menghapusnya, dan atas dasar apa. Karena itu `deleted_by` dan
 * `delete_reason` ikut disimpan, dan alasannya WAJIB diisi di controller.
 * Bagi pemakai sehari-hari perilakunya tetap sama: barisnya hilang dari daftar.
 *
 * Catatan kolom:
 *
 *  - `title` adalah "Reimbursement Title" pada aplikasi acuan — satu baris yang
 *    merangkum seluruh dokumen, ditampilkan di kolom DESCRIPTION pada rekap dan
 *    menjadi baris judul di berkas Excel. Ia BUKAN hal yang sama dengan
 *    `reimbursement_items.description`, yang menjelaskan satu baris biaya. Kedua
 *    field itu tampak mirip di layar dan sempat tertukar saat perancangan.
 *
 *  - `charged_to_label` DIBEKUKAN saat submit (Keputusan D105): nama cabang, atau
 *    "Multiple branches" bila itemnya lintas cabang. Nama cabang dapat berubah
 *    atau dinonaktifkan, dan dokumen yang berujung ke pembayaran harus tetap
 *    terbaca persis seperti saat disetujui — prinsip yang sama dengan `day_type`
 *    di Overtime dan `check_in_distance_m` di Attendance (Keputusan D9).
 *    `charged_branch_id` tetap disimpan untuk PENYARINGAN, dan hanya terisi bila
 *    seluruh item berada di satu cabang.
 *
 *  - `total_amount` DIHITUNG DAN DISIMPAN, bukan `SUM()` saat dibaca. Daftar 25
 *    baris tidak perlu 25 subquery, dan yang tercetak di dokumen persetujuan
 *    harus angka yang DISETUJUI — bukan angka yang kebetulan berlaku saat halaman
 *    dibuka. Satu-satunya jalur tulisnya adalah
 *    ReimbursementService::recalculateTotals().
 *
 *  - `item_count` disimpan supaya kolom "1 items" di layar tidak menuntut query
 *    tambahan per baris.
 *
 *  - `status` hanya punya EMPAT nilai (Keputusan D112): submitted, in_review,
 *    approved, rejected. `cancelled` sengaja TIDAK ada — karyawan tidak dapat
 *    membatalkan pengajuannya (Keputusan D111, mengikuti aplikasi acuan),
 *    sehingga nilai itu tidak akan pernah tercapai, dan nilai status yang tidak
 *    mungkin muncul adalah kode mati yang menyesatkan pembacanya. Kolomnya
 *    bertipe string, jadi menambahkannya kembali kelak berarti satu konstanta di
 *    model — BUKAN migrasi.
 *
 *  - `created_by` hanya terisi bila ADMIN membuat dokumen atas nama karyawan
 *    lewat tombol "New RB". Untuk pengajuan mandiri nilainya NULL, sehingga
 *    "diajukan sendiri" dan "dibuatkan" selalu dapat dibedakan.
 *
 *  - `flags` (JSON) meniru `attendance_records.flags` dan `overtime_requests.flags`:
 *    sinyal baru dapat ditambahkan tanpa migrasi kolom setiap kali (Keputusan D10).
 *
 *  - `currency` disiapkan meski hari ini selalu IDR (jawaban R3). UI-nya terkunci;
 *    kolomnya ada supaya mata uang kedua nanti tidak menuntut migrasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reimbursement_requests', function (Blueprint $table) {
            $table->id();

            // ── Identitas ──────────────────────────────────────────────────
            $table->string('request_no', 30)->unique();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('created_by')->nullable();

            // ── Isi dokumen ────────────────────────────────────────────────
            $table->date('request_date');
            $table->string('title', 200);
            $table->string('supporting_url', 1000)->nullable();

            // ── Pembebanan biaya, dibekukan saat submit ────────────────────
            $table->unsignedBigInteger('charged_branch_id')->nullable();
            $table->string('charged_to_label', 200)->nullable();

            // ── Nominal ────────────────────────────────────────────────────
            $table->char('currency', 3)->default('IDR');
            $table->decimal('total_amount', 20, 2)->default(0);
            $table->unsignedSmallInteger('item_count')->default(0);

            // ── Status & posisi dalam alur ─────────────────────────────────
            $table->string('status', 20)->default('submitted');
            $table->unsignedTinyInteger('current_step_order')->nullable();

            $table->json('flags')->nullable();
            $table->text('notes')->nullable();

            // ── Periode, menyamai attendance_records & overtime_requests ───
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');

            $table->timestamp('completed_at')->nullable();

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

            $table->foreign('deleted_by')
                  ->references('employee_id')->on('employee')
                  ->nullOnDelete();

            $table->foreign('charged_branch_id')
                  ->references('id')->on('branches')
                  ->nullOnDelete();

            $table->index(['employee_id', 'request_date'], 'reimb_employee_date_idx');
            $table->index(['status'], 'reimb_status_idx');
            $table->index(['request_date'], 'reimb_date_idx');
            $table->index(['period_year', 'period_month'], 'reimb_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reimbursement_requests');
    }
};
