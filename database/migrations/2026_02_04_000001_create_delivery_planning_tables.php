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
        // 5. ACTIVITY EMPLOYEES - Assignment karyawan ke aktivitas
        // ====================================================================
        Schema::create('activity_employee', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_project_activity_id');
            $table->unsignedBigInteger('employee_id');
            // Assignment details
            $table->enum('role', ['lead', 'member', 'reviewer', 'support'])->default('member');
            $table->decimal('allocation_percentage', 5, 2)->default(100);
            $table->boolean('is_active')->default(true);
            $table->date('assigned_date')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            // Unique constraint
            $table->unique(['delivery_project_activity_id', 'employee_id'], 'unique_activity_employee');
            $table->index(['employee_id', 'is_active'], 'idx_employee_assignments');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_employee');
        Schema::dropIfExists('delivery_activities');
        Schema::dropIfExists('delivery_stages');
        Schema::dropIfExists('delivery_groups');
        Schema::dropIfExists('delivery_phases');
    }
};
