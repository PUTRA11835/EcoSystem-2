<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_qualification', function (Blueprint $table) {
            $table->id('qualification_id');
            $table->foreignId('employee_id')
                ->constrained('employee', 'employee_id')
                ->onDelete('cascade');

            // Data umum qualification
            $table->string('qualification_type')->nullable(); // e.g., Education, Certification, Language (ditentukan di view)
            $table->string('module')->nullable(); // untuk education
            $table->string('language')->nullable();
            $table->string('qualification_level')->nullable();
            $table->string('first_year')->nullable();
            $table->boolean('certified')->nullable();
            $table->boolean('dpm')->nullable();
            $table->boolean('dsm')->nullable();
            $table->date('valid_to')->nullable(); // masa berlaku sertifikasi
            $table->date('valid_from')->nullable();

            // Lampiran file
            $table->string('drive_link')->nullable(); // lampiran file (scan ijazah/cert)
            $table->string('verify_link')->nullable(); // link verifikasi eksternal

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_qualification');
    }
};
