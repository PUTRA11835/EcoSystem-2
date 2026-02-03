<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('activity_employee', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_activity_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('role')->nullable(); // Role in this activity (e.g., Lead, Member)
            $table->date('assigned_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('project_activity_id')->references('id')->on('project_activities')->onDelete('cascade');
            $table->foreign('employee_id')->references('employee_id')->on('employee')->onDelete('cascade');

            // Unique constraint - one employee can only be assigned once per activity
            $table->unique(['project_activity_id', 'employee_id'], 'activity_employee_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_employee');
    }
};
