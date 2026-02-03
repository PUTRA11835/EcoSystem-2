<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_bank', function (Blueprint $table) {
            $table->id('bank_id');
            $table->foreignId('customer_id')->constrained('customer','customer_id')->onDelete('cascade');

            $table->string('bank_name');
            $table->string('bank_key')->nullable();
            $table->string('account_number')->unique();
            $table->string('account_holder')->nullable();
            $table->string('drive_link')->nullable();
            $table->date('valid_from')->nullable();
            $table->date('valid_to')->nullable();
            $table->string('verify_link')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_bank');
    }
};
