<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracking satu permintaan generate laporan .docx dari template Word (fitur
 * ClaudeReportService). Diproses lewat queue job — kolom `status` di sini
 * yang dipoll frontend, sama seperti pola `ai_analysis_status` di
 * staging_ticket untuk fitur Analisa AI.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('word_reports', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')->constrained('employee', 'employee_id')->cascadeOnDelete();

            $table->string('template_original_name');
            $table->string('template_path');
            $table->text('instructions')->nullable();

            // pending -> processing -> completed|failed
            $table->string('status', 20)->default('pending');

            $table->string('docx_path')->nullable();
            $table->string('pdf_path')->nullable();
            $table->text('summary')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index(['employee_id', 'created_at'], 'word_reports_owner_recent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('word_reports');
    }
};
