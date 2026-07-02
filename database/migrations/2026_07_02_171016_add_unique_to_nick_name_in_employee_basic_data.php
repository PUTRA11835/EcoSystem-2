<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fill any NULL/empty nick_name with first_name so NOT NULL constraint can be applied
        DB::statement("
            UPDATE employee_basic_data
            SET nick_name = first_name
            WHERE nick_name IS NULL OR nick_name = ''
        ");

        Schema::table('employee_basic_data', function (Blueprint $table) {
            $table->string('nick_name')->nullable(false)->change();
            // NULL values are excluded from uniqueness — multiple employees can have no nickname
            $table->unique('nick_name', 'uq_employee_basic_data_nick_name');
        });
    }

    public function down(): void
    {
        Schema::table('employee_basic_data', function (Blueprint $table) {
            $table->dropUnique('uq_employee_basic_data_nick_name');
            $table->string('nick_name')->nullable()->change();
        });
    }
};
