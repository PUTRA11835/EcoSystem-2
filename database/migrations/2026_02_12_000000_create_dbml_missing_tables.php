<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration to add tables defined in ecosystem_db_diagram.dbml
 * that are missing from current migrations (including singular variants).
 */
return new class extends Migration
{
    public function up(): void
    {
        // --------------------------------------------------------------------
        // AUTH USERS
        // --------------------------------------------------------------------
        if (!Schema::hasTable('auth_users')) {
            Schema::create('auth_users', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->enum('user_type', ['employee', 'customer']);
                $table->unsignedBigInteger('employee_id')->nullable();
                $table->unsignedBigInteger('customer_id')->nullable();
                $table->string('username')->unique();
                $table->string('email')->unique();
                $table->string('phone')->unique();
                $table->string('password');
                $table->timestamp('last_login_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('email_verified_at')->nullable();
                $table->timestamp('phone_verified_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->index('username');
                $table->index('email');
                $table->index('phone');

                $table->foreign('employee_id')
                    ->references('employee_id')
                    ->on('employee')
                    ->nullOnDelete();
                $table->foreign('customer_id')
                    ->references('customer_id')
                    ->on('customer')
                    ->nullOnDelete();
            });
        }

        // --------------------------------------------------------------------
        // EMPLOYEE HISTORY
        // --------------------------------------------------------------------
        if (!Schema::hasTable('employee_history')) {
            Schema::create('employee_history', function (Blueprint $table) {
                $table->bigIncrements('history_id');
                $table->unsignedBigInteger('employee_id');
                $table->string('action')->nullable();
                $table->text('description')->nullable();
                $table->unsignedBigInteger('performed_by')->nullable();
                $table->timestamp('performed_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->foreign('employee_id')
                    ->references('employee_id')
                    ->on('employee')
                    ->cascadeOnDelete();
                $table->foreign('performed_by')
                    ->references('employee_id')
                    ->on('employee')
                    ->nullOnDelete();
            });
        }

        // --------------------------------------------------------------------
        // TICKET MESSAGE (SINGULAR)
        // --------------------------------------------------------------------
        if (!Schema::hasTable('ticket_message')) {
            Schema::create('ticket_message', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->increments('id');
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
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->index('ticket_id', 'idx_message_ticket');
                $table->index('sender_type', 'idx_message_sender_type');
                $table->index('sender_id', 'idx_message_sender');
                $table->index('channel', 'idx_message_channel');
                $table->index('email_message_id', 'idx_message_email_id');
                $table->index('created_at', 'idx_message_created');
                $table->index('is_internal_note', 'idx_message_internal');

                $table->foreign('ticket_id')
                    ->references('ticket_id')
                    ->on('ticket')
                    ->cascadeOnDelete();
            });
        }

        // --------------------------------------------------------------------
        // TICKET ATTACHMENT (SINGULAR)
        // --------------------------------------------------------------------
        if (!Schema::hasTable('ticket_attachment')) {
            Schema::create('ticket_attachment', function (Blueprint $table) {
                $table->engine = 'InnoDB';
                $table->increments('id');
                $table->unsignedBigInteger('ticket_id');
                $table->unsignedInteger('message_id')->nullable();
                $table->enum('uploaded_by_type', ['customer', 'employee']);
                $table->unsignedBigInteger('uploaded_by_id');
                $table->string('attachment_type');
                $table->string('link_url');
                $table->string('link_title')->nullable();
                $table->text('description')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->index('ticket_id', 'idx_attachment_ticket');
                $table->index('message_id', 'idx_attachment_message');
                $table->index('uploaded_by_type', 'idx_attachment_uploader_type');

                $table->foreign('ticket_id')
                    ->references('ticket_id')
                    ->on('ticket')
                    ->cascadeOnDelete();
                $table->foreign('message_id')
                    ->references('id')
                    ->on('ticket_message')
                    ->nullOnDelete();
            });
        }

        // --------------------------------------------------------------------
        // TICKET ACCOUNT (SINGULAR)
        // --------------------------------------------------------------------
        if (!Schema::hasTable('ticket_account')) {
            Schema::create('ticket_account', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedBigInteger('ticket_id');
                $table->enum('account_type', ['customer', 'employee']);
                $table->string('account_email');
                $table->boolean('can_view')->default(true);
                $table->boolean('can_reply')->default(true);
                $table->timestamp('joined_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->index('ticket_id', 'idx_participant_ticket');
                $table->unique(['ticket_id', 'account_type', 'account_email'], 'idx_participant_unique');
                $table->index('account_type', 'idx_participant_type');

                $table->foreign('ticket_id')
                    ->references('ticket_id')
                    ->on('ticket')
                    ->cascadeOnDelete();
            });
        }

        // --------------------------------------------------------------------
        // TICKET TEAM (SINGULAR)
        // --------------------------------------------------------------------
        if (!Schema::hasTable('ticket_team')) {
            Schema::create('ticket_team', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedBigInteger('ticket_id');
                $table->unsignedBigInteger('employee_id');
                $table->timestamp('joined_at')->nullable();
                $table->timestamp('left_at')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->index('ticket_id', 'idx_team_ticket');
                $table->index('employee_id', 'idx_team_employee');
                $table->unique(['ticket_id', 'employee_id'], 'idx_team_ticket_employee');

                $table->foreign('ticket_id')
                    ->references('ticket_id')
                    ->on('ticket')
                    ->cascadeOnDelete();
                $table->foreign('employee_id')
                    ->references('employee_id')
                    ->on('employee')
                    ->cascadeOnDelete();
            });
        }

        // --------------------------------------------------------------------
        // CUSTOMER MANDAYS DETAIL (SINGULAR)
        // --------------------------------------------------------------------
        if (!Schema::hasTable('customer_mandays_detail')) {
            Schema::create('customer_mandays_detail', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedBigInteger('customer_mandays_id');
                $table->string('module');
                $table->decimal('mandays', 10, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->index('customer_mandays_id', 'idx_cmd_customer_mandays');
                $table->index('module', 'idx_cmd_module');
                $table->index(['customer_mandays_id', 'module'], 'idx_cmd_mandays_module');

                $table->foreign('customer_mandays_id')
                    ->references('id')
                    ->on('customer_mandays')
                    ->cascadeOnDelete();
            });
        }

        // --------------------------------------------------------------------
        // CONSULTANT MANDAYS DETAIL (SINGULAR)
        // --------------------------------------------------------------------
        if (!Schema::hasTable('consultant_mandays_detail')) {
            Schema::create('consultant_mandays_detail', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedBigInteger('consultant_mandays_id');
                $table->unsignedBigInteger('employee_id');
                $table->string('module');
                $table->decimal('mandays', 10, 2)->default(0);
                $table->string('notes')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->index('consultant_mandays_id', 'idx_cimd_consultant_mandays');
                $table->index('employee_id', 'idx_cimd_employee');
                $table->index('module', 'idx_cimd_module');
                $table->unique(['consultant_mandays_id', 'employee_id', 'module'], 'idx_cimd_mandays_employee_module');

                $table->foreign('consultant_mandays_id')
                    ->references('id')
                    ->on('consultant_mandays')
                    ->cascadeOnDelete();
                $table->foreign('employee_id')
                    ->references('employee_id')
                    ->on('employee')
                    ->cascadeOnDelete();
            });
        }

        // --------------------------------------------------------------------
        // TICKET RATING (SINGULAR)
        // --------------------------------------------------------------------
        if (!Schema::hasTable('ticket_rating')) {
            Schema::create('ticket_rating', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedBigInteger('ticket_id');
                $table->unsignedBigInteger('customer_id');
                $table->integer('rating')->nullable();
                $table->string('comment')->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index('ticket_id');
                $table->index('customer_id');

                $table->foreign('ticket_id')
                    ->references('ticket_id')
                    ->on('ticket')
                    ->cascadeOnDelete();
                $table->foreign('customer_id')
                    ->references('customer_id')
                    ->on('customer')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_rating');
        Schema::dropIfExists('consultant_mandays_detail');
        Schema::dropIfExists('customer_mandays_detail');
        Schema::dropIfExists('ticket_team');
        Schema::dropIfExists('ticket_account');
        Schema::dropIfExists('ticket_attachment');
        Schema::dropIfExists('ticket_message');
        Schema::dropIfExists('employee_history');
        Schema::dropIfExists('auth_users');
    }
};
