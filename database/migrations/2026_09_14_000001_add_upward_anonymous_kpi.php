<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds anonymous "upward" (subordinate → supervisor) KPI evaluations.
 *
 * An upward-type evaluation is filled by the subordinate (employee_id, via
 * the same self_* fields as a self-assessment) about their supervisor
 * (supervisor_id — the subject being rated). When the template is marked
 * anonymous, HR reviews each rater's submission individually but the
 * subject never sees them: only the published average (see published_at/
 * published_by below) is exposed on the subject's "My KPI" page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_templates', function (Blueprint $table) {
            $table->boolean('is_anonymous')->default(false)->after('target_type');
        });

        DB::statement("ALTER TABLE kpi_templates MODIFY target_type ENUM('self','supervisor','peer','upward') DEFAULT 'supervisor'");

        Schema::table('kpi_evaluations', function (Blueprint $table) {
            $table->boolean('is_anonymous')->default(false)->after('status')
                ->comment('Snapshot of template.is_anonymous at creation time');
            $table->timestamp('published_at')->nullable()->after('hr_approved_at')
                ->comment('When HR published the aggregated upward-evaluation average to the subject');
            $table->unsignedBigInteger('published_by')->nullable()->after('published_at')
                ->comment('FK employee.employee_id — the HR who published the average');
        });
    }

    public function down(): void
    {
        Schema::table('kpi_evaluations', function (Blueprint $table) {
            $table->dropColumn(['is_anonymous', 'published_at', 'published_by']);
        });

        DB::statement("ALTER TABLE kpi_templates MODIFY target_type ENUM('self','supervisor','peer') DEFAULT 'supervisor'");

        Schema::table('kpi_templates', function (Blueprint $table) {
            $table->dropColumn('is_anonymous');
        });
    }
};
