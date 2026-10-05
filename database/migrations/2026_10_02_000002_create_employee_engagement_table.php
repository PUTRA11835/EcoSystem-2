<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Engagement konsultan External — satu baris per karyawan (keputusan E3 = blok Engagement di profil; HC-D47).
 *
 * KENAPA: form konsultan ESH memuat blok yang tidak punya tempat di EcoSystem — skema engagement
 * (mis. Mandays), vendor/partner, client/principal company, assignment/role, "dikelola oleh", tarif +
 * mata uang, tanggal mulai dan selesai. Ditaruh di tabel 1:1 TERPISAH (bukan kolom di
 * `employee_basic_data` yang dibaca ± 130 berkas) sehingga tidak ada tabel lama yang disentuh (HC-D15).
 *
 * BARIS DIBUAT SAAT PERTAMA DISIMPAN; tidak diisi massal untuk 58 External → tak ada data lama tersentuh.
 *
 * DATA SENSITIF: `rate`/`currency` (tarif konsultan). Dijaga slug izin TERPISAH
 * (`employee.section.engagement_rate.*`) dan seluruh tabel tertutup bagi asisten AI kecuali pemegang
 * izin tarif (TableAccess). Pemilik (konsultan) tidak punya jalur melihat/ubah blok ini sendiri.
 *
 * TUMPANG TINDIH DENGAN MODUL KONTRAK (SPKWT EXT) — DISADARI: saat modul Kontrak dibangun, tarif dan
 * periode engagement akan dihubungkan/diselaraskan dengan kontrak konsultan agar tidak ganda.
 * Sampai itu, tabel ini adalah satu-satunya tempat data tersebut.
 *
 * Hanya menambah tabel; down() menghapusnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_engagement', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');

            // Nilai skema dibatasi di kode (EngagementRules), bukan ENUM DB — daftar dapat bertambah tanpa migrasi.
            $table->string('engagement_scheme', 20)->nullable();
            $table->string('vendor_partner', 150)->nullable();
            $table->string('client_company', 150)->nullable();
            $table->string('assignment_role', 150)->nullable();
            $table->string('managed_by', 150)->nullable();   // jabatan/peran pengelola (mis. "Head of RPMO"), bukan akun
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            // SENSITIF — izin terpisah
            $table->decimal('rate', 15, 2)->nullable();
            $table->string('currency', 3)->nullable();

            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique('employee_id', 'uq_employee_engagement_employee');
            $table->index('end_date', 'idx_employee_engagement_end');       // dasar pengingat engagement berakhir
            $table->index('client_company', 'idx_employee_engagement_client');

            $table->foreign('employee_id', 'fk_employee_engagement_employee')
                ->references('employee_id')->on('employee')->cascadeOnDelete();
            $table->foreign('updated_by', 'fk_employee_engagement_updated_by')
                ->references('employee_id')->on('employee')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_engagement');
    }
};
