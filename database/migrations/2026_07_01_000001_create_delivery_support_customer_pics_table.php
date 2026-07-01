<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_support_customer_pics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_support_id');
            $table->unsignedBigInteger('contact_id');
            $table->timestamps();

            $table->unique(['delivery_support_id', 'contact_id'], 'ds_customer_pics_unique');

            $table->foreign('delivery_support_id')
                  ->references('id')
                  ->on('delivery_support')
                  ->onDelete('cascade');

            $table->foreign('contact_id')
                  ->references('contact_id')
                  ->on('customer_contact')
                  ->onDelete('cascade');

            $table->index('delivery_support_id');
            $table->index('contact_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_support_customer_pics');
    }
};
