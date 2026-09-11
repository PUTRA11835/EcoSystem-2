<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda jenis employee: Internal vs External.
 *
 * Aturan tunggal: employee dianggap EXTERNAL bila Home Base = "Others"
 * (penanda dari file import HR), selain itu INTERNAL. Nilai ini DISIMPAN
 * (denormalisasi) agar bisa di-query/filter & dipakai harga MD (tarif
 * internal vs external berbeda) tanpa selalu menyimpulkan dari home_base.
 *
 * Lokasi: `employee_basic_data` (BUKAN `employee` yang dipakai API Jarvies),
 * konsisten dgn home_base & grade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_basic_data', function (Blueprint $table) {
            $table->string('employee_type', 20)->nullable()->after('grade');
        });

        // Backfill dari home_base yang sudah ada: 'Others' → External, sisanya Internal.
        DB::table('employee_basic_data')
            ->whereRaw('LOWER(TRIM(home_base)) = ?', ['others'])
            ->update(['employee_type' => 'External']);

        DB::table('employee_basic_data')
            ->where(function ($q) {
                $q->whereRaw('LOWER(TRIM(home_base)) <> ?', ['others'])
                  ->orWhereNull('home_base');
            })
            ->update(['employee_type' => 'Internal']);
    }

    public function down(): void
    {
        Schema::table('employee_basic_data', function (Blueprint $table) {
            $table->dropColumn('employee_type');
        });
    }
};
