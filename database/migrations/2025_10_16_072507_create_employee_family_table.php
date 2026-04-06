<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_family', function (Blueprint $table) {
            $table->id('family_id');
            $table->foreignId('employee_id')->constrained('employee','employee_id')->onDelete('cascade');

            // 👨‍👩‍👧 Informasi Anggota Keluarga
            $table->string('name');                     // Nama lengkap anggota keluarga
            $table->string('title')->nullable();
            $table->string('relation');                 // spouse, child, parent, sibling, dll
            $table->string('gender')->nullable();       // Jenis kelamin
            $table->string('religion')->nullable();      // Agama
            $table->string('country')->nullable();      // Negara (sesuai ERD family)
            $table->string('birth_place')->nullable();  // Tempat lahir
            $table->date('birth_date')->nullable();     // Tanggal lahir
            $table->string('occupation')->nullable();   // Pekerjaan

            // 🧾 Status & Validitas
            $table->boolean('is_alive')->default(true);      // Tambahan: status hidup
            $table->date('valid_from')->nullable();          // Dari tanggal
            $table->date('valid_to')->nullable();            // Sampai tanggal

            // 📎 Metadata
            $table->string('verify_link')->nullable();       // Bukti/verifikasi dokumen (optional)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_family');
    }
};
