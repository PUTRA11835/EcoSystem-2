<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ticket_sla_pauses was created by 2026_05_28_100000_create_sla_tables and later
 * altered by 2026_06_09_000001 / 2026_06_09_000003 / 2026_06_22_000001 — all of
 * which are recorded as already run. On some environments the table itself is
 * missing (dropped outside of migrations) while the migrations table still says
 * those ran, so `php artisan migrate` won't recreate it. This recreates it with
 * the final schema those migrations already produced, guarded by hasTable so it
 * is a no-op wherever the table already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ticket_sla_pauses')) {
            return;
        }

        Schema::create('ticket_sla_pauses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->enum('pause_reason', ['waiting_customer', 'sent_to_sap', 'sent_to_support', 'on_hold', 'meeting']);
            $table->string('triggered_by_status', 100)->nullable();
            $table->dateTime('started_at');
            $table->dateTime('scheduled_end_at')->nullable();
            $table->dateTime('ended_at')->nullable();
            $table->decimal('duration_hours', 8, 2)->nullable();
            $table->unsignedInteger('started_by_message_id')->nullable();
            $table->unsignedInteger('ended_by_message_id')->nullable();
            $table->unsignedBigInteger('resumed_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['ticket_id', 'ended_at'], 'sla_pauses_active_idx');

            $table->foreign('ticket_id')
                  ->references('ticket_id')->on('ticket')
                  ->cascadeOnDelete();
            $table->foreign('started_by_message_id')
                  ->references('id')->on('ticket_message')
                  ->nullOnDelete();
            $table->foreign('ended_by_message_id')
                  ->references('id')->on('ticket_message')
                  ->nullOnDelete();
            $table->foreign('resumed_by')
                  ->references('employee_id')->on('employee')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // No-op: this migration only repairs a table that other migrations already
        // own the lifecycle of (see 2026_05_28_100000_create_sla_tables::down()).
    }
};
