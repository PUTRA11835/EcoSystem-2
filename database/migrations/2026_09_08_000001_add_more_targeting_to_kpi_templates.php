<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Broaden KPI template targeting so "Who is this template for?" can mix
 * criteria: roles, positions, individual employees, and delivery projects.
 * Any match makes the template eligible; all-empty = offered to everyone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('kpi_templates', 'target_employees')) {
                $table->json('target_employees')->nullable()->after('target_positions');
            }
            if (!Schema::hasColumn('kpi_templates', 'target_projects')) {
                $table->json('target_projects')->nullable()->after('target_employees');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kpi_templates', function (Blueprint $table) {
            foreach (['target_employees', 'target_projects'] as $col) {
                if (Schema::hasColumn('kpi_templates', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
