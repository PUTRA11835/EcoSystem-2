<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('login_activity', function (Blueprint $table) {
            $table->string('device_brand', 100)->nullable()->after('device_type');
            $table->string('device_model', 100)->nullable()->after('device_brand');
        });
    }

    public function down(): void
    {
        Schema::table('login_activity', function (Blueprint $table) {
            $table->dropColumn(['device_brand', 'device_model']);
        });
    }
};
