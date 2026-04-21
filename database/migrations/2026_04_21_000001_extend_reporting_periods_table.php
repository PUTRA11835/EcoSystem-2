<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reporting_periods', function (Blueprint $table) {
            // Actual date range (RPMO can set custom, defaults to 21-20 rule)
            $table->date('start_date')->nullable()->after('month');
            $table->date('end_date')->nullable()->after('start_date');

            // ── Global status (RPMO-controlled) ──────────────────────────────
            $table->enum('global_status', ['not_open', 'open', 'closed'])
                  ->default('not_open')->after('end_date');
            $table->timestamp('opened_at')->nullable()->after('global_status');
            $table->unsignedBigInteger('opened_by')->nullable()->after('opened_at');

            // ── Project domain status (Project Head-controlled) ───────────────
            $table->enum('project_status', ['not_open', 'open', 'closed'])
                  ->default('not_open')->after('opened_by');
            $table->timestamp('project_opened_at')->nullable()->after('project_status');
            $table->unsignedBigInteger('project_opened_by')->nullable()->after('project_opened_at');
            $table->timestamp('project_closed_at')->nullable()->after('project_opened_by');
            $table->unsignedBigInteger('project_closed_by')->nullable()->after('project_closed_at');

            // ── Support domain status (Support Head-controlled) ───────────────
            $table->enum('support_status', ['not_open', 'open', 'closed'])
                  ->default('not_open')->after('project_closed_by');
            $table->timestamp('support_opened_at')->nullable()->after('support_status');
            $table->unsignedBigInteger('support_opened_by')->nullable()->after('support_opened_at');
            $table->timestamp('support_closed_at')->nullable()->after('support_opened_by');
            $table->unsignedBigInteger('support_closed_by')->nullable()->after('support_closed_at');
        });

        // Migrate existing data: compute dates and infer statuses from closed_at
        DB::statement("
            UPDATE reporting_periods
            SET
                start_date = CONCAT(year, '-', LPAD(month, 2, '0'), '-21'),
                end_date   = CASE
                                 WHEN month = 12 THEN CONCAT(year + 1, '-01-20')
                                 ELSE CONCAT(year, '-', LPAD(month + 1, 2, '0'), '-20')
                             END,
                global_status  = CASE WHEN closed_at IS NOT NULL THEN 'closed' ELSE 'open' END,
                project_status = CASE WHEN closed_at IS NOT NULL THEN 'closed' ELSE 'open' END,
                support_status = CASE WHEN closed_at IS NOT NULL THEN 'closed' ELSE 'open' END,
                opened_by      = CASE WHEN closed_at IS NULL THEN closed_by ELSE NULL END
        ");
    }

    public function down(): void
    {
        Schema::table('reporting_periods', function (Blueprint $table) {
            $table->dropColumn([
                'start_date', 'end_date',
                'global_status', 'opened_at', 'opened_by',
                'project_status', 'project_opened_at', 'project_opened_by',
                'project_closed_at', 'project_closed_by',
                'support_status', 'support_opened_at', 'support_opened_by',
                'support_closed_at', 'support_closed_by',
            ]);
        });
    }
};
