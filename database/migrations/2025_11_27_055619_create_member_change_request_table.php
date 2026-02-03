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
        Schema::create('member_change_requests', function (Blueprint $table) {
        $table->id('change_request_id');
        $table->unsignedBigInteger('ticket_id');
        $table->unsignedBigInteger('requested_by');
        $table->enum('change_type', ['update', 'remove']);
        $table->json('member_ids');
        $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
        $table->unsignedBigInteger('processed_by')->nullable();
        $table->timestamp('processed_at')->nullable();
        $table->timestamps();  
        $table->foreign('ticket_id')->references('ticket_id')->on('ticket')->onDelete('cascade');
        $table->foreign('requested_by')->references('employee_id')->on('employee');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_change_requests');
    }
};
