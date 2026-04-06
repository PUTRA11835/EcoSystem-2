<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_role_assignment', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('role_id');
            $table->timestamps();

            $table->primary(['employee_id', 'role_id']);

            $table->foreign('employee_id')
                  ->references('employee_id')
                  ->on('employee')
                  ->onDelete('cascade');

            $table->foreign('role_id')
                  ->references('id')
                  ->on('employee_role')
                  ->onDelete('cascade');
        });

        // Migrate existing single role_id data into pivot table
        DB::statement("
            INSERT IGNORE INTO employee_role_assignment (employee_id, role_id, created_at, updated_at)
            SELECT employee_id, role_id, NOW(), NOW()
            FROM employee
            WHERE role_id IS NOT NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_role_assignment');
    }
};
