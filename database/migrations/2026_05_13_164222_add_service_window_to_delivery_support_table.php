<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_support', function (Blueprint $table) {
            $table->time('service_window_start')->nullable()->after('resolution_estimated');
            $table->time('service_window_end')->nullable()->after('service_window_start');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_support', function (Blueprint $table) {
            $table->dropColumn(['service_window_start', 'service_window_end']);
        });
    }
};
