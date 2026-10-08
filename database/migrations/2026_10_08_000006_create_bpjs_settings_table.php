<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan BPJS berefektif-tanggal (Payroll Fase 2). APPEND-ONLY: setiap perubahan tarif/batas adalah baris
 * baru dengan `effective_date` baru; baris lama tidak diubah/dihapus oleh aplikasi sehingga payroll lama dapat
 * dihitung ulang dengan tarif yang berlaku saat itu. Satu tanggal berlaku hanya boleh satu baris (unique) —
 * berbeda dengan aplikasi acuan yang punya banyak baris pada tanggal yang sama.
 *
 * Hanya menambah tabel; down() menghapusnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bpjs_settings', function (Blueprint $table) {
            $table->id();
            $table->date('effective_date');

            // Kesehatan
            $table->decimal('health_employer_rate', 5, 2);
            $table->decimal('health_employee_rate', 5, 2);
            $table->decimal('health_min_base', 15, 2)->default(0);   // 0 = tanpa batas bawah (UMK diisi pemilik)
            $table->decimal('health_cap', 15, 2);

            // Ketenagakerjaan
            $table->decimal('jht_employer_rate', 5, 2);
            $table->decimal('jht_employee_rate', 5, 2);
            $table->decimal('jp_employer_rate', 5, 2);
            $table->decimal('jp_employee_rate', 5, 2);
            $table->decimal('jp_cap', 15, 2);
            $table->decimal('jkk_rate', 5, 2);
            $table->decimal('jkm_rate', 5, 2);

            // "Dihitung di payroll" per kelompok program (saklar perusahaan)
            $table->boolean('health_in_payroll')->default(true);
            $table->boolean('employment_in_payroll')->default(true);

            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique('effective_date', 'uq_bpjs_settings_effective_date');
            $table->foreign('created_by', 'fk_bpjs_settings_created_by')
                ->references('employee_id')->on('employee')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bpjs_settings');
    }
};
