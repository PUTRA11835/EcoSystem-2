<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_member', function (Blueprint $table) {
            $table->id('ticket_member_id');
            $table->foreignId('ticket_id')
                ->constrained('ticket', 'ticket_id')
                ->onDelete('cascade');
            $table->foreignId('employee_id')
                ->constrained('employee', 'employee_id')
                ->onDelete('cascade');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_member');
    }
};
