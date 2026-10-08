<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan PPh 21 per TAHUN PAJAK (Payroll Fase 1). Semua tarif dari DB — tidak ada angka tarif di kode.
 *
 *  - pph21_ptkp                 kode PTKP + nilai setahun
 *  - pph21_progressive_brackets lapisan Pasal 17 (batas atas termasuk; NULL = tanpa batas)
 *  - pph21_ter_rates            tarif efektif bulanan kategori A/B/C (batas bawah tidak termasuk, batas atas termasuk)
 *  - pph21_settings             satu baris per tahun: biaya jabatan, perlakuan premi pemberi kerja
 *
 * Hanya menambah tabel; down() menghapusnya. Data awal: migrasi 2026_10_08_000004.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pph21_ptkp', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('code', 10);
            $table->string('description', 100);
            $table->decimal('annual_amount', 15, 2);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['year', 'code'], 'uq_pph21_ptkp_year_code');
        });

        Schema::create('pph21_progressive_brackets', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('seq');
            $table->decimal('upper_limit', 17, 2)->nullable();
            $table->decimal('rate', 5, 2);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['year', 'seq'], 'uq_pph21_brackets_year_seq');
        });

        Schema::create('pph21_ter_rates', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->char('category', 1);
            $table->unsignedSmallInteger('seq');
            $table->decimal('gross_over', 17, 2);
            $table->decimal('gross_upto', 17, 2)->nullable();
            $table->decimal('rate', 5, 2);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['year', 'category', 'seq'], 'uq_pph21_ter_year_cat_seq');
        });

        Schema::create('pph21_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->unique();
            $table->decimal('occupational_cost_rate', 5, 2)->default(5);
            $table->decimal('occupational_cost_monthly_max', 15, 2)->default(500000);
            $table->boolean('include_employer_premiums')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pph21_settings');
        Schema::dropIfExists('pph21_ter_rates');
        Schema::dropIfExists('pph21_progressive_brackets');
        Schema::dropIfExists('pph21_ptkp');
    }
};
