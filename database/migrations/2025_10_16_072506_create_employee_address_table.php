<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_address', function (Blueprint $table) {
            $table->id('address_id');
            $table->foreignId('employee_id')
                ->constrained('employee', 'employee_id')
                ->onDelete('cascade');

            // 🏠 ADDRESS INFORMATION
            $table->string('address_type')->nullable(); // Home, Office, Temporary, dll
            $table->string('country')->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('rural_urban_village')->nullable();
            $table->string('street')->nullable();
            $table->string('house_number')->nullable();
            $table->string('postal_code')->nullable();

            // 📞 COMMUNICATION
            $table->string('language')->nullable();
            $table->string('cell_phone_country')->nullable();
            $table->string('telephone_country')->nullable();
            $table->string('fax_country')->nullable();
            $table->string('preferred_communication')->nullable(); // Pilihan utama: email, phone, dll
            $table->string('cell_phone')->nullable();
            $table->string('telephone')->nullable();
            $table->string('telephone_extension')->nullable();
            $table->string('fax')->nullable();
            $table->string('fax_extension')->nullable();

            $table->string('email_personal')->nullable();
            $table->string('email_work')->nullable();
            $table->string('website')->nullable();

            // ⏳ VALIDITY PERIOD
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();

            // ⚙️ STATUS
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_address');
    }
};
