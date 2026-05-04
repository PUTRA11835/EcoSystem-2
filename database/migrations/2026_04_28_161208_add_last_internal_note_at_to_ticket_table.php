<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket', function (Blueprint $table) {
            $table->timestamp('last_internal_note_at')->nullable()->after('last_agent_reply_at');
        });

        // Backfill: set last_internal_note_at from latest internal note per ticket
        DB::statement("
            UPDATE ticket t
            JOIN (
                SELECT ticket_id, MAX(created_at) AS latest
                FROM ticket_message
                WHERE is_internal_note = 1
                GROUP BY ticket_id
            ) m ON t.ticket_id = m.ticket_id
            SET t.last_internal_note_at = m.latest
        ");
    }

    public function down(): void
    {
        Schema::table('ticket', function (Blueprint $table) {
            $table->dropColumn('last_internal_note_at');
        });
    }
};
