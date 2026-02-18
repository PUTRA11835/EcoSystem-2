<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add ticket_id to delivery_support_activities table
 * This allows tracking which ticket an activity was created from
 * and supports multiple tickets being assigned to one delivery support
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_support_activities', function (Blueprint $table) {
            // Add ticket_id column after delivery_support_phase_id
            $table->unsignedBigInteger('ticket_id')->nullable()->after('delivery_support_phase_id');

            // Add foreign key
            $table->foreign('ticket_id')
                ->references('ticket_id')
                ->on('ticket')
                ->onDelete('set null');

            // Add index for performance
            $table->index('ticket_id');
        });

        // Also remove the UNIQUE constraint from delivery_support.ticket_id
        // to allow the same ticket to potentially be referenced (primary ticket)
        // while also being linked through activities
        // Note: We keep the column but remove uniqueness
        Schema::table('delivery_support', function (Blueprint $table) {
            $table->dropUnique(['ticket_id']);
        });
    }

    public function down(): void
    {
        Schema::table('delivery_support_activities', function (Blueprint $table) {
            $table->dropForeign(['ticket_id']);
            $table->dropIndex(['ticket_id']);
            $table->dropColumn('ticket_id');
        });

        Schema::table('delivery_support', function (Blueprint $table) {
            $table->unique('ticket_id');
        });
    }
};
