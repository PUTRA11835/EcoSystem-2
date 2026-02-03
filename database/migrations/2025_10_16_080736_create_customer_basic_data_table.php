<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_basic_data', function (Blueprint $table) {
            $table->id('basic_data_id');

            // Relasi ke customer utama
            $table->foreignId('customer_id')->unique('customer','customer_id')->onDelete('cascade');

            // Data utama sesuai ERD
            $table->string('title')->nullable();
            $table->string('name_1')->nullable();
            $table->string('name_2')->nullable();
            $table->string('search_term_1')->nullable();
            $table->string('search_term_2')->nullable();
            $table->integer('external_number')->nullable();

            // Informasi kategori dan grup
            $table->string('customer_group')->nullable();
            $table->string('customer_category')->nullable();
            $table->string('credit_limit_type')->nullable();
            $table->string('industry_sector')->nullable();

            // Informasi akun eksekutif & otorisasi
            $table->string('ec_account_executive')->nullable();
            $table->string('sap_account_executive')->nullable();
            $table->string('authorization_group')->nullable();

            // Status dan flags
            $table->boolean('block')->default(false);
            $table->boolean('deletion_flag')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_basic_data');
    }
};
