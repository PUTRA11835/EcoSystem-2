<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_contact_groups', function (Blueprint $table) {
            $table->id('group_id');
            $table->foreignId('customer_id')->constrained('customer', 'customer_id')->onDelete('cascade');
            $table->string('name');
            $table->timestamps();

            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_contact_groups');
    }
};
