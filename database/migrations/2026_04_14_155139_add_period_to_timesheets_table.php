<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            // Period override: if null, period is computed from date (21-20 rule)
            // If set, this overrides the computed period (used when date falls in a closed period)
            $table->smallInteger('period_year')->unsigned()->nullable()->after('md_consumed');
            $table->tinyInteger('period_month')->unsigned()->nullable()->after('period_year');
        });
    }

    public function down(): void
    {
        Schema::table('timesheets', function (Blueprint $table) {
            $table->dropColumn(['period_year', 'period_month']);
        });
    }
};
