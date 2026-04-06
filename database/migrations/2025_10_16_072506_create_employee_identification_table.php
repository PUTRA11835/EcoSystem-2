<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_identification', function (Blueprint $table) {
            $table->id('identification_id');
            $table->foreignId('employee_id')->constrained('employee','employee_id')->onDelete('cascade');

            $table->string('identification_type'); // e.g., KTP, Passport
            $table->string('identification_number')->unique();
            $table->string('responsible_institution')->nullable();
            $table->string('country')->nullable();
            $table->string('region')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->date('entry_date')->nullable();
            $table->string('drive_link')->nullable(); // opsional sesuai ERD
            $table->string('verify_link')->nullable(); // opsional sesuai ERD

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_identification');
    }
};
