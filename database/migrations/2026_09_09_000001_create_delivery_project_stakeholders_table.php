<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stakeholder Register per Delivery Project.
 *
 * Satu baris = satu stakeholder proyek, mengikuti output proses PMBOK
 * "Identify Stakeholders". Kolomnya dipetakan langsung dari sheet
 * "Stakeholder Register" pada template Excel PMP/PMBOK aligned:
 *
 *   ID → stakeholder_id (di-generate SH-001, SH-002, ... per proyek; `seq`
 *        menyimpan nomor urutnya supaya menghapus baris tidak membuat ID
 *        berikutnya bentrok dengan yang pernah dipakai).
 *   Kuadran Power-Interest → TIDAK disimpan; diturunkan otomatis dari
 *        kolom power + interest (lihat App\Models\DeliveryProjectStakeholder).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_project_stakeholders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_projects_id')
                  ->constrained('delivery_projects')
                  ->onDelete('cascade');

            // ── Identitas ──────────────────────────────────────────────────
            $table->unsignedSmallInteger('seq');                    // nomor urut dalam proyek
            $table->string('stakeholder_id', 30);                   // hasil generate: SH-001
            $table->string('name', 255);
            $table->string('role_title', 255)->nullable();          // Jabatan / Peran
            $table->string('organization', 255)->nullable();        // Organisasi / Departemen
            $table->string('category', 20)->default('Internal');    // Internal | Eksternal
            $table->string('classification', 60)->nullable();       // Tipe / Klasifikasi (Project Sponsor, ...)
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();

            // ── Power / Interest ──────────────────────────────────────────
            $table->string('power', 10)->nullable();                // Tinggi | Sedang | Rendah
            $table->string('interest', 10)->nullable();             // Tinggi | Sedang | Rendah

            // ── Engagement ───────────────────────────────────────────────
            $table->string('current_attitude', 20)->nullable();     // Unaware|Resistant|Neutral|Supportive|Leading
            $table->string('expected_attitude', 20)->nullable();
            $table->text('key_expectations')->nullable();           // Harapan Utama
            $table->text('information_needs')->nullable();          // Kebutuhan Informasi / Concern
            $table->text('engagement_strategy')->nullable();        // Strategi Engagement

            // ── Komunikasi ───────────────────────────────────────────────
            $table->string('communication_frequency', 30)->nullable(); // Harian|Mingguan|Dua Mingguan|Bulanan|Triwulanan|Sesuai Kebutuhan
            $table->string('communication_method', 255)->nullable();   // Metode / Channel Komunikasi
            $table->string('pic_internal', 255)->nullable();           // PIC Internal (anggota tim)
            $table->text('stakeholder_risk')->nullable();             // Risiko Terkait Stakeholder

            // ── Audit trail ──────────────────────────────────────────────
            $table->string('status', 20)->default('Aktif');         // Aktif | Tidak Aktif | Selesai
            $table->date('identified_date')->nullable();            // Tanggal Identifikasi
            $table->date('last_updated_date')->nullable();          // Update Terakhir
            $table->text('notes')->nullable();                      // Catatan

            $table->timestamps();

            $table->index('delivery_projects_id');
            $table->unique(['delivery_projects_id', 'stakeholder_id'], 'dp_stakeholder_project_sid_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_project_stakeholders');
    }
};
