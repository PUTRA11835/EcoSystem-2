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
        // =====================================================================
        // PROJECTS TABLE
        // =====================================================================
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            // Reference to ECOSYSTEM's customer table (customer_id as PK)
            $table->unsignedBigInteger('client_id');
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
            $table->string('delivery_type')->nullable();
            $table->string('delivery_subtype')->nullable();
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
            $table->foreignId('created_by_id')->nullable()->constrained('users')->onDelete('set null');
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
        });

        // =====================================================================
        // PROJECT EMPLOYEES (Many-to-Many)
        // =====================================================================
        Schema::create('project_employee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            // Reference to ECOSYSTEM's employee table (employee_id as PK)
            $table->unsignedBigInteger('employee_id');
            $table->foreign('employee_id')->references('employee_id')->on('employee')->onDelete('cascade');
            $table->string('assignment')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'employee_id']);
        });

        // =====================================================================
        // PROJECT UPDATES
        // =====================================================================
        Schema::create('project_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->text('highlight_issue')->nullable();
            $table->text('action')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->nullable();
            $table->string('complexity')->nullable();
            $table->text('deliverable')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('project_id');
        });

        // =====================================================================
        // DOCUMENTS
        // =====================================================================
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('document_name');
            $table->string('link_document')->nullable();
            $table->enum('document_type', ['BAST/BAPP', 'Contract', 'Justification', 'PR/PO', 'Others']);
            $table->timestamps();

            $table->index('project_id');
        });

        // =====================================================================
        // PROJECT PHASES (Template/Master)
        // =====================================================================
        Schema::create('project_phases', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('order_sequence')->default(0);
            $table->string('color')->default('#3B82F6');
            $table->decimal('weight', 5, 2)->default(0);

            // Dynamic phase support
            $table->boolean('is_system_default')->default(false);
            $table->boolean('is_optional')->default(false);
            $table->enum('orientation', ['vertical', 'horizontal'])->default('vertical');
            $table->boolean('is_active')->default(true);
            $table->foreignId('parent_phase_id')->nullable()->constrained('project_phases')->onDelete('set null');
            $table->json('settings')->nullable();

            $table->timestamps();

            $table->index('is_system_default');
            $table->index('is_active');
        });

        // =====================================================================
        // PROJECT-PHASE RELATIONSHIP (Many-to-Many with Pivot Data)
        // =====================================================================
        Schema::create('project_project_phase', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('project_phase_id')->constrained('project_phases')->onDelete('cascade');
            $table->decimal('weight', 5, 2)->default(0);
            $table->integer('order_sequence')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->enum('orientation', ['vertical', 'horizontal'])->default('vertical');
            $table->json('custom_settings')->nullable();
            $table->boolean('is_golive_phase')->default(false);
            $table->timestamps();

            $table->unique(['project_id', 'project_phase_id']);
            $table->index('project_id');
        });

        // =====================================================================
        // PROJECT PHASE TEMPLATES
        // =====================================================================
        Schema::create('project_phase_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('phase_configuration');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        // =====================================================================
        // PROJECT VIEW CONFIGURATIONS
        // =====================================================================
        Schema::create('project_view_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->enum('default_view', ['table', 'gantt', 'scurve'])->default('table');
            $table->json('gantt_settings')->nullable();
            $table->json('table_settings')->nullable();
            $table->json('column_visibility')->nullable();
            $table->timestamps();

            $table->unique('project_id');
        });

        // =====================================================================
        // PROJECT CUSTOM ACTIVITIES
        // =====================================================================
        Schema::create('project_custom_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->string('phase')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('project_id');
        });

        // =====================================================================
        // PROJECT ACTIVITIES (Master/Template) - WITHOUT stage_id FK first
        // =====================================================================
        Schema::create('project_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('projects')->onDelete('cascade');
            $table->foreignId('project_phase_id')->nullable()->constrained('project_phases')->onDelete('cascade');
            $table->unsignedBigInteger('stage_id')->nullable(); // FK akan ditambah nanti
            $table->string('name');
            $table->text('description')->nullable();
            $table->integer('order_sequence')->default(0);

            // Extended fields
            $table->string('module')->nullable();
            $table->boolean('new_requirement')->default(false);
            $table->string('tcode')->nullable();
            $table->string('receive_type')->nullable();
            $table->string('complexity')->nullable();
            $table->string('functional_sinergi')->nullable();
            $table->string('technical_sinergi')->nullable();
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

            $table->index('project_id');
            $table->index('project_phase_id');
            $table->index('stage_id');
        });

        // =====================================================================
        // PROJECT PLANNING (Hierarchical Structure) - WITHOUT stage_id FK first
        // =====================================================================
        Schema::create('project_planning', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->onDelete('cascade');
            $table->foreignId('phase_id')->nullable()->constrained('project_phases')->onDelete('set null');
            $table->foreignId('parent_id')->nullable()->constrained('project_planning')->onDelete('cascade');
            $table->unsignedBigInteger('stage_id')->nullable(); // FK akan ditambah nanti
            $table->foreignId('activity_id')->nullable()->constrained('project_activities')->onDelete('set null');
            $table->foreignId('project_custom_activity_id')->nullable()->constrained('project_custom_activities')->onDelete('set null');

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

            $table->index(['project_id', 'parent_id']);
            $table->index(['project_id', 'is_group']);
            $table->index(['project_id', 'level']);
            $table->index('phase_id');
            $table->index('stage_id');
        });

        // =====================================================================
        // PROJECT PLANNING EXTENDED
        // =====================================================================
        Schema::create('project_planning_extended', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_planning_id')->constrained('project_planning')->onDelete('cascade');
            $table->string('module')->nullable();
            $table->boolean('new_requirement')->default(false);
            $table->string('tcode')->nullable();
            $table->string('receive_type')->nullable();
            $table->string('complexity')->nullable();
            $table->string('functional_sinergi')->nullable();
            $table->string('technical_sinergi')->nullable();
            $table->text('deliverable')->nullable();
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->timestamps();

            $table->unique('project_planning_id');
        });

        // =====================================================================
        // ACTIVITY STAGES
        // =====================================================================
        Schema::create('activity_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planning_id')->constrained('project_planning')->onDelete('cascade');
            $table->foreignId('activity_id')->nullable()->constrained('project_activities')->onDelete('set null');
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
        Schema::table('project_activities', function (Blueprint $table) {
            $table->foreign('stage_id')->references('id')->on('activity_stages')->onDelete('set null');
        });

        Schema::table('project_planning', function (Blueprint $table) {
            $table->foreign('stage_id')->references('id')->on('activity_stages')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign keys first to avoid circular dependency issues
        Schema::table('project_activities', function (Blueprint $table) {
            $table->dropForeign(['stage_id']);
        });
        Schema::table('project_planning', function (Blueprint $table) {
            $table->dropForeign(['stage_id']);
        });

        // Drop tables in reverse order
        Schema::dropIfExists('activity_stages');
        Schema::dropIfExists('project_planning_extended');
        Schema::dropIfExists('project_planning');
        Schema::dropIfExists('project_activities');
        Schema::dropIfExists('project_custom_activities');
        Schema::dropIfExists('project_view_configurations');
        Schema::dropIfExists('project_phase_templates');
        Schema::dropIfExists('project_project_phase');
        Schema::dropIfExists('project_phases');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('project_updates');
        Schema::dropIfExists('project_employee');
        Schema::dropIfExists('projects');
    }
};
