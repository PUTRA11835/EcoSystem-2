<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['employee_basic_data', 'employee_family'] as $table) {
            DB::table($table)->whereIn('religion', ['Confucian', 'Confucianism'])->update(['religion' => 'Konghucu']);
            DB::table($table)->where('religion', 'Budha')->update(['religion' => 'Buddhist']);
        }
    }

    public function down(): void
    {
        foreach (['employee_basic_data', 'employee_family'] as $table) {
            DB::table($table)->where('religion', 'Konghucu')->update(['religion' => 'Confucian']);
        }
    }
};
