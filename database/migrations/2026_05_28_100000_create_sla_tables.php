<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. sla_policies ─────────────────────────────────────────────────
        Schema::create('sla_policies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->enum('priority', ['Low', 'Medium', 'High', 'Very High']);
            $table->enum('scale', ['Simple', 'Medium', 'Complex']);
            $table->decimal('response_hours', 6, 2);
            $table->decimal('resolution_hours', 6, 2);
            $table->tinyInteger('is_24_hours')->default(0);
            $table->tinyInteger('is_active')->default(1);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['customer_id', 'priority', 'scale'], 'sla_policies_unique');
            $table->index(['customer_id', 'priority', 'scale', 'is_active'], 'sla_policies_lookup_idx');

            $table->foreign('customer_id')
                  ->references('customer_id')->on('customer')
                  ->nullOnDelete();
            $table->foreign('created_by')
                  ->references('employee_id')->on('employee')
                  ->nullOnDelete();
        });

        // ── 2. ticket_sla ────────────────────────────────────────────────────
        Schema::create('ticket_sla', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('staging_ticket_id')->nullable()->unique();
            $table->unsignedBigInteger('ticket_id')->nullable()->unique();
            $table->unsignedBigInteger('sla_policy_id')->nullable();
            $table->enum('sla_mode', ['full', 'response_only'])->default('full');
            $table->timestamp('sla_start_at');
            $table->timestamp('response_due_at')->nullable();
            $table->timestamp('resolution_due_at')->nullable();
            $table->timestamp('first_responded_at')->nullable();
            $table->decimal('validation_duration_hours', 8, 2)->nullable();
            $table->enum('response_status', ['pending', 'met', 'breached'])->default('pending');
            $table->timestamp('session_start_at')->nullable();
            $table->timestamp('solution_started_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->decimal('total_waiting_hours', 8, 2)->default(0);
            $table->decimal('net_resolution_hours', 8, 2)->nullable();
            $table->enum('resolution_status', ['pending_validation', 'pending', 'paused', 'met', 'breached'])
                  ->default('pending_validation');
            $table->enum('ball_holder', ['helpdesk', 'customer', 'sap'])->default('helpdesk');
            $table->timestamp('sla_paused_at')->nullable();
            $table->timestamps();

            $table->index(['resolution_status', 'resolved_at'], 'ticket_sla_active_idx');
            $table->index(['response_status', 'first_responded_at'], 'ticket_sla_response_idx');
            $table->index('sla_policy_id', 'ticket_sla_policy_idx');

            $table->foreign('staging_ticket_id')
                  ->references('id')->on('staging_tickets')
                  ->nullOnDelete();
            $table->foreign('ticket_id')
                  ->references('ticket_id')->on('ticket')
                  ->nullOnDelete();
            $table->foreign('sla_policy_id')
                  ->references('id')->on('sla_policies')
                  ->nullOnDelete();
        });

        // ── 3. ticket_sla_pauses ─────────────────────────────────────────────
        Schema::create('ticket_sla_pauses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->enum('pause_reason', ['waiting_customer', 'sent_to_sap', 'sent_to_support', 'on_hold']);
            $table->string('triggered_by_status', 100)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
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

        // ── 4. ticket_sla_events ─────────────────────────────────────────────
        Schema::create('ticket_sla_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->unsignedBigInteger('staging_ticket_id')->nullable();
            $table->unsignedInteger('message_id')->nullable();
            $table->enum('event_type', [
                'email_received',
                'ticket_validated',
                'agent_replied',
                'customer_replied',
                'resolution_sent',
                'escalated_to_sap',
                'escalated_to_support',
                'sla_warning',
                'sla_breached',
                'ticket_closed',
            ]);
            $table->string('jarvis_status', 50)->nullable();
            $table->timestamp('event_at');
            $table->decimal('waiting_hours', 6, 2)->nullable();
            $table->decimal('response_hours', 6, 2)->nullable();
            $table->decimal('resolution_hours', 6, 2)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('triggered_by')->nullable();
            $table->enum('triggered_by_type', ['employee', 'customer', 'system'])->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['ticket_id', 'event_at'], 'sla_events_ticket_idx');
            $table->index(['event_type', 'ticket_id'], 'sla_events_type_idx');

            $table->foreign('ticket_id')
                  ->references('ticket_id')->on('ticket')
                  ->nullOnDelete();
            $table->foreign('staging_ticket_id')
                  ->references('id')->on('staging_tickets')
                  ->nullOnDelete();
            $table->foreign('message_id')
                  ->references('id')->on('ticket_message')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_sla_events');
        Schema::dropIfExists('ticket_sla_pauses');
        Schema::dropIfExists('ticket_sla');
        Schema::dropIfExists('sla_policies');
    }
};
