<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tautkan word_reports ke template library-nya (report_templates). Kolom
 * template_path/template_original_name yang sudah ada TETAP dipakai apa
 * adanya oleh ReportGeneratorService (snapshot template yang benar-benar
 * dipakai generate ini) — report_template_id murni penanda "dari template
 * mana asalnya", untuk riwayat/reuse, bukan pengganti kolom itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('word_reports', function (Blueprint $table) {
            $table->foreignId('report_template_id')->nullable()->after('employee_id')
                ->constrained('report_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('word_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('report_template_id');
        });
    }
};
