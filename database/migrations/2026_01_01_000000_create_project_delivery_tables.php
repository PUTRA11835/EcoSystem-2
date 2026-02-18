<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for Project Delivery module
     *
     * This migration creates all necessary tables for the Project Planning/Delivery module
     * to be integrated into the ECOSYSTEM.
     *
     * Prerequisites:
     * - ECOSYSTEM must have 'customer' table with 'customer_id' column
     * - ECOSYSTEM must have 'employee' table with 'employee_id' column
     * - ECOSYSTEM must have 'users' table with 'id' column
     */
    public function up(): void
    {
        // ====================================================================
        // 1. DELIVERY LIST TABLE (Base table for both project and support)
        // ====================================================================
        if (!Schema::hasTable('delivery_list')) {
            Schema::create('delivery_list', function (Blueprint $table) {
                $table->id();
                $table->timestamps();
            });
        }
        
        // =====================================================================
        // PROJECTS TABLE
        // =====================================================================
        Schema::create('delivery_projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_delivery_list')->nullable();
            // Reference to ECOSYSTEM's customer table (customer_id as PK)
            $table->unsignedBigInteger('client_id');
            $table->foreign('id_delivery_list')->references('id')->on('delivery_list')->onDelete('set null');
            $table->foreign('client_id')->references('customer_id')->on('customer')->onDelete('cascade');
            $table->string('pic')->nullable();
            $table->string('project_type')->nullable();
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('status')->default('planning');
            $table->string('phase')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('go_live_estimated')->nullable();
            $table->decimal('calculated_progress', 5, 2)->default(0);

            // Delivery Information
            $table->string('name')->nullable();
            $table->string('ae_type')->nullable();
            $table->string('ae_name')->nullable();
            $table->string('ae_phone')->nullable();
            $table->string('ae_email')->nullable();

            // Reference to ECOSYSTEM's employee table for delivery roles
            $table->unsignedBigInteger('delivery_owner_id')->nullable();
            $table->foreign('delivery_owner_id')->references('employee_id')->on('employee')->onDelete('set null');
            $table->unsignedBigInteger('delivery_manager_id')->nullable();
            $table->foreign('delivery_manager_id')->references('employee_id')->on('employee')->onDelete('set null');

            $table->string('delivery_method')->nullable();
            $table->string('warranty_period')->nullable();
            $table->integer('total_mandays')->nullable();
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->date('approval_date')->nullable();
            $table->string('approval_name')->nullable();

            // Location Information
            $table->string('location_name')->nullable();
            $table->string('location_type')->nullable();
            $table->string('location_country')->nullable();
            $table->string('location_geographical')->nullable();
            $table->string('location_region')->nullable();
            $table->string('location_city')->nullable();
            $table->string('location_street')->nullable();
            $table->date('location_valid_from')->nullable();
            $table->date('location_valid_to')->nullable();

            $table->timestamps();

            $table->index('client_id');
            $table->index('status');
            $table->index('category');
            $table->index('id_delivery_list');
            $table->index('created_by_id');

            $table->foreign('created_by_id')->references('employee_id')->on('employee')->onDelete('set null');
        });

        // =====================================================================
        // PROJECT EMPLOYEES (Many-to-Many)
        // =====================================================================
        Schema::create('delivery_project_employee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_projects_id')->constrained('delivery_projects')->onDelete('cascade');
            // Reference to ECOSYSTEM's employee table (employee_id as PK)
            $table->unsignedBigInteger('employee_id');
            $table->foreign('employee_id')->references('employee_id')->on('employee')->onDelete('cascade');
            $table->string('assignment')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->unique(
                ['delivery_projects_id', 'employee_id'], 
                'delivery_proj_emp_unique'
            );
        });

        // =====================================================================
        // PROJECT UPDATES
        // =====================================================================
        Schema::create('delivery_project_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_projects_id')->constrained('delivery_projects')->onDelete('cascade');
            $table->text('highlight_issue')->nullable();
            $table->text('action')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->nullable();
            $table->string('complexity')->nullable();
            $table->text('deliverable')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('delivery_projects_id');
        });

        // =====================================================================
        // DOCUMENTS
        // =====================================================================
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_projects_id')->constrained('delivery_projects')->onDelete('cascade');
            $table->string('document_name');
            $table->string('link_document')->nullable();
            $table->enum('document_type', ['BAST/BAPP', 'Contract', 'Justification', 'PR/PO', 'Others']);
            $table->timestamps();

            $table->index('delivery_projects_id');
        });

        // =====================================================================
        // PROJECT PHASES (One-to-Many: Project has many Phases)
        // =====================================================================
        Schema::create('delivery_project_phases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_projects_id')->nullable()->constrained('delivery_projects')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('order_sequence')->default(0);
            $table->string('color')->default('#3B82F6');
            $table->decimal('weight', 5, 2)->default(0);

            // ✅ KOLOM BARU: Phase configuration per project
            $table->boolean('is_golive_phase')->default(false);  // ✅ Baru
            $table->boolean('is_visible')->default(true);        // ✅ Baru
            $table->json('custom_settings')->nullable();         // ✅ Baru

            // Dynamic phase support
            $table->boolean('is_system_default')->default(false);
            $table->boolean('is_optional')->default(false);
            $table->enum('orientation', ['vertical', 'horizontal'])->default('vertical');
            $table->boolean('is_active')->default(true);
            $table->foreignId('parent_phase_id')->nullable()->constrained('delivery_project_phases')->onDelete('set null');
            $table->json('settings')->nullable();

            $table->timestamps();

            $table->index('delivery_projects_id');
            $table->index('is_system_default');
            $table->index('is_active');
        });

        // =====================================================================
        // PROJECT VIEW CONFIGURATIONS
        // =====================================================================
        Schema::create('delivery_project_view_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_projects_id')->constrained('delivery_projects')->onDelete('cascade')->name('dpvc_delivery_projects_id_foreign');
            $table->enum('default_view', ['table', 'gantt', 'scurve'])->default('table');
            $table->json('gantt_settings')->nullable();
            $table->json('table_settings')->nullable();
            $table->json('column_visibility')->nullable();
            $table->timestamps();

            $table->unique('delivery_projects_id');
        });

        // =====================================================================
        // PROJECT ACTIVITIES (Master/Template) - WITHOUT stage_id FK first
        // =====================================================================
        Schema::create('delivery_project_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_projects_id')->nullable()->constrained('delivery_projects')->onDelete('cascade');
            $table->foreignId('delivery_project_phase_id')->nullable()->constrained('delivery_project_phases')->onDelete('cascade');
            $table->unsignedBigInteger('stage_id')->nullable(); // FK akan ditambah nanti
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('order_sequence')->default(0);

            // Extended fields
            $table->string('module')->nullable();
            $table->boolean('new_requirement')->default(false);
            $table->string('object')->nullable();
            $table->string('receive_type')->nullable();
            $table->string('complexity')->nullable();
            $table->text('deliverable')->nullable();

            // Dates and progress
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->string('status')->default('not_started');
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->decimal('weight', 5, 2)->default(0);
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index('delivery_projects_id');
            $table->index('delivery_project_phase_id');
            $table->index('stage_id');
        });

        // =====================================================================
        // PROJECT PLANNING (Hierarchical Structure) - WITHOUT stage_id FK first
        // =====================================================================
        Schema::create('delivery_project_planning', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_projects_id')->constrained('delivery_projects')->onDelete('cascade');
            $table->foreignId('phase_id')->nullable()->constrained('delivery_project_phases')->onDelete('set null');
            $table->foreignId('parent_id')->nullable()->constrained('delivery_project_planning')->onDelete('cascade');
            $table->unsignedBigInteger('stage_id')->nullable(); // FK akan ditambah nanti
            $table->foreignId('activity_id')->nullable()->constrained('delivery_project_activities')->onDelete('set null');

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

            $table->index(['delivery_projects_id', 'is_group']);
            $table->index(['delivery_projects_id', 'parent_id']);
            $table->index(['delivery_projects_id', 'level']);
            $table->index('phase_id');
            $table->index('stage_id');
        });

        // =====================================================================
        // ACTIVITY STAGES
        // =====================================================================
        Schema::create('activity_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planning_id')->constrained('delivery_project_planning')->onDelete('cascade');
            $table->foreignId('activity_id')->nullable()->constrained('delivery_project_activities')->onDelete('set null');
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

            $table->index('planning_id');
            $table->index('activity_id');
        });

        // =====================================================================
        // ADD FOREIGN KEYS FOR CIRCULAR REFERENCES
        // =====================================================================
        Schema::table('delivery_project_activities', function (Blueprint $table) {
            $table->foreign('stage_id')->references('id')->on('activity_stages')->onDelete('set null');
        });

        Schema::table('delivery_project_planning', function (Blueprint $table) {
            $table->foreign('stage_id')->references('id')->on('activity_stages')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign keys first to avoid circular dependency issues
        Schema::table('delivery_project_activities', function (Blueprint $table) {
            $table->dropForeign(['stage_id']);
        });
        Schema::table('delivery_project_planning', function (Blueprint $table) {
            $table->dropForeign(['stage_id']);
        });

        // Drop tables in reverse order
        Schema::dropIfExists('activity_stages');
        Schema::dropIfExists('delivery_project_planning');
        Schema::dropIfExists('delivery_project_activities');
        Schema::dropIfExists('delivery_project_view_configurations');
        Schema::dropIfExists('delivery_project_phases');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('delivery_project_updates');
        Schema::dropIfExists('delivery_project_employee');
        Schema::dropIfExists('delivery_projects');
    }
};
