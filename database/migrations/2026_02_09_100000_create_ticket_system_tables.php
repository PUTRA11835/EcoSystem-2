<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ============================================================================
 * TICKET SYSTEM TABLES
 * ============================================================================
 *
 *
 * @date 2026-02-09
 */
return new class extends Migration
{
    public function up(): void
    {
        // ====================================================================
        // 1. UPDATE TICKET TABLE
        // ====================================================================
        Schema::table('ticket', function (Blueprint $table) {
            // Add new columns if they don't exist
            if (!Schema::hasColumn('ticket', 'ticket_number')) {
                $table->string('ticket_number')->unique()->after('ticket_id');
            }
            if (!Schema::hasColumn('ticket', 'agent_id')) {
                $table->unsignedBigInteger('agent_id')->nullable()->after('ticket_number');
            }
            if (!Schema::hasColumn('ticket', 'subject')) {
                $table->string('subject')->nullable()->after('customer_id');
            }
            if (!Schema::hasColumn('ticket', 'channel')) {
                $table->enum('channel', ['email', 'web'])->default('web')->after('type');
            }
            if (!Schema::hasColumn('ticket', 'email_thread_id')) {
                $table->string('email_thread_id')->nullable()->after('channel');
            }
            if (!Schema::hasColumn('ticket', 'last_customer_reply_at')) {
                $table->timestamp('last_customer_reply_at')->nullable();
            }
            if (!Schema::hasColumn('ticket', 'last_agent_reply_at')) {
                $table->timestamp('last_agent_reply_at')->nullable();
            }
            if (!Schema::hasColumn('ticket', 'last_message_at')) {
                $table->timestamp('last_message_at')->nullable();
            }
            if (!Schema::hasColumn('ticket', 'deleted_at')) {
                $table->softDeletes();
            }

            // Update status enum if needed
            // Note: Modifying enum requires raw SQL or recreating column
        });

        // ====================================================================
        // 6. CUSTOMER MANDAYS TABLE
        // ====================================================================
        Schema::create('customer_mandays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->integer('version')->default(1);
            $table->unsignedBigInteger('proposed_by_agent_id');
            $table->timestamp('proposed_at')->nullable();
            $table->timestamp('submitted_to_customer_at')->nullable();
            $table->enum('status', ['draft', 'pending_customer', 'approved', 'rejected'])->default('draft');
            $table->timestamp('customer_response_at')->nullable();
            $table->text('customer_notes')->nullable();
            $table->decimal('total_mandays', 10, 2)->default(0);
            $table->timestamps();

            $table->index('ticket_id', 'idx_cm_ticket');
            $table->unique(['ticket_id', 'version'], 'idx_cm_ticket_version');
            $table->index('status', 'idx_cm_status');
            $table->index('proposed_by_agent_id', 'idx_cm_agent');

            $table->foreign('ticket_id')
                ->references('ticket_id')
                ->on('ticket')
                ->onDelete('cascade');
            $table->foreign('proposed_by_agent_id')
                ->references('employee_id')
                ->on('employee')
                ->onDelete('cascade');
        });

        // ====================================================================
        // 8. CONSULTANT MANDAYS TABLE
        // ====================================================================
        Schema::create('consultant_mandays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('proposed_by_agent_id');
            $table->timestamp('proposed_at')->nullable();
            $table->timestamp('last_edited_at')->nullable();
            $table->enum('status', ['draft', 'pending_approval', 'approved', 'rejected'])->default('draft');
            $table->unsignedBigInteger('approved_by_head_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->decimal('total_mandays', 10, 2)->default(0);
            $table->timestamps();

            $table->unique('ticket_id', 'idx_cim_ticket');
            $table->index('status', 'idx_cim_status');
            $table->index('proposed_by_agent_id', 'idx_cim_agent');
            $table->index('approved_by_head_id', 'idx_cim_head');

            $table->foreign('ticket_id')
                ->references('ticket_id')
                ->on('ticket')
                ->onDelete('cascade');
            $table->foreign('proposed_by_agent_id')
                ->references('employee_id')
                ->on('employee')
                ->onDelete('cascade');
            $table->foreign('approved_by_head_id')
                ->references('employee_id')
                ->on('employee')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        // Drop detail tables first to avoid FK constraint errors
        Schema::dropIfExists('consultant_mandays_detail');
        Schema::dropIfExists('customer_mandays_detail');


        Schema::dropIfExists('consultant_mandays');
        Schema::dropIfExists('customer_mandays');

        // Remove added columns from ticket table
        Schema::table('ticket', function (Blueprint $table) {
            $columns = [
                'ticket_number', 'agent_id', 'subject', 'channel',
                'email_thread_id', 'last_customer_reply_at',
                'last_agent_reply_at', 'last_message_at', 'deleted_at'
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('ticket', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
