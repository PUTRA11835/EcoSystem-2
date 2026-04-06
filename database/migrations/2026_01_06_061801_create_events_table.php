<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->time('start_time');
            $table->date('end_date')->nullable();
            $table->time('end_time')->nullable();
            $table->string('location')->nullable();
            $table->enum('type', ['meeting', 'task', 'deadline', 'urgent', 'reminder'])->default('meeting');
            $table->boolean('all_day')->default(false);
            $table->string('color')->nullable();
            
            // UPDATE: Gunakan employee_id sebagai foreign key
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('employee_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('created_by')->references('employee_id')->on('employee')->onDelete('set null');
            $table->foreign('employee_id')->references('employee_id')->on('employee')->onDelete('set null');
            $table->foreign('customer_id')->references('customer_id')->on('customer')->onDelete('set null');
            
            // Indexes
            $table->index('start_date');
            $table->index('end_date');
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};