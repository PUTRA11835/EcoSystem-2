<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_templates', function (Blueprint $table) {
            // Deadline for filling this template, interpreted by period_type:
            //   monthly   → day of every month
            //   quarterly → month + day (every year)
            //   annual    → day + month + year (one specific date)
            $table->unsignedTinyInteger('deadline_day')->nullable()->after('period_type');
            $table->unsignedTinyInteger('deadline_month')->nullable()->after('deadline_day');
            $table->unsignedSmallInteger('deadline_year')->nullable()->after('deadline_month');
        });
    }

    public function down(): void
    {
        Schema::table('kpi_templates', function (Blueprint $table) {
            $table->dropColumn(['deadline_day', 'deadline_month', 'deadline_year']);
        });
    }
};
