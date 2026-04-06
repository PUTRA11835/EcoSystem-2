<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');   // recipient
            $table->string('type', 50)->default('mention'); // 'mention', 'reply', etc.
            $table->unsignedBigInteger('ticket_id')->nullable();
            $table->unsignedBigInteger('message_id')->nullable();
            $table->unsignedBigInteger('from_employee_id')->nullable(); // sender
            $table->string('from_name', 255)->nullable();
            $table->text('preview')->nullable();         // short snippet
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'is_read']);
            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
