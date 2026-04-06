<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --------------------------------------------------------------------
        // EMPLOYEE ROLE (PIVOT/ASSIGNMENT)
        // --------------------------------------------------------------------
        if (!Schema::hasTable('employee_role')) {
            Schema::create('employee_role', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('description')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
            });
        }

        Schema::create('employee', function (Blueprint $table) {
            $table->id('employee_id');
            $table->foreignId('role_id')->constrained('employee_role','id')->onDelete('cascade');
            $table->string('eci')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee');
        Schema::dropIfExists('employee_role');
    }
};
