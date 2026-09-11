<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ============================================================================
 * DELIVERY SUPPORT — RECONS (rekonsiliasi tiket)
 * ============================================================================
 *
 * Satu "Recons" = satu batch rekonsiliasi berisi sekumpulan tiket yang sudah
 * closed dan punya Customer MD, dikumpulkan untuk keperluan penagihan/berita
 * acara ke customer.
 *
 * Tabel:
 * - delivery_support_recons          → header batch (nomor, tanggal, status)
 * - delivery_support_recons_tickets  → baris tiket di dalam satu batch
 *
 * Catatan desain:
 * - `man_days_snapshot` menyimpan nilai `ticket.man_days` SAAT tiket dimasukkan
 *   ke batch. Kalau man_days tiket diubah setelahnya, angka di Recons yang
 *   sudah disubmit tidak ikut berubah (audit trail penagihan tetap konsisten).
 * - Tidak ada perubahan apa pun ke tabel `ticket`. Kolom "Close Date" memakai
 *   `ticket.end_date` yang sudah diisi otomatis oleh
 *   TicketController::updateStatus() saat status berubah jadi `closed`.
 *
 * @date 2026-09-03
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('delivery_support_recons')) {
            Schema::create('delivery_support_recons', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('delivery_support_id');
                $table->string('recons_number');
                $table->text('description')->nullable();
                $table->date('recons_date')->nullable();
                $table->enum('status', ['draft', 'submitted'])->default('draft');

                // Employee yang membuat/terakhir menyimpan draft ("user Id yang
                // melakukan recons") dan yang men-submit-nya.
                $table->unsignedBigInteger('created_by_id')->nullable();
                $table->unsignedBigInteger('submitted_by_id')->nullable();
                $table->timestamp('submitted_at')->nullable();

                $table->timestamps();

                // Nomor unik per Delivery Support (support lain boleh punya nomor
                // yang sama; dalam satu support tidak boleh dobel).
                $table->unique(['delivery_support_id', 'recons_number'], 'uniq_support_recons_number');
                $table->index(['delivery_support_id', 'status'], 'idx_support_recons_status');

                $table->foreign('delivery_support_id', 'fk_support_recons_support')
                    ->references('id')->on('delivery_support')->onDelete('cascade');
                $table->foreign('created_by_id', 'fk_support_recons_created_by')
                    ->references('employee_id')->on('employee')->onDelete('set null');
                $table->foreign('submitted_by_id', 'fk_support_recons_submitted_by')
                    ->references('employee_id')->on('employee')->onDelete('set null');
            });
        }

        if (!Schema::hasTable('delivery_support_recons_tickets')) {
            Schema::create('delivery_support_recons_tickets', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('delivery_support_recons_id');
                $table->unsignedBigInteger('ticket_id');
                $table->decimal('man_days_snapshot', 8, 2)->nullable();
                $table->timestamps();

                $table->unique(['delivery_support_recons_id', 'ticket_id'], 'uniq_recons_ticket');
                // Dipakai query eligibilitas "tiket belum pernah masuk Recons".
                $table->index('ticket_id', 'idx_recons_ticket_id');

                $table->foreign('delivery_support_recons_id', 'fk_recons_ticket_recons')
                    ->references('id')->on('delivery_support_recons')->onDelete('cascade');
                $table->foreign('ticket_id', 'fk_recons_ticket_ticket')
                    ->references('ticket_id')->on('ticket')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_support_recons_tickets');
        Schema::dropIfExists('delivery_support_recons');
    }
};
