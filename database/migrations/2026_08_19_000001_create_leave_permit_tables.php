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
        // 1. Leave & Permit Types (Master Data Table)
        Schema::create('leave_permit_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 150);
            $table->enum('category', ['leave', 'permit'])->default('leave');
            $table->decimal('default_quota', 8, 2)->default(0);
            $table->string('min_service_period', 50)->nullable(); // e.g., '12 bln'
            $table->boolean('is_paid')->default(true); // Ya / Tidak (potong gaji)
            $table->enum('gender_target', ['all', 'P', 'L'])->default('all'); // all, Perempuan, Laki-laki
            $table->boolean('requires_attachment')->default(false);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Leave & Permit Quotas (Global or Per-Employee)
        Schema::create('leave_permit_quotas', function (Blueprint $table) {
            $table->id();
            $table->integer('year');
            $table->foreignId('leave_permit_type_id')->constrained('leave_permit_types')->onDelete('cascade');
            // employee_id is nullable: null means quota is set globally for all employees
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->foreign('employee_id')->references('employee_id')->on('employee')->onDelete('cascade');
            $table->decimal('quota_amount', 8, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['year', 'leave_permit_type_id', 'employee_id'], 'year_type_emp_unique');
        });

        // 3. Leave & Permit Applications
        Schema::create('leave_permit_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_no', 50)->unique();
            $table->unsignedBigInteger('employee_id');
            $table->foreign('employee_id')->references('employee_id')->on('employee')->onDelete('cascade');
            $table->foreignId('leave_permit_type_id')->constrained('leave_permit_types')->onDelete('cascade');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 8, 2);
            $table->text('reason');
            $table->string('attachment_path')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'revision'])->default('pending');
            $table->text('revision_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->foreign('reviewed_by')->references('employee_id')->on('employee')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        // 4. Leave & Permit Action Logs
        Schema::create('leave_permit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('leave_permit_applications')->onDelete('cascade');
            $table->string('action', 50); // e.g. submitted, approved, rejected, revision_requested, resubmitted
            $table->unsignedBigInteger('performed_by')->nullable();
            $table->foreign('performed_by')->references('employee_id')->on('employee')->onDelete('set null');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_permit_logs');
        Schema::dropIfExists('leave_permit_applications');
        Schema::dropIfExists('leave_permit_quotas');
        Schema::dropIfExists('leave_permit_types');
    }
};
