<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_basic_data', function (Blueprint $table) {
            // Home base office (Jakarta, Yogyakarta, Surabaya)
            $table->string('home_base', 100)->nullable()->after('authorization_group');
            // Consultant grade — used to determine the man-day (MD) price
            $table->string('grade', 100)->nullable()->after('home_base');
        });
    }

    public function down(): void
    {
        Schema::table('employee_basic_data', function (Blueprint $table) {
            $table->dropColumn(['home_base', 'grade']);
        });
    }
};
