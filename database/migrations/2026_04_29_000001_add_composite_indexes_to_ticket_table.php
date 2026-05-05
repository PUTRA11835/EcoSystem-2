<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket', function (Blueprint $table) {
            $table->index(['customer_id', 'status', 'deleted_at'], 'idx_ticket_customer_status');
            $table->index(['employee_id', 'status', 'deleted_at'], 'idx_ticket_employee_status');
        });
    }

    public function down(): void
    {
        Schema::table('ticket', function (Blueprint $table) {
            $table->dropIndex('idx_ticket_customer_status');
            $table->dropIndex('idx_ticket_employee_status');
        });
    }
};
