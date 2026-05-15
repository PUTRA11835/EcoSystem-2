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
            // 'full'          = Response + Resolution SLA (Incident, Service Request)
            // 'response_only' = Response Time saja (Change Request, Konsultasi)
            $table->enum('sla_mode', ['full', 'response_only'])
                  ->default('full')
                  ->after('sla_policy_id');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_sla', function (Blueprint $table) {
            $table->dropColumn('sla_mode');
        });
    }
};
