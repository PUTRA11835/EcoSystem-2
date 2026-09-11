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
        Schema::table('ticket_message', function (Blueprint $table) {
            $table->unsignedBigInteger('sla_message_by')->nullable()->after('sla_message');
            $table->timestamp('sla_message_at')->nullable()->after('sla_message_by');

            $table->foreign('sla_message_by')->references('employee_id')->on('employee')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ticket_message', function (Blueprint $table) {
            $table->dropForeign(['sla_message_by']);
            $table->dropColumn(['sla_message_by', 'sla_message_at']);
        });
    }
};
