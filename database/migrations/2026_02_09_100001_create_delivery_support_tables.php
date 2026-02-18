<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ============================================================================
 * DELIVERY SUPPORT TABLES
 * ============================================================================
 *
 * Tables created:
 * - delivery_list (base table)
 * - delivery_support
 * - delivery_support_phases
 * - delivery_support_view_configurations
 * - delivery_support_planning
 * - delivery_support_activities
 * - delivery_support_activity_stages
 *
 * @date 2026-02-09
 */
return new class extends Migration
{
    public function up(): void
    {
        // ====================================================================
        // 2. DELIVERY SUPPORT TABLE
        // ====================================================================
        if (!Schema::hasTable('delivery_support')) {
            Schema::create('delivery_support', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_delivery_list')->nullable();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->unsignedBigInteger('ticket_id')->unique()->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->date('resolution_estimated')->nullable();
                $table->decimal('calculated_progress', 5, 2)->default(0);
                $table->string('name')->nullable();
                $table->unsignedBigInteger('delivery_owner_id')->nullable();
                $table->unsignedBigInteger('support_manager_id')->nullable();
                $table->string('support_method')->nullable();
                $table->integer('total_mandays')->nullable();
                $table->unsignedBigInteger('created_by_id')->nullable();
                $table->date('approval_date')->nullable();
                $table->string('approval_name')->nullable();
                $table->timestamps();

                $table->foreign('id_delivery_list')->references('id')->on('delivery_list')->onDelete('set null');
                $table->foreign('client_id')->references('customer_id')->on('customer')->onDelete('set null');
                $table->foreign('ticket_id')->references('ticket_id')->on('ticket')->onDelete('set null');
                $table->foreign('delivery_owner_id')->references('employee_id')->on('employee')->onDelete('set null');
                $table->foreign('support_manager_id')->references('employee_id')->on('employee')->onDelete('set null');
                $table->foreign('created_by_id')->references('employee_id')->on('employee')->onDelete('set null');

                $table->index('client_id');
                $table->index('ticket_id');
                $table->index('id_delivery_list');
            });
        }

        // ====================================================================
        // 3. DELIVERY SUPPORT PHASES TABLE
        // ====================================================================
        if (!Schema::hasTable('delivery_support_phases')) {
            Schema::create('delivery_support_phases', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('delivery_support_id');
                $table->string('name');
                $table->text('description')->nullable();
                $table->integer('order_sequence')->default(0);
                $table->string('color')->default('#3B82F6');
                $table->decimal('weight', 5, 2)->default(0);
                $table->boolean('is_resolution_phase')->default(false);
                $table->boolean('is_visible')->default(true);
                $table->json('custom_settings')->nullable();
                $table->boolean('is_system_default')->default(false);
                $table->boolean('is_optional')->default(false);
                $table->enum('orientation', ['vertical', 'horizontal'])->default('vertical');
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('parent_phase_id')->nullable();
                $table->json('settings')->nullable();
                $table->timestamps();

                $table->index('delivery_support_id');
                $table->index('is_system_default');
                $table->index('is_active');

                $table->foreign('delivery_support_id')
                    ->references('id')
                    ->on('delivery_support')
                    ->onDelete('cascade');

                $table->foreign('parent_phase_id')
                    ->references('id')
                    ->on('delivery_support_phases')
                    ->onDelete('set null');
            });
        }

        // ====================================================================
        // 4. DELIVERY SUPPORT VIEW CONFIGURATIONS TABLE
        // ====================================================================
        if (!Schema::hasTable('delivery_support_view_configurations')) {
            Schema::create('delivery_support_view_configurations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('delivery_support_id')->unique();
                $table->enum('default_view', ['table', 'gantt', 'scurve'])->default('table');
                $table->json('gantt_settings')->nullable();
                $table->json('table_settings')->nullable();
                $table->json('column_visibility')->nullable();
                $table->timestamps();

                $table->foreign('delivery_support_id')
                    ->references('id')
                    ->on('delivery_support')
                    ->onDelete('cascade');
            });
        }

        // ====================================================================
        // 5. DELIVERY SUPPORT ACTIVITY STAGES TABLE (must be created before planning)
        // ====================================================================
        if (!Schema::hasTable('delivery_support_activity_stages')) {
            Schema::create('delivery_support_activity_stages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('planning_id')->nullable();
                $table->unsignedBigInteger('activity_id')->nullable();
                $table->string('name');
                $table->text('description')->nullable();
                $table->date('planned_start_date')->nullable();
                $table->date('planned_end_date')->nullable();
                $table->date('actual_start_date')->nullable();
                $table->date('actual_end_date')->nullable();
                $table->decimal('progress', 5, 2)->default(0);
                $table->enum('status', ['not_started', 'in_progress', 'completed', 'delayed', 'on_hold'])->default('not_started');
                $table->decimal('weight', 5, 2)->default(0);
                $table->json('custom_fields')->nullable();
                $table->integer('order_sequence')->default(0);
                $table->string('color')->nullable();
                $table->timestamps();
                $table->softDeletes();

                // Indexes will be added after planning table is created
            });
        }

        // ====================================================================
        // 6. DELIVERY SUPPORT ACTIVITIES TABLE
        // ====================================================================
        if (!Schema::hasTable('delivery_support_activities')) {
            Schema::create('delivery_support_activities', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('delivery_support_id');
                $table->unsignedBigInteger('delivery_support_phase_id')->nullable();
                $table->unsignedBigInteger('stage_id')->nullable();
                $table->string('name');
                $table->text('description')->nullable();
                $table->integer('order_sequence')->default(0);
                $table->string('module')->nullable();
                $table->boolean('new_issue')->default(false);
                $table->string('object')->nullable();
                $table->string('incident_type')->nullable();
                $table->string('complexity')->nullable();
                $table->text('deliverable')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->date('actual_start_date')->nullable();
                $table->date('actual_end_date')->nullable();
                $table->string('status')->default('not_started');
                $table->decimal('progress_percentage', 5, 2)->default(0);
                $table->decimal('weight', 5, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('delivery_support_id');
                $table->index('delivery_support_phase_id');
                $table->index('stage_id');

                $table->foreign('delivery_support_id')
                    ->references('id')
                    ->on('delivery_support')
                    ->onDelete('cascade');

                $table->foreign('delivery_support_phase_id')
                    ->references('id')
                    ->on('delivery_support_phases')
                    ->onDelete('set null');

                $table->foreign('stage_id')
                    ->references('id')
                    ->on('delivery_support_activity_stages')
                    ->onDelete('set null');
            });
        }

        // ====================================================================
        // 7. DELIVERY SUPPORT PLANNING TABLE
        // ====================================================================
        if (!Schema::hasTable('delivery_support_planning')) {
            Schema::create('delivery_support_planning', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('delivery_support_id');
                $table->unsignedBigInteger('phase_id')->nullable();
                $table->unsignedBigInteger('parent_id')->nullable();
                $table->unsignedBigInteger('stage_id')->nullable();
                $table->unsignedBigInteger('activity_id')->nullable();
                $table->string('name')->nullable();
                $table->string('group_name')->nullable();
                $table->boolean('is_group')->default(false);
                $table->integer('level')->default(0);
                $table->integer('order_sequence')->default(0);
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->date('actual_start_date')->nullable();
                $table->date('actual_end_date')->nullable();
                $table->decimal('weight', 5, 2)->default(0);
                $table->string('status')->default('not_started');
                $table->decimal('progress_percentage', 5, 2)->default(0);
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index(['delivery_support_id', 'is_group']);
                $table->index(['delivery_support_id', 'parent_id']);
                $table->index(['delivery_support_id', 'level']);
                $table->index('phase_id');
                $table->index('stage_id');

                $table->foreign('delivery_support_id')
                    ->references('id')
                    ->on('delivery_support')
                    ->onDelete('cascade');

                $table->foreign('phase_id')
                    ->references('id')
                    ->on('delivery_support_phases')
                    ->onDelete('set null');

                $table->foreign('parent_id')
                    ->references('id')
                    ->on('delivery_support_planning')
                    ->onDelete('cascade');

                $table->foreign('stage_id')
                    ->references('id')
                    ->on('delivery_support_activity_stages')
                    ->onDelete('set null');

                $table->foreign('activity_id')
                    ->references('id')
                    ->on('delivery_support_activities')
                    ->onDelete('set null');
            });
        }

        // Add foreign keys to activity_stages after planning is created
        // Only run if both tables exist and the indexes haven't been added yet
        if (Schema::hasTable('delivery_support_activity_stages') && Schema::hasTable('delivery_support_activities')) {
            // Use try-catch to handle duplicate index/foreign key errors gracefully
            try {
                Schema::table('delivery_support_activity_stages', function (Blueprint $table) {
                    $table->index('planning_id');
                    $table->index('activity_id');

                    $table->foreign('activity_id', 'fk_support_stages_activity')
                        ->references('id')
                        ->on('delivery_support_activities')
                        ->onDelete('cascade');
                });
            } catch (\Exception $e) {
                // Index or foreign key already exists, skip silently
            }
        }

        // ====================================================================
        // 8. DELIVERY SUPPORT ACTIVITY EMPLOYEE TABLE (Team assignment)
        // ====================================================================
        if (!Schema::hasTable('delivery_support_activity_employee')) {
            Schema::create('delivery_support_activity_employee', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('delivery_support_activity_id');
                $table->unsignedBigInteger('employee_id');
                $table->enum('role', ['lead', 'member', 'reviewer', 'support'])->default('member');
                $table->decimal('allocation_percentage', 5, 2)->default(100);
                $table->boolean('is_active')->default(true);
                $table->date('assigned_date')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['delivery_support_activity_id', 'employee_id'], 'unique_support_activity_employee');
                $table->index(['employee_id', 'is_active'], 'idx_support_employee_assignments');

                $table->foreign('delivery_support_activity_id', 'fk_support_act_emp_activity')
                    ->references('id')
                    ->on('delivery_support_activities')
                    ->onDelete('cascade');
            });
        }

        // ====================================================================
        // 9. DELIVERY SUPPORT DOCUMENTS TABLE
        // ====================================================================
        if (!Schema::hasTable('delivery_support_documents')) {
            Schema::create('delivery_support_documents', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('delivery_support_id');
                $table->string('document_name');
                $table->string('link_document')->nullable();
                $table->enum('document_type', ['BAST/BAPP', 'Service Agreement', 'Problem Report', 'Solution Document', 'Test Results', 'Others'])->default('Others');
                $table->timestamps();

                $table->index('delivery_support_id');

                $table->foreign('delivery_support_id')
                    ->references('id')
                    ->on('delivery_support')
                    ->onDelete('cascade');
            });
        }

        // ====================================================================
        // 10. DELIVERY SUPPORT UPDATES TABLE
        // ====================================================================
        if (!Schema::hasTable('delivery_support_updates')) {
            Schema::create('delivery_support_updates', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('delivery_support_id');
                $table->text('highlight_issue')->nullable();
                $table->text('action')->nullable();
                $table->date('due_date')->nullable();
                $table->string('status')->nullable();
                $table->string('complexity')->nullable();
                $table->text('deliverable')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->index('delivery_support_id');

                $table->foreign('delivery_support_id')
                    ->references('id')
                    ->on('delivery_support')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_support_updates');
        Schema::dropIfExists('delivery_support_documents');
        Schema::dropIfExists('delivery_support_activity_employee');

        // Remove foreign keys first if table exists
        if (Schema::hasTable('delivery_support_activity_stages')) {
            try {
                Schema::table('delivery_support_activity_stages', function (Blueprint $table) {
                    $table->dropForeign('fk_support_stages_activity');
                });
            } catch (\Exception $e) {
                // Foreign key doesn't exist, skip silently
            }
        }

        Schema::dropIfExists('delivery_support_planning');
        Schema::dropIfExists('delivery_support_activities');
        Schema::dropIfExists('delivery_support_activity_stages');
        Schema::dropIfExists('delivery_support_view_configurations');
        Schema::dropIfExists('delivery_support_phases');
        Schema::dropIfExists('delivery_support');
        Schema::dropIfExists('delivery_list');
    }
};
