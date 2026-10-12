<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks whether admins have already been alerted for the CURRENT
     * staleness episode of a task, so the periodic staleness check doesn't
     * re-notify every run while a task stays stale - only once when it
     * first crosses the threshold, reset back to null the next time the
     * task succeeds.
     */
    public function up(): void
    {
        Schema::table('scheduled_task_status', function (Blueprint $table) {
            $table->timestamp('stale_notified_at')->nullable()->after('consecutive_failures');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_task_status', function (Blueprint $table) {
            $table->dropColumn('stale_notified_at');
        });
    }
};
