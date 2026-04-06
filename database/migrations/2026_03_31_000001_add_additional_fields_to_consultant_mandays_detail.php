<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultant_mandays_detail', function (Blueprint $table) {
            $table->decimal('additional_mandays', 6, 2)->default(0)->after('mandays');
            $table->decimal('approved_additional', 6, 2)->default(0)->after('additional_mandays');
        });
    }

    public function down(): void
    {
        Schema::table('consultant_mandays_detail', function (Blueprint $table) {
            $table->dropColumn(['additional_mandays', 'approved_additional']);
        });
    }
};
