<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Approved Days" for Resolution Days review — mirrors approved_additional, but for the
 * base proposed `mandays` instead of `additional_mandays`. Previously Head had no way to
 * approve less than what the consultant proposed for the base days; only the additional
 * portion was adjustable.
 *
 * Scope note: this column feeds Total Days display + consultant_mandays.total_mandays /
 * ticket.man_days ONLY. It intentionally does NOT replace `mandays` in the MD-quota checks
 * used elsewhere (TimesheetController submit validation, Consultant Workload, Reporting
 * dashboards) — those keep reading the raw `mandays` column as before, unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultant_mandays_detail', function (Blueprint $table) {
            $table->decimal('approved_mandays', 6, 2)->nullable()->after('mandays');
        });

        // Backfill: proposals already approved under the old (implicit 100% pass-through)
        // rule effectively had their full `mandays` approved — preserve that history instead
        // of silently zeroing out already-approved totals.
        DB::table('consultant_mandays_detail as cmd')
            ->join('consultant_mandays as cm', 'cm.id', '=', 'cmd.consultant_mandays_id')
            ->where('cm.status', 'approved')
            ->update(['cmd.approved_mandays' => DB::raw('cmd.mandays')]);
    }

    public function down(): void
    {
        Schema::table('consultant_mandays_detail', function (Blueprint $table) {
            $table->dropColumn('approved_mandays');
        });
    }
};
