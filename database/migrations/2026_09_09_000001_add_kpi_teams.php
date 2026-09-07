<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KPI teams — so HR can assign an employee to a team (which has a lead) or pull
 * the lead from a delivery project, instead of setting every leader by hand.
 * The resolved leader is still written to employee_basic_data.direct_supervision
 * (the single field the rest of the KPI flow reads); lead_source records where
 * it came from: manual | team | project.
 */
return new class extends Migration
{
    public function up(): void
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

    public function down(): void
    {
        Schema::table('employee_basic_data', function (Blueprint $table) {
            if (Schema::hasColumn('employee_basic_data', 'kpi_team_id')) {
                $table->dropForeign(['kpi_team_id']);
                $table->dropColumn('kpi_team_id');
            }
            if (Schema::hasColumn('employee_basic_data', 'lead_source')) {
                $table->dropColumn('lead_source');
            }
        });

        Schema::dropIfExists('kpi_teams');
    }
};
