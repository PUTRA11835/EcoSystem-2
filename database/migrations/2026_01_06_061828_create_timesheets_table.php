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
        Schema::create('timesheets', function (Blueprint $table) {
            $table->id();
            
            // UPDATE: Gunakan employee_id sebagai foreign key
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedBigInteger('ticket_id')->nullable();
            
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('duration_minutes')->default(0);
            $table->text('description');
            $table->enum('activity_type', ['development', 'meeting', 'documentation', 'testing', 'support', 'training', 'other'])->default('development');
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft');
            $table->text('rejection_reason')->nullable();
            
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->boolean('is_billable')->default(true);
            
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('employee_id')->references('employee_id')->on('employee')->onDelete('cascade');
            $table->foreign('approved_by')->references('employee_id')->on('employee')->onDelete('set null');
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('set null');
            $table->foreign('ticket_id')->references('ticket_id')->on('ticket')->onDelete('set null');
            
            // Indexes
            $table->index('employee_id');
            $table->index('date');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timesheets');
    }
};