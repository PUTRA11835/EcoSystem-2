<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_identification', function (Blueprint $table) {
            $table->id('identification_id');
            $table->foreignId('customer_id')->unique('customer','customer_id')->onDelete('cascade');

            $table->string('idenfication_type');
            $table->string('identification_number')->unique();
            $table->string('responsible_institution')->nullable();
            $table->string('country')->nullable();
            $table->string('region')->nullable();
            $table->date('entry_date')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('drive_link')->nullable(); // opsional sesuai ERD
            $table->string('verify_link')->nullable(); // opsional sesuai ERD

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_identification');
    }
};
