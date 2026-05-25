<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_sla_events', function (Blueprint $table) {
            $table->id();

            // NULL pada event 'email_received' — tiket belum ada saat email masuk
            $table->unsignedBigInteger('ticket_id')->nullable();

            // NULL setelah tiket resmi dibuat
            $table->unsignedBigInteger('staging_ticket_id')->nullable();

            // Pesan yang menjadi trigger event ini (nullable — beberapa event tidak dari pesan)
            $table->unsignedInteger('message_id')->nullable();

            $table->enum('event_type', [
                'email_received',       // t=0: staging_ticket dibuat, SLA mulai
                'ticket_validated',     // Helpdesk approve, tiket resmi dibuat — response_hours diisi
                'resolution_sent',      // Agen set proposed solution/author action — SLA pause, resolution_hours diisi
                'customer_replied',     // Customer balas pertama setelah pause — SLA resume, waiting_hours diisi
                'escalated_to_sap',     // jarvies_status = sent in to SAP — SLA pause
                'escalated_to_support', // jarvies_status = sent it to support — SLA pause
                'sla_warning',          // Scheduler: sisa waktu SLA ≤ 20% dari target
                'sla_breached',         // Scheduler: melewati deadline resolution
                'ticket_closed',        // Tiket di-close — resolution_hours final diisi
            ]);

            // Waktu event ini terjadi
            $table->timestamp('event_at');

            // ─── KOLOM KALKULASI (hanya diisi pada event tertentu) ──────────
            // Durasi waiting pada event customer_replied
            // = business/24jam hours dari pause.started_at sampai NOW
            $table->decimal('waiting_hours', 6, 2)->nullable();

            // Durasi response pada event ticket_validated
            // = business/24jam hours dari sla_start_at sampai NOW
            $table->decimal('response_hours', 6, 2)->nullable();

            // Akumulasi net jam kerja sampai event ini terjadi
            // Diisi pada: resolution_sent dan ticket_closed
            // = total elapsed - total waiting sampai titik ini
            $table->decimal('resolution_hours', 6, 2)->nullable();

            // Deskripsi event — bisa otomatis dari sistem atau catatan manual
            $table->text('notes')->nullable();

            // Siapa yang trigger event (employee_id atau customer_id tergantung triggered_by_type)
            $table->unsignedBigInteger('triggered_by')->nullable();

            $table->enum('triggered_by_type', [
                'employee',
                'customer',
                'system', // Dari scheduler atau observer otomatis
            ])->nullable();

            // Hanya created_at — event log tidak pernah diubah setelah dibuat
            $table->timestamp('created_at')->useCurrent();

            // Index utama untuk menampilkan log di UI detail tiket (order by event_at)
            $table->index(['ticket_id', 'event_at'], 'sla_events_ticket_idx');

            // Index untuk scheduler — cari tiket yang sudah dapat warning/breach atau belum
            $table->index(['event_type', 'ticket_id'], 'sla_events_type_idx');

            $table->foreign('ticket_id')
                ->references('ticket_id')
                ->on('ticket')
                ->nullOnDelete();

            $table->foreign('staging_ticket_id')
                ->references('id')
                ->on('staging_tickets')
                ->nullOnDelete();

            $table->foreign('message_id')
                ->references('id')
                ->on('ticket_message')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_sla_events');
    }
};
