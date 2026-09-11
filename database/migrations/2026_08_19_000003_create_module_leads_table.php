<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('module_leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('module_id');
            $table->unsignedBigInteger('employee_id');
            $table->timestamps();

            $table->unique(['module_id', 'employee_id']);
            $table->foreign('module_id')->references('id')->on('modules')->onDelete('cascade');
            $table->foreign('employee_id')->references('employee_id')->on('employee')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('module_leads');
    }
};
