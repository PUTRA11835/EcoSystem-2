<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ============================================================================
 * TICKET SYSTEM TABLES
 * ============================================================================
 *
 * Tables created:
 * - ticket (updated structure)
 * - ticket_message
 * - ticket_attachment
 * - ticket_account
 * - ticket_team
 * - customer_mandays
 * - customer_mandays_detail
 * - consultant_mandays
 * - consultant_mandays_detail
 * - ticket_rating
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
        // 2. TICKET MESSAGE TABLE
        // ====================================================================
        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->enum('sender_type', ['customer', 'employee', 'system']);
            $table->unsignedBigInteger('sender_id')->nullable();
            $table->string('sender_email')->nullable();
            $table->string('sender_name')->nullable();
            $table->text('message');
            $table->boolean('is_internal_note')->default(false);
            $table->enum('channel', ['email', 'web'])->default('web');
            $table->string('email_message_id')->nullable();
            $table->string('email_in_reply_to')->nullable();
            $table->boolean('is_read_by_customer')->default(false);
            $table->boolean('is_read_by_agent')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('ticket_id', 'idx_message_ticket');
            $table->index('sender_type', 'idx_message_sender_type');
            $table->index('sender_id', 'idx_message_sender');
            $table->index('channel', 'idx_message_channel');
            $table->index('email_message_id', 'idx_message_email_id');
            $table->index('created_at', 'idx_message_created');
            $table->index('is_internal_note', 'idx_message_internal');
        });

        // ====================================================================
        // 3. TICKET ATTACHMENT TABLE
        // ====================================================================
        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('message_id')->nullable();
            $table->enum('uploaded_by_type', ['customer', 'employee']);
            $table->unsignedBigInteger('uploaded_by_id');
            $table->string('attachment_type');
            $table->string('link_url');
            $table->string('link_title')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('ticket_id', 'idx_attachment_ticket');
            $table->index('message_id', 'idx_attachment_message');
            $table->index('uploaded_by_type', 'idx_attachment_uploader_type');
        });

        // ====================================================================
        // 4. TICKET ACCOUNT TABLE (Who can access this ticket)
        // ====================================================================
        Schema::create('ticket_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->enum('account_type', ['customer', 'employee']);
            $table->string('account_email');
            $table->boolean('can_view')->default(true);
            $table->boolean('can_reply')->default(true);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index('ticket_id', 'idx_participant_ticket');
            $table->unique(['ticket_id', 'account_type', 'account_email'], 'idx_participant_unique');
            $table->index('account_type', 'idx_participant_type');
        });

        // ====================================================================
        // 5. TICKET TEAM TABLE
        // ====================================================================
        Schema::create('ticket_teams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('employee_id');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->index('ticket_id', 'idx_team_ticket');
            $table->index('employee_id', 'idx_team_employee');
            $table->unique(['ticket_id', 'employee_id'], 'idx_team_ticket_employee');
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
        });

        // ====================================================================
        // 7. CUSTOMER MANDAYS DETAIL TABLE
        // ====================================================================
        Schema::create('customer_mandays_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_mandays_id');
            $table->string('module');
            $table->decimal('mandays', 10, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('customer_mandays_id', 'idx_cmd_customer_mandays');
            $table->index('module', 'idx_cmd_module');
            $table->index(['customer_mandays_id', 'module'], 'idx_cmd_mandays_module');

            $table->foreign('customer_mandays_id')
                ->references('id')
                ->on('customer_mandays')
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
        });

        // ====================================================================
        // 9. CONSULTANT MANDAYS DETAIL TABLE
        // ====================================================================
        Schema::create('consultant_mandays_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('consultant_mandays_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('module');
            $table->decimal('mandays', 10, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->index('consultant_mandays_id', 'idx_cimd_consultant_mandays');
            $table->index('employee_id', 'idx_cimd_employee');
            $table->index('module', 'idx_cimd_module');
            $table->unique(['consultant_mandays_id', 'employee_id', 'module'], 'idx_cimd_mandays_employee_module');

            $table->foreign('consultant_mandays_id')
                ->references('id')
                ->on('consultant_mandays')
                ->onDelete('cascade');
        });

        // ====================================================================
        // 10. TICKET RATING TABLE
        // ====================================================================
        Schema::create('ticket_ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('customer_id');
            $table->integer('rating')->nullable();
            $table->string('comment')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('ticket_id');
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_ratings');
        Schema::dropIfExists('consultant_mandays_details');
        Schema::dropIfExists('consultant_mandays');
        Schema::dropIfExists('customer_mandays_details');
        Schema::dropIfExists('customer_mandays');
        Schema::dropIfExists('ticket_teams');
        Schema::dropIfExists('ticket_accounts');
        Schema::dropIfExists('ticket_attachments');
        Schema::dropIfExists('ticket_messages');

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
