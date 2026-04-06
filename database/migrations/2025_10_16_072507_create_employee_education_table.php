<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_education', function (Blueprint $table) {
            $table->id('education_id');
            $table->foreignId('employee_id')->constrained('employee','employee_id')->onDelete('cascade');

            /* 🟩 Education Data Section */
            $table->string('education_type')->nullable();        // S1, S2, Diploma, dsb
            $table->string('institute_place')->nullable();       // Nama universitas / tempat
            $table->string('country')->nullable();
            $table->string('degree')->nullable();                // Bachelor, Master, dll
            $table->string('duration_of_course')->nullable();    // Lama studi
            $table->string('final_grade')->nullable();           // IPK / Grade
            $table->string('branch_of_study')->nullable();       // Jurusan
            $table->year('start_year')->nullable();
            $table->year('graduation_year')->nullable();
            $table->string('unit')->nullable();                  // Fakultas atau unit akademik

            /* 🟨 Validity Period */
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();

            /* 📎 Attachment Link */
            $table->string('attachment_name')->nullable();       // contoh: “S1 Certificate”
            $table->string('attachment_verify_link')->nullable();
            $table->string('attachment_drive_link')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_education');
    }
};
