<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staging / temp layer untuk tiket yang masuk dari customer (web form atau email).
 *
 * Flow:
 *   Customer submit → staging_tickets (status=unvalidated)
 *   Admin validasi  → approved  → Ticket::create() + staging.ticket_id terisi
 *   Admin tolak     → rejected  + rejection_reason terisi
 *
 * Tabel `ticket` TIDAK disentuh sampai admin menyetujui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staging_tickets', function (Blueprint $table) {
            $table->id();

            // Siapa yang submit
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customer', 'customer_id')
                ->nullOnDelete();

            // Data tiket yang disubmit customer
            $table->text('description');
            $table->enum('ticket_priority', ['Low', 'Medium', 'High'])->default('Medium');

            // Status staging
            $table->enum('status', ['unvalidated', 'approved', 'rejected'])->default('unvalidated');
            $table->text('rejection_reason')->nullable();

            // Channel: web (form Jarvies) | email (email masuk dari MS365)
            $table->string('channel', 20)->default('web');

            // Untuk email-channel: simpan thread info agar tidak duplikat
            $table->string('email_thread_id')->nullable()->index();
            $table->string('submitted_by_email')->nullable();

            // Siapa & kapan divalidasi
            $table->unsignedBigInteger('validated_by')->nullable();
            $table->foreign('validated_by')
                ->references('employee_id')->on('employee')
                ->nullOnDelete();
            $table->timestamp('validated_at')->nullable();

            // FK ke ticket setelah di-approve (unique: 1 staging → 1 ticket)
            $table->unsignedBigInteger('ticket_id')->nullable()->unique();
            $table->foreign('ticket_id')
                ->references('ticket_id')->on('ticket')
                ->nullOnDelete();

            $table->timestamps();

            // Index untuk query admin (filter by status, customer)
            $table->index(['status', 'customer_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staging_tickets');
    }
};
