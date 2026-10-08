<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel Payroll (Fase 3). Hanya MENAMBAH tabel; down() menghapusnya.
 *
 *  payroll_settings     satu baris (id=1): kebijakan perhitungan
 *  payroll_periods      periode + status (open → approved → paid → locked) + total
 *  payroll_slips        satu slip per karyawan per periode; ringkasan + SNAPSHOT (karyawan, tarif BPJS & PPh 21)
 *                       sehingga slip lama tak berubah walau pengaturan diubah kemudian
 *  payroll_slip_items   baris rinci slip (pendapatan, potongan, iuran perusahaan)
 *  payroll_adjustments  penyesuaian manual per karyawan per periode (bonus, THR, koreksi, potongan)
 *
 * Slip & item dibuat ulang oleh Calculate/Recalculate HANYA pada periode `open` (data hasil sistem, bukan input).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->boolean('overtime_enabled')->default(true);
            $table->unsignedTinyInteger('work_days_per_week')->default(5);      // 5 | 6 (menggeser pengali lembur hari libur)
            $table->boolean('prorate_partial_period')->default(true);           // komponen yang hanya berlaku sebagian periode dibayar prorata
            $table->unsignedTinyInteger('final_tax_month')->default(12);        // masa pajak terakhir: true-up setahun
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
        DB::table('payroll_settings')->insert(['id' => 1, 'created_at' => now(), 'updated_at' => now()]);

        Schema::create('payroll_periods', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->date('period_start');
            $table->date('period_end');
            $table->date('pay_date');
            $table->string('status', 12)->default('open');

            $table->unsignedInteger('employee_count')->default(0);
            $table->decimal('total_gross', 17, 2)->default(0);
            $table->decimal('total_deductions', 17, 2)->default(0);
            $table->decimal('total_take_home', 17, 2)->default(0);
            $table->decimal('total_bpjs_employee', 17, 2)->default(0);
            $table->decimal('total_bpjs_employer', 17, 2)->default(0);
            $table->decimal('total_pph21', 17, 2)->default(0);
            $table->json('warnings')->nullable();            // peringatan hasil Calculate terakhir

            $table->timestamp('calculated_at')->nullable();
            $table->unsignedBigInteger('calculated_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['period_start', 'period_end'], 'idx_payroll_periods_range');
            $table->index('status', 'idx_payroll_periods_status');
        });

        Schema::create('payroll_slips', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('period_id');
            $table->unsignedBigInteger('employee_id');

            // Snapshot identitas saat dihitung
            $table->string('employee_name', 200);
            $table->string('employee_eci', 50)->nullable();
            $table->string('position', 150)->nullable();
            $table->string('department', 150)->nullable();
            $table->string('ptkp_code', 10)->nullable();
            $table->string('bank_name', 100)->nullable();
            $table->string('bank_account', 60)->nullable();

            $table->decimal('gross_earnings', 15, 2)->default(0);
            $table->decimal('overtime', 15, 2)->default(0);
            $table->decimal('component_deductions', 15, 2)->default(0);
            $table->decimal('bpjs_employee', 15, 2)->default(0);
            $table->decimal('bpjs_employer', 15, 2)->default(0);
            $table->decimal('pph21', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('take_home_pay', 15, 2)->default(0);
            $table->decimal('tax_gross', 15, 2)->default(0);       // bruto PPh 21 bulan ini (termasuk premi perusahaan bila diatur)
            $table->decimal('employee_pension', 15, 2)->default(0); // JHT + JP porsi karyawan (untuk true-up)
            $table->decimal('pph21_refund', 15, 2)->default(0);     // kelebihan potong pada true-up (informasi Accounting)

            $table->string('status', 12)->default('draft');         // draft | approved
            $table->json('breakdown')->nullable();                  // BPJS, PPh 21, tarif yang dipakai, peringatan
            $table->timestamps();

            $table->unique(['period_id', 'employee_id'], 'uq_payroll_slips_period_employee');
            $table->index('employee_id', 'idx_payroll_slips_employee');
            $table->foreign('period_id', 'fk_payroll_slips_period')->references('id')->on('payroll_periods')->cascadeOnDelete();
            $table->foreign('employee_id', 'fk_payroll_slips_employee')->references('employee_id')->on('employee');
        });

        Schema::create('payroll_slip_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('slip_id');
            $table->string('type', 12);                  // earning | deduction | employer
            $table->string('code', 30);
            $table->string('name', 200);
            $table->decimal('amount', 15, 2);
            $table->boolean('taxable')->default(false);
            $table->string('source_type', 30)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);

            $table->index('slip_id', 'idx_payroll_items_slip');
            $table->foreign('slip_id', 'fk_payroll_items_slip')->references('id')->on('payroll_slips')->cascadeOnDelete();
        });

        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('period_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('kind', 12);                  // earning | deduction
            $table->string('name', 150);
            $table->decimal('amount', 15, 2);
            $table->boolean('taxable')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['period_id', 'employee_id'], 'idx_payroll_adj_period_emp');
            $table->foreign('period_id', 'fk_payroll_adj_period')->references('id')->on('payroll_periods')->cascadeOnDelete();
            $table->foreign('employee_id', 'fk_payroll_adj_employee')->references('employee_id')->on('employee');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('payroll_slip_items');
        Schema::dropIfExists('payroll_slips');
        Schema::dropIfExists('payroll_periods');
        Schema::dropIfExists('payroll_settings');
    }
};
