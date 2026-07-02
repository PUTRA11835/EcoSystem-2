<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('name', 150);
            $table->string('meeting_link', 2048)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('ticket_id', 'fk_meeting_templates_ticket')
                ->references('ticket_id')
                ->on('ticket')
                ->onDelete('cascade');

            $table->foreign('employee_id', 'fk_meeting_templates_employee')
                ->references('employee_id')
                ->on('employee')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_templates');
    }
};
