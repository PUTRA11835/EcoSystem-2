<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_credential', function (Blueprint $table) {
            $table->bigIncrements('credential_id');
            $table->unsignedBigInteger('customer_id')->unique();
            $table->longText('notes')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('customer_id')
                  ->references('customer_id')
                  ->on('customer')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_credential');
    }
};
