<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * KPI Module — Core Tables
 *
 * Four tables power the entire KPI workflow:
 *
 *   kpi_templates           — role-based template definition (HR configures)
 *   kpi_indicators          — weighted KPI indicators within a template
 *   kpi_evaluations         — one evaluation record per employee per period
 *                             (supervisor is assigned by HR per cycle)
 *   kpi_evaluation_details  — per-indicator scores (self-assessment + supervisor)
 *
 * Workflow:
 *   1. HR creates a kpi_evaluation for an employee, assigning a template + supervisor
 *   2. Employee submits self-assessment (kpi_evaluation_details.self_*)
 *   3. Supervisor submits scores     (kpi_evaluation_details.supervisor_*)
 *   4. Steps 2 & 3 are order-independent — BOTH must be done before HR can approve
 *   5. HR approves → employee sees results in "My KPI"
 *
 * Slug convention (POST-only routes):
 *   All data mutations go through POST. No PUT/PATCH/DELETE — matching the
 *   project-wide rule established in hr-general.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── 1. KPI Templates ─────────────────────────────────────────────────
        Schema::create('kpi_templates', function (Blueprint $table) {
            $table->id();

            // Which role this template applies to (role-based, not position-based)
            $table->unsignedBigInteger('role_id')->nullable()->comment('FK employee_role.id; null = global template');
            $table->string('name', 200);
            $table->text('description')->nullable();

            // Period type determines how often evaluations are expected
            $table->enum('period_type', ['monthly', 'quarterly', 'annual'])->default('monthly');

            $table->boolean('is_active')->default(true);

            // Audit
            $table->unsignedBigInteger('created_by')->nullable()->comment('FK employee.employee_id');
            $table->unsignedBigInteger('updated_by')->nullable()->comment('FK employee.employee_id');
            $table->timestamps();

            $table->index('role_id');
            $table->index('is_active');
        });

        // ── 2. KPI Indicators ────────────────────────────────────────────────
        Schema::create('kpi_indicators', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('template_id');
            $table->string('name', 300);
            $table->text('description')->nullable();

            // What is being measured (e.g., "%", "count", "score")
            $table->string('measurement_unit', 50)->nullable();

            // Numeric target (e.g., 95 for 95%)
            $table->decimal('target_value', 10, 2)->nullable();

            // Weight within template — all indicators of a template must sum to 100
            $table->decimal('weight', 5, 2)->default(0);

            $table->unsignedTinyInteger('order_seq')->default(1);
            $table->timestamps();

            $table->foreign('template_id')->references('id')->on('kpi_templates')->onDelete('cascade');
            $table->index('template_id');
        });

        // ── 3. KPI Evaluations ───────────────────────────────────────────────
        Schema::create('kpi_evaluations', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('employee_id')->comment('FK employee.employee_id');
            $table->unsignedBigInteger('template_id')->comment('FK kpi_templates.id');

            // Period: stored as YYYY-MM string for simplicity and readability
            $table->string('period_month', 7)->comment('Format: YYYY-MM');

            // HR assigns the supervisor per evaluation cycle (not auto-derived)
            $table->unsignedBigInteger('supervisor_id')->nullable()->comment('FK employee.employee_id');

            /**
             * Status lifecycle:
             *   draft          → created by HR, no action yet
             *   self_assessed  → employee submitted self-assessment
             *   reviewed       → supervisor submitted scores
             *   completed      → BOTH self-assessment AND supervisor review done
             *   hr_approved    → HR approved → visible to employee
             *   hr_rejected    → HR rejected → back for revision
             */
            $table->enum('status', [
                'draft',
                'self_assessed',
                'reviewed',
                'completed',
                'hr_approved',
                'hr_rejected',
            ])->default('draft');

            // Computed when all indicator scores are in
            $table->decimal('overall_score', 5, 2)->nullable();

            // Timestamps for each stage
            $table->timestamp('self_assessed_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('hr_approved_at')->nullable();

            // Who did what
            $table->unsignedBigInteger('hr_approved_by')->nullable()->comment('FK employee.employee_id');

            // HR feedback notes (visible to employee when approved/rejected)
            $table->text('hr_notes')->nullable();

            // Audit
            $table->unsignedBigInteger('created_by')->nullable()->comment('FK employee.employee_id — the HR who created this');
            $table->timestamps();

            $table->index('employee_id');
            $table->index('supervisor_id');
            $table->index('template_id');
            $table->index('period_month');
            $table->index('status');
            $table->index(['employee_id', 'period_month']);
        });

        // ── 4. KPI Evaluation Details ────────────────────────────────────────
        Schema::create('kpi_evaluation_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('evaluation_id');
            $table->unsignedBigInteger('indicator_id');

            // Self-assessment (filled by employee)
            $table->decimal('self_achievement', 5, 2)->nullable()->comment('Employee-reported achievement value');
            $table->text('self_notes')->nullable();
            $table->timestamp('self_submitted_at')->nullable();

            // Supervisor scoring (filled by supervisor)
            $table->decimal('supervisor_score', 5, 2)->nullable()->comment('0-100 score from supervisor');
            $table->text('supervisor_notes')->nullable();
            $table->timestamp('supervisor_submitted_at')->nullable();

            // Computed: indicator_weight × supervisor_score / 100
            $table->decimal('weighted_score', 5, 2)->nullable();

            $table->timestamps();

            $table->foreign('evaluation_id')->references('id')->on('kpi_evaluations')->onDelete('cascade');
            $table->foreign('indicator_id')->references('id')->on('kpi_indicators')->onDelete('cascade');
            $table->unique(['evaluation_id', 'indicator_id']);
            $table->index('evaluation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_evaluation_details');
        Schema::dropIfExists('kpi_evaluations');
        Schema::dropIfExists('kpi_indicators');
        Schema::dropIfExists('kpi_templates');
    }
};
