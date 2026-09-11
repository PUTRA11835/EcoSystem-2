<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `activity_date` — the actual date the work happened, separate from `date`
 * (the submit date, gated by the open-period rules in PeriodService). Unlike
 * `date`, this column carries no period/window restriction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->date('activity_date')->nullable()->after('date');
        });
    }

    public function down(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->dropColumn('activity_date');
        });
    }
};
