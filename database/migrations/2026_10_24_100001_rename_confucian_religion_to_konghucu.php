<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agama "Confucian" di UI diganti nama yang benar: "Konghucu". Data lama ikut disesuaikan (hanya nilai persis 'Confucian'/'Budha';
 * kolom lain dan baris lain tidak disentuh). Validasi tetap menerima 'Confucian' untuk klien/impor lama.
 */
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
