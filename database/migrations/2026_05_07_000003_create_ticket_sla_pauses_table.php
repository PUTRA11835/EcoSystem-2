<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_sla_pauses', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('ticket_id');

            // Alasan SLA pause — memetakan ke nilai jarvies_status yang menjadi trigger
            $table->enum('pause_reason', [
                'waiting_customer', // jarvies_status: proposed solution / author action
                'sent_to_sap',      // jarvies_status: sent in to SAP
                'sent_to_support',  // jarvies_status: sent it to support
                'on_hold',          // ticket.status: hold
            ]);

            // Nilai jarvies_status yang secara langsung trigger pause ini
            // Disimpan untuk audit trail dan analisis
            $table->string('triggered_by_status', 100)->nullable();

            // Kapan SLA mulai pause
            $table->timestamp('started_at');

            // Kapan SLA resume — NULL berarti pause masih aktif saat ini
            $table->timestamp('ended_at')->nullable();

            // Durasi pause dalam jam, dihitung saat ended_at terisi
            // Dihitung berdasarkan business hours atau 24 jam sesuai policy tiket
            $table->decimal('duration_hours', 8, 2)->nullable();

            // Pesan agen yang menjadi trigger pause (saat set proposed solution dst)
            $table->unsignedInteger('started_by_message_id')->nullable();

            // Pesan customer yang menjadi trigger resume
            $table->unsignedInteger('ended_by_message_id')->nullable();

            // Diisi jika resume dilakukan secara manual oleh agen
            // (bukan dari pesan customer — misal: customer konfirmasi via telepon)
            $table->unsignedBigInteger('resumed_by')->nullable();

            // created_at saja — tidak ada updated_at karena record ini tidak diubah,
            // hanya di-close (ended_at diisi) saat resume
            $table->timestamp('created_at')->useCurrent();

            // Index untuk cari pause aktif dengan cepat (ended_at IS NULL)
            $table->index(['ticket_id', 'ended_at'], 'sla_pauses_active_idx');

            $table->foreign('ticket_id')
                ->references('ticket_id')
                ->on('ticket')
                ->cascadeOnDelete();

            $table->foreign('started_by_message_id')
                ->references('id')
                ->on('ticket_message')
                ->nullOnDelete();

            $table->foreign('ended_by_message_id')
                ->references('id')
                ->on('ticket_message')
                ->nullOnDelete();

            $table->foreign('resumed_by')
                ->references('employee_id')
                ->on('employee')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_sla_pauses');
    }
};
