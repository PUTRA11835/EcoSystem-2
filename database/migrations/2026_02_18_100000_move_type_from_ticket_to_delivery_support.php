<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ============================================================================
 * MOVE TYPE FROM TICKET TO DELIVERY SUPPORT
 * ============================================================================
 *
 * Changes:
 * 1. Remove ticket_id from delivery_support table
 * 2. Remove type from ticket table
 * 3. Add type to delivery_support table (AMS, MO, ATS, Project, Internal)
 *
 * The helpdesk assigns tickets to delivery_support, which determines the type.
 *
 * @date 2026-02-18
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Add type column to delivery_support table
        Schema::table('delivery_support', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_support', 'type')) {
                $table->enum('type', ['AMS', 'MO', 'ATS', 'Project', 'Internal'])
                    ->nullable()
                    ->after('name');
            }
        });

        // 2. Remove ticket_id from delivery_support table
        // First, drop foreign key if exists, then drop column
        if (Schema::hasColumn('delivery_support', 'ticket_id')) {
            Schema::table('delivery_support', function (Blueprint $table) {
                // Drop foreign key
                try {
                    $table->dropForeign(['ticket_id']);
                } catch (\Exception $e) {
                    // Foreign key might not exist
                }

                // Drop index
                try {
                    $table->dropIndex(['ticket_id']);
                } catch (\Exception $e) {
                    // Index might not exist
                }
            });

            Schema::table('delivery_support', function (Blueprint $table) {
                $table->dropColumn('ticket_id');
            });
        }

        // 3. Remove type from ticket table
        if (Schema::hasColumn('ticket', 'type')) {
            Schema::table('ticket', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }

    public function down(): void
    {
        // 1. Add type back to ticket table
        if (!Schema::hasColumn('ticket', 'type')) {
            Schema::table('ticket', function (Blueprint $table) {
                $table->string('type')->nullable()->after('man_days');
            });
        }

        // 2. Add ticket_id back to delivery_support table
        if (!Schema::hasColumn('delivery_support', 'ticket_id')) {
            Schema::table('delivery_support', function (Blueprint $table) {
                $table->unsignedBigInteger('ticket_id')->unique()->nullable()->after('client_id');

                $table->foreign('ticket_id')
                    ->references('ticket_id')
                    ->on('ticket')
                    ->onDelete('set null');

                $table->index('ticket_id');
            });
        }

        // 3. Remove type from delivery_support table
        if (Schema::hasColumn('delivery_support', 'type')) {
            Schema::table('delivery_support', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }
    }
};
