<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KPI Module Enhancements:
 *   - Adds target_type ('self', 'supervisor', 'peer') to kpi_templates
 *   - Adds star_rating (1-5) and actual_achievement to kpi_evaluation_details
 *   - Adds general_notes to kpi_evaluations
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('kpi_templates', 'target_type')) {
            Schema::table('kpi_templates', function (Blueprint $table) {
                $table->enum('target_type', ['self', 'supervisor', 'peer'])->default('supervisor')
                      ->after('period_type')
                      ->comment('self = Mandiri, supervisor = Atasan Langsung, peer = Rekan Kerja');
            });
        }

        if (!Schema::hasColumn('kpi_evaluations', 'general_notes')) {
            Schema::table('kpi_evaluations', function (Blueprint $table) {
                $table->text('general_notes')->nullable()->after('overall_score');
            });
        }

        if (!Schema::hasColumn('kpi_evaluation_details', 'star_rating')) {
            Schema::table('kpi_evaluation_details', function (Blueprint $table) {
                $table->unsignedTinyInteger('star_rating')->nullable()->after('supervisor_score')->comment('1 to 5 star rating');
                $table->string('actual_achievement', 255)->nullable()->after('self_achievement')->comment('Text or numeric actual achievement');
            });
        }
    }

    public function down(): void
    {
        Schema::table('kpi_templates', function (Blueprint $table) {
            $table->dropColumn('target_type');
        });
        Schema::table('kpi_evaluations', function (Blueprint $table) {
            $table->dropColumn('general_notes');
        });
        Schema::table('kpi_evaluation_details', function (Blueprint $table) {
            $table->dropColumn(['star_rating', 'actual_achievement']);
        });
    }
};
