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
            $table->unsignedBigInteger('last_internal_note_sender_id')->nullable()->after('last_internal_note_at');
        });

        // Backfill: sender_id dari internal note terbaru per ticket
        DB::statement("
            UPDATE ticket t
            JOIN (
                SELECT tm.ticket_id, tm.sender_id
                FROM ticket_message tm
                INNER JOIN (
                    SELECT ticket_id, MAX(created_at) AS latest
                    FROM ticket_message
                    WHERE is_internal_note = 1
                    GROUP BY ticket_id
                ) latest_note ON tm.ticket_id = latest_note.ticket_id
                    AND tm.created_at = latest_note.latest
                    AND tm.is_internal_note = 1
            ) src ON t.ticket_id = src.ticket_id
            SET t.last_internal_note_sender_id = src.sender_id
        ");
    }

    public function down(): void
    {
        Schema::table('ticket', function (Blueprint $table) {
            $table->dropColumn('last_internal_note_sender_id');
        });
    }
};
