<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * RIWAYAT persetujuan milik satu dokumen Cash Advance — SALINAN langkah, bukan
 * rujukan ke konfigurasi.
 *
 * ── KENAPA DISALIN, BUKAN DIRUJUK ──────────────────────────────────────────
 * Kalau baris ini hanya menyimpan `step_id`, mengubah alur di halaman Settings
 * akan mengubah arti dokumen yang SEDANG BERJALAN. Bahaya terbesarnya bukan
 * tampilan: menghapus langkah yang sedang ditunggu membuat dokumen MELOMPAT jadi
 * disetujui tanpa ditinjau siapa pun, dan tidak ada satu pun galat yang muncul.
 *
 * Aturan asimetris Keputusan D116 berlaku di sini juga:
 *   MENAMBAH langkah   -> BOLEH diterapkan ke dokumen terbuka (memperketat),
 *                         lewat kotak centang `apply_to_open`
 *   MENGHAPUS / melonggarkan -> TIDAK PERNAH berlaku surut
 *
 * ── EMPAT HAL YANG IKUT DIBEKUKAN ──────────────────────────────────────────
 *
 *   step_name             ganti nama langkah di Settings tidak boleh mengubah
 *                         arti riwayat lama
 *   actor_role            🔴 kolom inilah yang menentukan siapa yang tercetak di
 *                         kolom "Approved by". Kalau ia dibaca dari konfigurasi
 *                         saat mencetak, mencetak ulang dokumen lama setelah alur
 *                         diubah menghasilkan KERTAS YANG BERBEDA — dengan nama
 *                         orang yang keliru di kolom persetujuan
 *   approver_role_id /    kandidat dibekukan, sehingga mengganti daftar di
 *   approver_employee_ids Settings tidak mengubah penyetuju dokumen yang menunggu
 *   chosen_by_requester   menandai baris yang penyetujunya DIPILIH PEMOHON, bukan
 *                         ditentukan konfigurasi — pertanyaan "kenapa dia yang
 *                         menyetujui?" harus punya jawaban di data
 *
 * Saat pemohon memilih approver lewat dropdown (langkah ber-requester_selectable),
 * `approver_employee_ids` diisi SATU id — bukan seluruh kandidat. Pilihannya
 * membeku pada detik submit.
 *
 * Status `skipped` dipakai saat dokumen dibatalkan atau ditolak sebelum langkah
 * ini sempat dijalani. Ia bukan hiasan: `signatureColumns()` MELEWATI langkah
 * berstatus skipped, sehingga cetakan dokumen batal tidak menampilkan kolom
 * tanda tangan untuk orang yang tidak akan pernah bertindak — perilaku yang baru
 * terlihat saat cetakan Purchase Request dirender betulan di P4.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_advance_approvals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cash_advance_id')
                  ->constrained('cash_advances')
                  ->cascadeOnDelete();

            $table->unsignedTinyInteger('order_seq');

            // Disalin dari cash_advance_approval_steps saat dokumen dibuat.
            $table->string('step_name', 100);
            $table->string('actor_role', 20)->default('approver'); // verificator | approver
            $table->string('approver_type', 20)->default('role');
            $table->unsignedBigInteger('approver_role_id')->nullable();
            $table->json('approver_employee_ids')->nullable();

            // Baris ini penyetujunya dipilih pemohon (D126).
            $table->boolean('chosen_by_requester')->default(false);

            // pending | approved | rejected | skipped
            $table->string('status', 20)->default('pending');

            // Siapa yang benar-benar bertindak. Inilah nama yang tercetak —
            // bukan kandidat, bukan setelan.
            $table->unsignedBigInteger('acted_by')->nullable();
            $table->timestamp('acted_at')->nullable();

            // Alasan penolakan WAJIB diisi di service (minimal 5 huruf);
            // pada persetujuan sifatnya opsional.
            $table->string('note', 500)->nullable();

            $table->json('flags')->nullable();
            $table->timestamps();

            $table->foreign('approver_role_id')
                  ->references('id')->on('employee_role')
                  ->nullOnDelete();

            $table->foreign('acted_by')
                  ->references('employee_id')->on('employee')
                  ->nullOnDelete();

            $table->unique(['cash_advance_id', 'order_seq'], 'ca_appr_doc_order_unq');
            $table->index('status', 'ca_appr_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_advance_approvals');
    }
};
