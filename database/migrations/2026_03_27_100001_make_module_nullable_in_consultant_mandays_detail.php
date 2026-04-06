<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consultant_mandays_detail', function (Blueprint $table) {
            $table->string('module')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('consultant_mandays_detail', function (Blueprint $table) {
            $table->string('module')->nullable(false)->change();
        });
    }
};
