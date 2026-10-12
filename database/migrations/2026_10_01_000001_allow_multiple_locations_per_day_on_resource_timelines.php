<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A consultant can be on two projects on the same day (overlapping
 * assignments). The old unique(employee_id, date) forced one location per day,
 * so saving the second project silently overwrote the first one on the overlap.
 * Uniqueness now also includes the location: one row per (consultant, day,
 * project).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resource_timelines', function (Blueprint $table) {
            // The FK on employee_id needs an index; add a plain one before the
            // old unique index (which MySQL was using for it) is dropped.
            $table->index('employee_id', 'resource_timelines_employee_id_idx');
        });

        Schema::table('resource_timelines', function (Blueprint $table) {
            $table->dropUnique(['employee_id', 'date']);
            $table->unique(['employee_id', 'date', 'location'], 'resource_timelines_emp_date_loc_unique');
        });
    }

    public function down(): void
    {
        // Keep one row per (employee, date) before restoring the old constraint.
        \DB::statement('DELETE rt1 FROM resource_timelines rt1
            INNER JOIN resource_timelines rt2
              ON rt1.employee_id = rt2.employee_id AND rt1.date = rt2.date AND rt1.id > rt2.id');

        Schema::table('resource_timelines', function (Blueprint $table) {
            $table->dropUnique('resource_timelines_emp_date_loc_unique');
            $table->unique(['employee_id', 'date']);
        });

        Schema::table('resource_timelines', function (Blueprint $table) {
            $table->dropIndex('resource_timelines_employee_id_idx');
        });
    }
};
