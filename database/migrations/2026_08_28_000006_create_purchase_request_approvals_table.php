<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat persetujuan per dokumen Purchase Request.
 *
 * 🔴 BARIS DI SINI DISALIN DARI `purchase_request_approval_steps` SAAT DOKUMEN
 * DIBUAT — bukan dibaca langsung dari tabel konfigurasi setiap kali.
 *
 * Alasannya adalah jebakan yang tidak terlihat sampai terjadi: bila alur
 * persetujuan diubah sementara ada dokumen yang sedang berjalan, dokumen itu bisa
 * kehilangan langkah yang sudah disetujui, atau melompat menjadi disetujui penuh
 * tanpa pernah ditinjau siapa pun. Dengan menyalin, mengubah pengaturan hanya
 * berdampak pada dokumen BARU, dan riwayat persetujuan tetap dapat diaudit apa
 * adanya. Pola ini sudah teruji di Overtime dan Reimbursement.
 *
 * Prinsipnya sama dengan Keputusan D9 (menyimpan jarak terhitung saat presensi
 * agar mengubah radius tidak mengubah makna riwayat).
 *
 * Keputusan D116 tetap berlaku di sini — dan itu ASIMETRIS, jangan disederhanakan:
 * MENAMBAH langkah boleh diterapkan ke dokumen yang sedang berjalan (memperketat),
 * lewat kotak centang `apply_to_open`. MENGHAPUS atau MELONGGARKAN tidak pernah
 * berlaku surut, karena di sanalah bahayanya: dokumen yang menunggu di langkah
 * yang dihapus bisa melompat jadi disetujui tanpa ditinjau siapa pun.
 *
 * ── Catatan kolom ──────────────────────────────────────────────────────────
 *
 *  - `step_name` disimpan sebagai SALINAN TEKS, bukan foreign key: nama langkah
 *    yang berlaku saat dokumen dibuat harus tetap terbaca meski langkah aslinya
 *    kelak diganti nama atau dihapus. Nama inilah yang muncul di layar sebagai
 *    "Pending Verification" pada rekap dan "Waiting Verification" pada detail —
 *    dua istilah yang mengikuti aplikasi acuan, bukan dua keadaan berbeda.
 *
 *    Nama ini juga menjadi JUDUL KOLOM TANDA TANGAN pada cetakan (Keputusan
 *    D129). Karena blok tanda tangan dirender dari baris-baris tabel ini — bukan
 *    dari konfigurasi terkini — jumlah dan judul kolomnya ikut membeku bersama
 *    dokumen. Cetak ulang dokumen lama setelah alur diubah tetap menghasilkan
 *    kertas yang sama.
 *
 *  - `chosen_by_requester` menandai baris yang kandidatnya DIPILIH PEMOHON saat
 *    submit (Keputusan D126). Ketika langkah bertanda `requester_selectable`
 *    disalin ke sini, `approver_employee_ids` diisi SATU id — pilihan pemohon —
 *    bukan seluruh daftar kandidat. Itulah mekanisme pembekuannya: mengganti
 *    kandidat di Settings tidak mengubah penyetuju dokumen yang sedang menunggu.
 *    Kolom boolean-nya ada supaya jejaknya terbaca saat audit, dan supaya layar
 *    detail dapat menerangkan kenapa penyetujunya orang itu.
 *
 *  - `acted_by` adalah siapa yang BENAR-BENAR menekan tombol. Tidak selalu sama
 *    dengan definisi di atasnya: sebuah role dapat dipegang banyak orang, dan
 *    cukup satu di antaranya yang bertindak (Keputusan D80).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_approvals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('purchase_request_id')
                  ->constrained('purchase_requests')
                  ->onDelete('cascade');

            $table->unsignedTinyInteger('order_seq');

            // ── Salinan definisi langkah saat dokumen dibuat ───────────────
            $table->string('step_name', 100);
            $table->string('approver_type', 20);
            $table->unsignedBigInteger('approver_role_id')->nullable();
            $table->json('approver_employee_ids')->nullable();
            $table->boolean('chosen_by_requester')->default(false);

            // ── Hasil ──────────────────────────────────────────────────────
            $table->string('status', 20)->default('waiting'); // waiting|approved|rejected|skipped

            $table->unsignedBigInteger('acted_by')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign('acted_by')
                  ->references('employee_id')->on('employee')
                  ->nullOnDelete();

            $table->unique(['purchase_request_id', 'order_seq'], 'pr_approval_request_order_unique');
            $table->index(['status'], 'pr_approval_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_approvals');
    }
};
