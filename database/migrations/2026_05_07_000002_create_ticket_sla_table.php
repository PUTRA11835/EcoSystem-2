<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_sla', function (Blueprint $table) {
            $table->id();

            // Diisi pertama kali saat email/request masuk (staging dibuat)
            // NULL jika tiket dibuat langsung tanpa melalui staging
            $table->unsignedBigInteger('staging_ticket_id')->nullable()->unique();

            // Diisi setelah helpdesk validasi dan tiket resmi dibuat
            // NULL selama masih di tahap pending_validation
            $table->unsignedBigInteger('ticket_id')->nullable()->unique();

            // Diisi setelah priority + scale diset (saat validasi)
            // NULL jika tidak ada policy yang cocok untuk customer ini
            $table->unsignedBigInteger('sla_policy_id')->nullable();

            // ─── TITIK NOL SLA ───────────────────────────────────────────────
            // Diisi dari staging_tickets.created_at saat email/request masuk
            // TIDAK PERNAH BERUBAH sepanjang lifecycle tiket
            $table->timestamp('sla_start_at');

            // Deadline first response = sla_start_at + policy.response_hours
            // NULL sampai policy di-attach (saat validasi)
            $table->timestamp('response_due_at')->nullable();

            // Deadline resolution = sla_start_at + policy.resolution_hours
            // Bisa di-extend setiap kali waiting customer selesai
            $table->timestamp('resolution_due_at')->nullable();

            // ─── HASIL RESPONSE ──────────────────────────────────────────────
            // Kapan helpdesk selesai validasi dan membuat tiket
            $table->timestamp('first_responded_at')->nullable();

            // Durasi dari sla_start_at sampai first_responded_at (dalam jam)
            $table->decimal('validation_duration_hours', 8, 2)->nullable();

            $table->enum('response_status', [
                'pending',   // Belum ada first response
                'met',       // First response dalam batas waktu
                'breached',  // First response terlambat
            ])->default('pending');

            // ─── HASIL RESOLUTION ────────────────────────────────────────────
            // Kapan tiket resmi di-close
            $table->timestamp('resolved_at')->nullable();

            // Akumulasi total jam semua periode waiting customer (tidak dihitung ke SLA)
            $table->decimal('total_waiting_hours', 8, 2)->default(0);

            // Waktu kerja bersih = total elapsed - total_waiting_hours
            // Diisi saat tiket di-close
            $table->decimal('net_resolution_hours', 8, 2)->nullable();

            $table->enum('resolution_status', [
                'pending_validation', // Email masuk, belum divalidasi helpdesk
                'pending',            // Tiket aktif, SLA sedang berjalan
                'paused',             // SLA berhenti sementara, menunggu customer/pihak lain
                'met',                // Tiket close, resolution dalam batas waktu
                'breached',           // Tiket close atau deadline lewat, SLA tidak terpenuhi
            ])->default('pending_validation');

            $table->timestamps();

            // Index untuk scheduler breach check — query tiket aktif yang belum selesai
            $table->index(['resolution_status', 'resolved_at'], 'ticket_sla_active_idx');
            $table->index(['response_status', 'first_responded_at'], 'ticket_sla_response_idx');
            // Index untuk dashboard/report per customer via join ke ticket
            $table->index('sla_policy_id', 'ticket_sla_policy_idx');

            $table->foreign('staging_ticket_id')
                ->references('id')
                ->on('staging_tickets')
                ->nullOnDelete();

            $table->foreign('ticket_id')
                ->references('ticket_id')
                ->on('ticket')
                ->nullOnDelete();

            $table->foreign('sla_policy_id')
                ->references('id')
                ->on('sla_policies')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_sla');
    }
};
