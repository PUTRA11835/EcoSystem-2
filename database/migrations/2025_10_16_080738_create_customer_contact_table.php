<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_contact', function (Blueprint $table) {
            $table->id('contact_id');
            $table->foreignId('customer_id')->constrained('customer','customer_id')->onDelete('cascade');

            /* 🟩 General Data Section */
            $table->string('title')->nullable();
            $table->string('full_name')->nullable();
            $table->string('nick_name')->nullable();
            $table->string('position')->nullable();
            $table->string('department')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->date('entry_date')->nullable();

            /* 🟦 Communication Section */
            $table->string('language')->nullable();
            $table->string('cell_phone_country')->nullable();
            $table->string('telephone_country')->nullable();
            $table->string('fax_country')->nullable();
            $table->string('email_personal')->nullable();
            $table->string('email_work')->nullable();
            $table->string('website')->nullable();

            $table->string('preferred_communication')->nullable();
            $table->string('cell_phone')->nullable();
            $table->string('telephone')->nullable();
            $table->string('fax')->nullable();
            $table->string('telephone_extension')->nullable();
            $table->string('fax_extension')->nullable();

            /* 🟨 Validity Period Section */
            // (sudah include valid_from & valid_to di atas)

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_contact');
    }
};
