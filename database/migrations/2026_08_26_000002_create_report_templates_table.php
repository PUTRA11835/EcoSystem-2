<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Library template .docx untuk Word Report Generator — bisa lebih dari satu
 * per customer/business partner (customer_id nullable = template umum/
 * internal, tidak terikat customer tertentu). Upload lewat halaman generate
 * otomatis tersimpan ke sini supaya bisa dipakai ulang tanpa upload lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_templates', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->nullable()->constrained('customer', 'customer_id')->nullOnDelete();
            $table->string('name');
            $table->string('original_filename');
            $table->string('file_path');
            $table->foreignId('uploaded_by')->constrained('employee', 'employee_id');

            $table->timestamps();

            $table->index(['customer_id', 'name'], 'report_templates_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_templates');
    }
};
