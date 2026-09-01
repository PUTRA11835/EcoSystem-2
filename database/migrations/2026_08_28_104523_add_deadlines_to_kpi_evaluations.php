<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_evaluations', function (Blueprint $table) {
            // Self-assessment deadline (editable by HR, e.g. end of month)
            $table->date('self_deadline')->nullable()->after('self_assessed_at')
                  ->comment('Deadline for employee to submit self-assessment');

            // Supervisor scoring deadline (editable by HR)
            $table->date('supervisor_deadline')->nullable()->after('reviewed_at')
                  ->comment('Deadline for supervisor to submit scoring');
        });
    }

    public function down(): void
    {
        Schema::table('kpi_evaluations', function (Blueprint $table) {
            $table->dropColumn(['self_deadline', 'supervisor_deadline']);
        });
    }
};
