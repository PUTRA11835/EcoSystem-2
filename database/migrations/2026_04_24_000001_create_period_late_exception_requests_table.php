<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_late_exception_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('period_id')
                  ->constrained('reporting_periods')
                  ->cascadeOnDelete();

            $table->unsignedBigInteger('employee_id');
            $table->foreign('employee_id')
                  ->references('employee_id')
                  ->on('employee')
                  ->cascadeOnDelete();

            // Derived from employee's role at time of request; stored for clarity
            $table->enum('domain', ['project', 'support']);

            // Employee's reason for requesting late access
            $table->text('notes')->nullable();

            // 2-level approval flow
            $table->enum('status', ['pending_head', 'pending_rpmo', 'approved', 'rejected'])
                  ->default('pending_head');

            // Level 1: Head approval
            $table->unsignedBigInteger('head_id')->nullable();
            $table->foreign('head_id')
                  ->references('employee_id')
                  ->on('employee')
                  ->restrictOnDelete();
            $table->timestamp('head_approved_at')->nullable();
            $table->text('head_notes')->nullable();

            // Level 2: RPMO approval
            $table->unsignedBigInteger('rpmo_id')->nullable();
            $table->foreign('rpmo_id')
                  ->references('employee_id')
                  ->on('employee')
                  ->restrictOnDelete();
            $table->timestamp('rpmo_approved_at')->nullable();
            $table->text('rpmo_notes')->nullable();

            // Rejection (either level can reject)
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->foreign('rejected_by')
                  ->references('employee_id')
                  ->on('employee')
                  ->restrictOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_notes')->nullable();

            $table->timestamps();

            // One active request per employee per period
            $table->unique(['period_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_late_exception_requests');
    }
};
