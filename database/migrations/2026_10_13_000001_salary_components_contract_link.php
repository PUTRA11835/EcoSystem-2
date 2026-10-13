<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Salary Components ← Contract: baris yang berasal dari kontrak aktif mencatat kontraknya (contract_id), supaya menyimpan
 * ulang kontrak yang sama memperbarui barisnya sendiri (tidak menggandakan). Aditif, nullable; baris lama tidak berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_salary_components', function (Blueprint $table) {
            $table->unsignedBigInteger('contract_id')->nullable()->after('offer_id');
            $table->index('contract_id', 'idx_esc_contract');
        });
    }

    public function down(): void
    {
        Schema::table('employee_salary_components', function (Blueprint $table) {
            $table->dropIndex('idx_esc_contract');
            $table->dropColumn('contract_id');
        });
    }
};
