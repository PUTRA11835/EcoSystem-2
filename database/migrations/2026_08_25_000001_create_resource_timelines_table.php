<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_timelines', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('employee_id');
            $table->foreign('employee_id')
                  ->references('employee_id')->on('employee')
                  ->restrictOnDelete();

            $table->date('date');
            $table->string('location')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_timelines');
    }
};
