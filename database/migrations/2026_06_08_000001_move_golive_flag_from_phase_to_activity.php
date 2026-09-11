<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Moves the Go-Live marker from the phase level to the activity (planning leaf) level.
 *
 * - Adds `is_golive` to delivery_project_planning: the single activity flagged as the
 *   project's go-live milestone. The project's Go Live Estimated date is derived from
 *   this activity's planned start_date.
 * - Drops the now-unused `is_golive_phase` from delivery_project_phases.
 *
 * Both directions are reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_project_planning', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_project_planning', 'is_golive')) {
                $table->boolean('is_golive')->default(false)->after('is_group');
            }
        });

        Schema::table('delivery_project_phases', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_project_phases', 'is_golive_phase')) {
                $table->dropColumn('is_golive_phase');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_project_phases', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_project_phases', 'is_golive_phase')) {
                $table->boolean('is_golive_phase')->default(false);
            }
        });

        Schema::table('delivery_project_planning', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_project_planning', 'is_golive')) {
                $table->dropColumn('is_golive');
            }
        });
    }
};
