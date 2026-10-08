<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Komponen gaji per karyawan — SUMBER KEBENARAN gaji pokok untuk Payroll (keputusan 8 Okt 2026;
 * lihat docs/humancapital/06-CATATAN-PAYROLL.md). `employee_contract.salary` hanya cuplikan dokumen.
 *
 * Berlaku-tanggal: satu karyawan boleh punya riwayat komponen; yang dipakai payroll adalah baris
 * dengan effective_from <= tanggal periode dan (effective_to kosong atau >= tanggal periode).
 * Aturan "tepat satu Gaji Pokok pada satu tanggal" ditegakkan di SalaryComponentRules (kode),
 * bukan constraint DB, karena MySQL tak punya constraint berentang tanggal.
 *
 * Hanya menambah tabel; down() menghapusnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salary_components', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');

            $table->string('name', 120);
            // base | fixed_allowance | variable_allowance | deduction  (nilai dibatasi di kode)
            $table->string('category', 30);
            $table->decimal('amount', 15, 2)->default(0);

            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            $table->boolean('is_mandatory')->default(false);
            $table->boolean('is_active')->default(true);
            // Ikut penghasilan bruto PPh 21 / ikut dasar upah BPJS.
            $table->boolean('taxable')->default(true);
            $table->boolean('bpjs_base')->default(true);

            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'category', 'effective_from'], 'idx_salary_components_emp_cat');
            $table->index('is_active', 'idx_salary_components_active');

            $table->foreign('employee_id', 'fk_salary_components_employee')
                ->references('employee_id')->on('employee')->cascadeOnDelete();
            $table->foreign('updated_by', 'fk_salary_components_updated_by')
                ->references('employee_id')->on('employee')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_salary_components');
    }
};
