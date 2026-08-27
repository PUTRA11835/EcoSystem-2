<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_support_modules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_support_id');
            $table->unsignedBigInteger('module_id');
            $table->timestamps();

            $table->unique(['delivery_support_id', 'module_id']);
            $table->foreign('delivery_support_id')->references('id')->on('delivery_support')->onDelete('cascade');
            $table->foreign('module_id')->references('id')->on('modules')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_support_modules');
    }
};
