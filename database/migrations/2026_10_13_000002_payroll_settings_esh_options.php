<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan Payroll tambahan (menyamai Payroll Settings ESH). Semua bawaannya = perilaku sekarang, jadi tidak ada hasil
 * hitung yang berubah sampai pihak berwenang mengubahnya.
 *  - Lembur: dasar upah, menit minimum, pembulatan menit & metodenya.
 *  - Pajak/gaji bersih: pembulatan dasar TER bulanan, pembulatan gaji bersih (+ kelipatannya).
 *  - Cut-off pemrosesan payroll: tanggal bawaan untuk periode baru.
 *  - Timesheet & pinjaman: saklar siap pakai (belum tersambung ke sumber datanya — lihat catatan di halaman Settings).
 *  - Catatan internal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->string('overtime_wage_basis', 12)->default('base_fixed');     // base_fixed | base_only
            $table->unsignedSmallInteger('overtime_min_minutes')->default(0);
            $table->unsignedSmallInteger('overtime_round_minutes')->default(0);   // 0 = tanpa pembulatan
            $table->string('overtime_round_method', 8)->default('nearest');       // nearest | up | down
            $table->string('ter_base_rounding', 8)->default('none');              // none | nearest | up | down (kelipatan 1.000)
            $table->string('net_rounding_mode', 8)->default('none');              // none | nearest | up | down
            $table->unsignedInteger('net_rounding_increment')->default(1000);
            $table->boolean('cutoff_enabled')->default(false);
            $table->unsignedTinyInteger('cutoff_day')->default(25);
            $table->boolean('timesheet_allowance_enabled')->default(false);
            $table->boolean('tax_timesheet_allowance')->default(true);
            $table->boolean('loan_deduction_enabled')->default(false);
            $table->text('internal_notes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropColumn(['overtime_wage_basis', 'overtime_min_minutes', 'overtime_round_minutes', 'overtime_round_method', 'ter_base_rounding',
                'net_rounding_mode', 'net_rounding_increment', 'cutoff_enabled', 'cutoff_day', 'timesheet_allowance_enabled', 'tax_timesheet_allowance',
                'loan_deduction_enabled', 'internal_notes']);
        });
    }
};
