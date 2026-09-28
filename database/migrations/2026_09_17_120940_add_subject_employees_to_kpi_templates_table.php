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
        Schema::table('kpi_templates', function (Blueprint $table) {
            // Explicit list of employee_ids who are the counterpart ("for who" for
            // Upward, "who fills" for Lead/Peer) for every employee matched by the
            // template's existing audience targeting. When empty, the counterpart
            // stays auto-derived from each matched employee's direct supervisor in
            // master data (unchanged legacy behavior).
            $table->json('subject_employees')->nullable()->after('target_employees');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kpi_templates', function (Blueprint $table) {
            $table->dropColumn('subject_employees');
        });
    }
};
