<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll memakai tabel `employee_salary_components` yang SAMA dengan kotak Salary Components (migrasi
 * 2026_10_07_000004_create_employee_salary_components, branch alnaf_hr) — tabel ini TIDAK dibuat dua kali.
 *
 * Migrasi ini hanya MENAMBAH kolom yang dibutuhkan payroll; semuanya punya default aman, jadi baris yang dibuat kotak
 * Salary Components (atau disalin dari Offering Letter) langsung dapat dibaca payroll:
 *   effective_to   akhir berlaku (kosong = berlaku seterusnya)
 *   is_active      komponen dinonaktifkan tanpa dihapus
 *   taxable        ikut penghasilan bruto PPh 21
 *   bpjs_base      ikut dasar upah BPJS
 *   updated_by     siapa yang terakhir mengubah
 *
 * Urutan nama berkas menjamin migrasi alnaf_hr (2026_10_07_…) berjalan lebih dulu. Hanya menambah kolom; down()
 * menghapusnya. Idempoten (hasColumn) supaya aman dijalankan ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_salary_components')) {
            return;   // migrasi pembuat tabel belum ada di cabang ini — dijalankan lebih dulu oleh urutan nama
        }

        Schema::table('employee_salary_components', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_salary_components', 'effective_to')) {
                $table->date('effective_to')->nullable()->after('effective_from');
            }
            if (!Schema::hasColumn('employee_salary_components', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (!Schema::hasColumn('employee_salary_components', 'taxable')) {
                $table->boolean('taxable')->default(true);
            }
            if (!Schema::hasColumn('employee_salary_components', 'bpjs_base')) {
                $table->boolean('bpjs_base')->default(true);
            }
            if (!Schema::hasColumn('employee_salary_components', 'updated_by')) {
                $table->unsignedBigInteger('updated_by')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('employee_salary_components')) {
            return;
        }
        Schema::table('employee_salary_components', function (Blueprint $table) {
            foreach (['effective_to', 'is_active', 'taxable', 'bpjs_base', 'updated_by'] as $col) {
                if (Schema::hasColumn('employee_salary_components', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
