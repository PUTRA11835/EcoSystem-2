<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KPI Templates — audience targeting.
 *
 * The old "Bulk Assignment" modal on the KPI Evaluation dashboard asked HR who
 * a template was for (roles / positions) every time it was used. That choice
 * belongs to the template itself: a template now declares which roles and
 * positions it applies to, and the dashboard simply offers each employee the
 * templates that match them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('kpi_templates', 'target_roles')) {
                $table->json('target_roles')->nullable()->after('target_type');
            }
            if (!Schema::hasColumn('kpi_templates', 'target_positions')) {
                $table->json('target_positions')->nullable()->after('target_roles');
            }
        });
    }

    public function down(): void
    {
        Schema::table('kpi_templates', function (Blueprint $table) {
            $table->dropColumn(['target_roles', 'target_positions']);
        });
    }
};
