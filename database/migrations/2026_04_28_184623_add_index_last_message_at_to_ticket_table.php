<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket', function (Blueprint $table) {
            $table->index('last_message_at',      'idx_ticket_last_message_at');
            $table->index('last_internal_note_at', 'idx_ticket_last_internal_note_at');
            $table->index('deleted_at',            'idx_ticket_deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('ticket', function (Blueprint $table) {
            $table->dropIndex('idx_ticket_last_message_at');
            $table->dropIndex('idx_ticket_last_internal_note_at');
            $table->dropIndex('idx_ticket_deleted_at');
        });
    }
};
