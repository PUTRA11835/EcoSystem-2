<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ticket_sla', function (Blueprint $table) {
            if (!Schema::hasColumn('ticket_sla', 'ball_holder')) {
                $table->enum('ball_holder', ['helpdesk', 'customer', 'sap'])
                      ->default('helpdesk')
                      ->after('resolution_status');
            }

            if (!Schema::hasColumn('ticket_sla', 'sla_paused_at')) {
                $table->timestamp('sla_paused_at')
                      ->nullable()
                      ->after('ball_holder');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_sla', function (Blueprint $table) {
            $table->dropColumn(
                array_filter(['ball_holder', 'sla_paused_at'], fn($col) => Schema::hasColumn('ticket_sla', $col))
            );
        });
    }
};
