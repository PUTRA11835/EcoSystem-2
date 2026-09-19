<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per distinct scheduled task (upserted on every run) - lets the
     * Control Center Schedule Monitor page answer "when did this last run,
     * and did it succeed" without scanning a growing history table.
     */
    public function up(): void
    {
        Schema::create('scheduled_task_status', function (Blueprint $table) {
            $table->id();
            $table->string('task_name')->unique();
            $table->timestamp('last_started_at')->nullable();
            $table->timestamp('last_finished_at')->nullable();
            $table->string('last_status')->nullable(); // running|success|failed|skipped
            $table->integer('last_exit_code')->nullable();
            $table->unsignedInteger('last_duration_ms')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->unsignedInteger('total_runs')->default(0);
            $table->timestamps();
        });

        // Append-only log of non-success outcomes only (failures/skips) - kept
        // small by design since those should be rare, unlike a full per-run
        // history which would grow unbounded for the every-minute tasks.
        Schema::create('scheduled_task_runs', function (Blueprint $table) {
            $table->id();
            $table->string('task_name');
            $table->string('status'); // failed|skipped
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->integer('exit_code')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['task_name', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_task_runs');
        Schema::dropIfExists('scheduled_task_status');
    }
};
