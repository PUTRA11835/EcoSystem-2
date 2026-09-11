<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops KPI teams. The manual "assign an employee to a team (which has a
 * lead)" flow was replaced by an automatically derived leader — project
 * manager → employee_basic_data.direct_supervision → none; see
 * KpiController::resolveReportsTo(). Nothing reads kpi_teams, kpi_team_id,
 * or lead_source any more, so they're dropped rather than left as dead
 * weight.
 *
 * Reverses 2026_09_09_000001_add_kpi_teams.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('employee_basic_data', 'kpi_team_id')) {
            Schema::table('employee_basic_data', function (Blueprint $table) {
                $table->dropForeign(['kpi_team_id']);
                $table->dropColumn('kpi_team_id');
            });
        }
        if (Schema::hasColumn('employee_basic_data', 'lead_source')) {
            Schema::table('employee_basic_data', function (Blueprint $table) {
                $table->dropColumn('lead_source');
            });
        }

        Schema::dropIfExists('kpi_teams');
    }

    public function down(): void
    {
        if (!Schema::hasTable('kpi_teams')) {
            Schema::create('kpi_teams', function (Blueprint $table) {
                $table->id();
                $table->string('name', 150);
                $table->unsignedBigInteger('lead_employee_id')->nullable();
                $table->string('description', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('lead_employee_id')->references('employee_id')->on('employee')->nullOnDelete();
                $table->index('lead_employee_id');
            });
        }

        Schema::table('employee_basic_data', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_basic_data', 'kpi_team_id')) {
                $table->unsignedBigInteger('kpi_team_id')->nullable()->after('direct_supervision');
                $table->foreign('kpi_team_id')->references('id')->on('kpi_teams')->nullOnDelete();
            }
            if (!Schema::hasColumn('employee_basic_data', 'lead_source')) {
                $table->string('lead_source', 20)->nullable()->after('kpi_team_id')
                    ->comment('manual | team | project');
            }
        });
    }
};
