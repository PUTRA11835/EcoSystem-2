<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ============================================================================
 * DELIVERY PLANNING TABLES - SIMPLIFIED STRUCTURE
 * ============================================================================
 *
 * Struktur hierarki:
 *
 * PROJECT
 *    └── DELIVERY PHASES (langsung per project, tanpa template global)
 *           └── DELIVERY GROUPS (nested unlimited via parent_id)
 *                  ├── DELIVERY STAGES (optional)
 *                  │      └── DELIVERY ACTIVITIES (parent_type='stage')
 *                  │
 *                  └── DELIVERY ACTIVITIES (parent_type='group') [LANGSUNG!]
 *
 * Fitur:
 * - Phase dibuat langsung per project (tidak ada template/reuse)
 * - Sub-group unlimited level dengan parent_id
 * - Activity bisa langsung di Group tanpa Stage
 * - Employee assignment per activity
 *
 * @date 2026-02-04
 */
return new class extends Migration
{
    public function up(): void
    {
        // ====================================================================
        // 1. DELIVERY PHASES - Langsung per Project (tanpa template)
        // ====================================================================
        Schema::create('delivery_phases', function (Blueprint $table) {
            $table->id();

            // Direct link ke project
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();

            // Basic Info
            $table->string('name');
            $table->string('code', 20)->nullable();
            $table->text('description')->nullable();
            $table->integer('order_sequence')->default(0);

            // Dates - Planned
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();

            // Dates - Actual
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();

            // Progress
            $table->decimal('weight', 5, 2)->default(0);
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'delayed', 'on_hold'])
                  ->default('not_started');

            // Display
            $table->string('color', 7)->default('#3B82F6');
            $table->string('icon', 50)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['project_id', 'order_sequence'], 'idx_phase_order');
            $table->index(['project_id', 'status'], 'idx_phase_status');
        });

        // ====================================================================
        // 2. DELIVERY GROUPS - Nested Unlimited via parent_id
        // ====================================================================
        Schema::create('delivery_groups', function (Blueprint $table) {
            $table->id();

            // Parent references
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('phase_id')->constrained('delivery_phases')->cascadeOnDelete();

            // Self-reference untuk sub-groups
            $table->foreignId('parent_id')->nullable()
                  ->constrained('delivery_groups')->cascadeOnDelete();

            // Basic Info
            $table->string('name');
            $table->string('code', 30)->nullable();
            $table->text('description')->nullable();

            // Hierarchy
            $table->integer('level')->default(0);
            $table->integer('order_sequence')->default(0);
            $table->string('path', 500)->nullable();

            // Dates - Planned
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();

            // Dates - Actual
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();

            // Progress
            $table->decimal('weight', 5, 2)->default(0);
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'delayed', 'on_hold'])
                  ->default('not_started');

            // Display
            $table->string('color', 7)->nullable();
            $table->string('icon', 50)->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('path', 'idx_group_path');
            $table->index(['project_id', 'phase_id', 'parent_id'], 'idx_group_hierarchy');
            $table->index(['project_id', 'level', 'order_sequence'], 'idx_group_level');
            $table->index(['project_id', 'status'], 'idx_group_status');
        });

        // ====================================================================
        // 3. DELIVERY STAGES - Optional (Activity bisa langsung ke Group)
        // ====================================================================
        Schema::create('delivery_stages', function (Blueprint $table) {
            $table->id();

            // Parent references
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('delivery_groups')->cascadeOnDelete();

            // Basic Info
            $table->string('name');
            $table->string('code', 30)->nullable();
            $table->text('description')->nullable();
            $table->integer('order_sequence')->default(0);

            // Dates - Planned
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();

            // Dates - Actual
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();

            // Progress
            $table->decimal('weight', 5, 2)->default(0);
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'delayed', 'on_hold'])
                  ->default('not_started');

            // Display
            $table->string('color', 7)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['group_id', 'order_sequence'], 'idx_stage_order');
            $table->index(['project_id', 'status'], 'idx_stage_status');
        });

        // ====================================================================
        // 4. DELIVERY ACTIVITIES - Flexible Parent (Stage atau Group)
        // ====================================================================
        Schema::create('delivery_activities', function (Blueprint $table) {
            $table->id();

            // Project & Phase reference
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('phase_id')->constrained('delivery_phases')->cascadeOnDelete();

            // Flexible Parent
            $table->enum('parent_type', ['stage', 'group'])->default('stage');
            $table->foreignId('stage_id')->nullable()
                  ->constrained('delivery_stages')->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()
                  ->constrained('delivery_groups')->cascadeOnDelete();

            // Basic Info
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->text('description')->nullable();
            $table->integer('order_sequence')->default(0);

            // Dates - Planned
            $table->date('planned_start_date')->nullable();
            $table->date('planned_end_date')->nullable();

            // Dates - Actual
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();

            // Progress
            $table->decimal('weight', 5, 2)->default(0);
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->enum('status', ['not_started', 'in_progress', 'completed', 'delayed', 'on_hold', 'cancelled'])
                  ->default('not_started');

            // Notes
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['project_id', 'phase_id'], 'idx_activity_project_phase');
            $table->index(['stage_id', 'order_sequence'], 'idx_activity_stage');
            $table->index(['group_id', 'order_sequence'], 'idx_activity_group');
            $table->index(['project_id', 'status'], 'idx_activity_status');
            $table->index(['parent_type', 'stage_id', 'group_id'], 'idx_activity_parent');
        });

        // ====================================================================
        // 5. ACTIVITY EMPLOYEES - Assignment karyawan ke aktivitas
        // ====================================================================
        Schema::create('activity_employees', function (Blueprint $table) {
            $table->id();

            $table->foreignId('activity_id')->constrained('delivery_activities')->cascadeOnDelete();
            $table->unsignedBigInteger('employee_id');
            $table->foreign('employee_id')->references('employee_id')->on('employee')->cascadeOnDelete();

            // Assignment details
            $table->enum('role', ['lead', 'member', 'reviewer', 'support'])->default('member');
            $table->decimal('allocation_percentage', 5, 2)->default(100);
            $table->boolean('is_active')->default(true);
            $table->date('assigned_date')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // Unique constraint
            $table->unique(['activity_id', 'employee_id'], 'unique_activity_employee');
            $table->index(['employee_id', 'is_active'], 'idx_employee_assignments');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_employees');
        Schema::dropIfExists('delivery_activities');
        Schema::dropIfExists('delivery_stages');
        Schema::dropIfExists('delivery_groups');
        Schema::dropIfExists('delivery_phases');
    }
};
