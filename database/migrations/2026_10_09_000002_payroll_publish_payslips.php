<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Publish Payslip (alur ESH): slip yang sudah di-Generate baru terlihat oleh karyawan di ESS (Paystub) setelah dipublikasikan.
 * Aditif: dua kolom di slip (per karyawan) dan dua di periode (jejak kapan & siapa). Slip lama = belum dipublikasikan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_slips', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('published_by')->nullable();
            $table->index(['employee_id', 'published_at'], 'idx_payroll_slips_employee_published');
        });
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('published_by')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropColumn(['published_at', 'published_by']);
        });
        Schema::table('payroll_slips', function (Blueprint $table) {
            $table->dropIndex('idx_payroll_slips_employee_published');
            $table->dropColumn(['published_at', 'published_by']);
        });
    }
};
