<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_address', function (Blueprint $table) {
            $table->id('address_id');
            $table->foreignId('customer_id')->constrained('customer','customer_id')->onDelete('cascade');

            /* 🟩 Address Section */
            $table->string('address_type')->nullable();
            $table->string('country')->nullable();
            $table->string('region')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('rural_urban_village')->nullable();
            $table->string('street')->nullable();
            $table->string('house_number')->nullable();
            $table->string('postal_code')->nullable();

            /* 🟦 Communication Section */
            $table->string('language')->nullable();
            $table->string('cell_phone_country')->nullable();
            $table->string('telephone_country')->nullable();
            $table->string('fax_country')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();

            $table->string('preferred_communication')->nullable();
            $table->string('cell_phone')->nullable();
            $table->string('telephone')->nullable();
            $table->string('fax')->nullable();
            $table->string('telephone_extension')->nullable();
            $table->string('fax_extension')->nullable();

            /* 🟨 Validity Period Section */
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_address');
    }
};
