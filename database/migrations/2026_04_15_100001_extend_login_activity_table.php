<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_activity', function (Blueprint $table) {
            $table->string('user_name')->nullable()->after('role_id');
            $table->enum('user_type', ['employee', 'customer'])->default('employee')->after('user_name');
            $table->string('device_type', 50)->nullable()->after('user_agent');  // desktop / mobile / tablet
            $table->string('browser', 100)->nullable()->after('device_type');
            $table->string('os', 100)->nullable()->after('browser');
            $table->string('location_city', 100)->nullable()->after('os');
            $table->string('location_country', 100)->nullable()->after('location_city');
        });
    }

    public function down(): void
    {
        Schema::table('login_activity', function (Blueprint $table) {
            $table->dropColumn(['user_name', 'user_type', 'device_type', 'browser', 'os', 'location_city', 'location_country']);
        });
    }
};