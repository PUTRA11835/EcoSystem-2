<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Drop the unique constraint on (delivery_projects_id, employee_id)
     * so that one employee can hold multiple roles in the same project.
     *
     * MySQL refuses to drop an index that is the sole index backing a FK.
     * Steps: add a standalone index on delivery_projects_id first, then
     * drop the unique composite index safely.
     */
    public function up(): void
    {
        Schema::table('delivery_project_employee', function (Blueprint $table) {
            // Add a plain index on delivery_projects_id so MySQL still has
            // an index to support the FK after we drop the composite unique.
            $table->index('delivery_projects_id', 'dpe_proj_id_idx');
        });

        Schema::table('delivery_project_employee', function (Blueprint $table) {
            $table->dropUnique('delivery_proj_emp_unique');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_project_employee', function (Blueprint $table) {
            $table->unique(
                ['delivery_projects_id', 'employee_id'],
                'delivery_proj_emp_unique'
            );
        });

        Schema::table('delivery_project_employee', function (Blueprint $table) {
            $table->dropIndex('dpe_proj_id_idx');
        });
    }
};
