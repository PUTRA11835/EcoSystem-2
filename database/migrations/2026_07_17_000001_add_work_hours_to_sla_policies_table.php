<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sla_policies', function (Blueprint $table) {
            $table->time('work_start_time')->nullable()->after('is_24_hours');
            $table->time('work_end_time')->nullable()->after('work_start_time');
        });
    }

    public function down(): void
    {
        Schema::table('sla_policies', function (Blueprint $table) {
            $table->dropColumn(['work_start_time', 'work_end_time']);
        });
    }
};
