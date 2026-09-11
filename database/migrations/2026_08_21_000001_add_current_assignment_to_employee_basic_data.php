<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_basic_data', function (Blueprint $table) {
            $table->string('current_assignment', 255)->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('employee_basic_data', function (Blueprint $table) {
            $table->dropColumn('current_assignment');
        });
    }
};
