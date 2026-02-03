<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('ticket_confirmation', function (Blueprint $table) {
            $table->id('confirmation_id');
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('employee_id'); // PIC yang take ticket
            $table->json('member_ids')->nullable(); // Support members
            $table->decimal('man_days', 10, 2);
            $table->enum('status', ['pending', 'confirmed', 'rejected'])->default('pending');
            $table->unsignedBigInteger('confirmed_by')->nullable(); // Admin yang konfirmasi
            $table->timestamp('confirmed_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
            
            $table->foreign('ticket_id')->references('ticket_id')->on('ticket')->onDelete('cascade');
            $table->foreign('employee_id')->references('employee_id')->on('employee')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('ticket_confirmation');
    }
};