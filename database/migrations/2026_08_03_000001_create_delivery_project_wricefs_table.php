<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WRICEF Log per Delivery Project.
 *
 * Satu baris = satu objek WRICEF (Workflow / Report / Interface / Conversion /
 * Enhancement / Form) beserta progres tiga tahapnya: FSD → Development → Testing.
 *
 * Catatan kolom:
 *  - "Company" TIDAK disimpan di sini. Nilainya selalu diambil dari customer
 *    project (delivery_projects.client_id → customer.name_1) supaya tidak ada
 *    dua sumber kebenaran ketika customer project diganti.
 *  - `obj_id` di-generate otomatis dari sap_module + huruf category + nomor urut
 *    (mis. PS + Interface → PSI001). `seq` menyimpan nomor urutnya supaya
 *    penomoran tetap stabil walau ada baris yang dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_project_wricefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_projects_id')
                  ->constrained('delivery_projects')
                  ->onDelete('cascade');

            // ── Identitas objek ────────────────────────────────────────────
            $table->string('sap_module', 20);                       // PS, MM, SD, FI, ...
            $table->string('category', 20);                         // Workflow|Report|Interface|Conversion|Enhancement|Form
            $table->unsignedSmallInteger('seq');                    // nomor urut dalam prefix (module+category)
            $table->string('obj_id', 30);                           // hasil generate: PSI001
            $table->string('obj_name', 255);
            $table->text('capability')->nullable();                 // boleh multi-baris
            $table->string('tcode', 100)->nullable();

            // ── Request & approval ─────────────────────────────────────────
            $table->string('priority', 10)->default('Medium');      // High|Medium|Low
            $table->string('requestor', 150)->nullable();           // free text
            $table->date('request_date')->nullable();
            $table->decimal('effort_mandays', 8, 2)->nullable();
            $table->string('approved_by', 150)->nullable();         // nama anggota Project Team
            $table->date('approved_date')->nullable();
            $table->string('status', 20)->default('Open');          // Open|In Progress|Closed

            // ── Tahap FSD ──────────────────────────────────────────────────
            $table->string('fsd_pic', 150)->nullable();
            $table->date('fsd_start')->nullable();
            $table->date('fsd_end')->nullable();
            $table->string('fsd_status', 30)->nullable();           // Develop|Review|Revisi|Done
            $table->text('fsd_remarks')->nullable();

            // ── Tahap Development ──────────────────────────────────────────
            $table->string('dev_pic', 150)->nullable();
            $table->date('dev_start')->nullable();
            $table->date('dev_end')->nullable();
            $table->string('dev_status', 30)->nullable();           // Develop Scenario|Testing|Revisi|Done
            $table->text('dev_remarks')->nullable();

            // ── Tahap Testing ──────────────────────────────────────────────
            $table->string('test_pic', 150)->nullable();
            $table->date('test_start')->nullable();
            $table->date('test_end')->nullable();
            $table->string('test_status', 30)->nullable();          // Develop|Testing|Revisi|Done
            $table->text('test_remarks')->nullable();

            $table->timestamps();

            $table->index('delivery_projects_id');
            $table->unique(['delivery_projects_id', 'obj_id'], 'dp_wricef_project_obj_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_project_wricefs');
    }
};
