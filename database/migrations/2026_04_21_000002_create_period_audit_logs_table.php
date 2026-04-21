<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')
                  ->constrained('reporting_periods')
                  ->cascadeOnDelete();

            // Action identifiers: period_created, global_open, global_close,
            // project_open, project_close, support_open, support_close,
            // force_close_project, force_close_support,
            // late_exception_granted_project, late_exception_granted_support,
            // late_exception_revoked_project, late_exception_revoked_support
            $table->string('action', 60);

            $table->unsignedBigInteger('actor_id');
            $table->foreign('actor_id')
                  ->references('employee_id')->on('employee')
                  ->restrictOnDelete();

            $table->unsignedSmallInteger('actor_role_id');
            $table->boolean('is_force')->default(false);
            $table->json('metadata')->nullable(); // extra context (employee_id for exceptions, etc.)
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_audit_logs');
    }
};
