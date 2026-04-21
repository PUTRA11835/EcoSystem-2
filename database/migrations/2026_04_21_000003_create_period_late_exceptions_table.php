<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('period_late_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('period_id')
                  ->constrained('reporting_periods')
                  ->cascadeOnDelete();

            $table->unsignedBigInteger('employee_id');
            $table->foreign('employee_id')
                  ->references('employee_id')->on('employee')
                  ->cascadeOnDelete();

            $table->enum('domain', ['project', 'support']);

            $table->unsignedBigInteger('granted_by');
            $table->foreign('granted_by')
                  ->references('employee_id')->on('employee')
                  ->restrictOnDelete();

            $table->timestamp('granted_at');
            $table->timestamp('expires_at')->nullable(); // null = permanent for this period
            $table->text('notes')->nullable();
            $table->timestamps();

            // One exception per employee per domain per period
            $table->unique(['period_id', 'employee_id', 'domain']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('period_late_exceptions');
    }
};
