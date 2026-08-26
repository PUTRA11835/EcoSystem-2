<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat persetujuan per pengajuan lembur.
 *
 * 🔴 BARIS DI SINI DISALIN DARI `overtime_approval_steps` SAAT PENGAJUAN
 * DIBUAT — bukan dibaca langsung dari tabel konfigurasi setiap kali.
 *
 * Alasannya adalah jebakan yang tidak terlihat sampai terjadi: bila alur
 * persetujuan diubah sementara ada pengajuan yang sedang berjalan, pengajuan
 * itu bisa kehilangan langkah yang sudah disetujui, atau melompat menjadi
 * disetujui penuh tanpa pernah ditinjau siapa pun. Dengan menyalin, mengubah
 * pengaturan hanya berdampak pada pengajuan BARU, dan riwayat persetujuan tetap
 * dapat diaudit apa adanya.
 *
 * Prinsipnya sama dengan Keputusan D9 (menyimpan jarak terhitung saat presensi
 * agar mengubah radius tidak mengubah makna riwayat) dan Keputusan D43
 * (menyimpan nilai lama saat persetujuan koreksi).
 *
 * `step_name` disimpan sebagai SALINAN TEKS, bukan foreign key: nama langkah
 * yang berlaku saat pengajuan dibuat harus tetap terbaca meski langkah aslinya
 * kelak diganti nama atau dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_request_approvals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('overtime_request_id')
                  ->constrained('overtime_requests')
                  ->onDelete('cascade');

            $table->unsignedTinyInteger('order_seq');

            // ── Salinan definisi langkah saat pengajuan dibuat ─────────────
            $table->string('step_name', 100);
            $table->string('approver_type', 20);
            $table->unsignedBigInteger('approver_role_id')->nullable();
            $table->json('approver_employee_ids')->nullable();

            // ── Hasil ──────────────────────────────────────────────────────
            $table->string('status', 20)->default('waiting'); // waiting|approved|rejected|skipped

            // Siapa yang BENAR-BENAR menekan tombol. Tidak selalu sama dengan
            // definisi di atas: sebuah role dapat dipegang banyak orang, dan
            // cukup satu di antaranya yang bertindak (Keputusan D80).
            $table->unsignedBigInteger('acted_by')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->foreign('acted_by')
                  ->references('employee_id')->on('employee')
                  ->nullOnDelete();

            $table->unique(['overtime_request_id', 'order_seq'], 'overtime_approval_request_order_unique');
            $table->index(['status'], 'overtime_approval_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_request_approvals');
    }
};
