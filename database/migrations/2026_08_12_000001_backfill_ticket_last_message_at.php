<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ticket list endpoints sort by COALESCE(last_message_at, created_at) DESC because
 * ~75% of existing tickets (migrated/legacy rows) never had last_message_at set,
 * which also defeats the idx_ticket_last_message_at index (function on indexed
 * column -> full scan + filesort on every /api/tickets request). Backfilling the
 * NULLs lets the ORDER BY switch to a plain last_message_at column while keeping
 * the exact same sort order these tickets already had.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('ticket')
            ->whereNull('last_message_at')
            ->update(['last_message_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        // Intentionally irreversible: we can't distinguish backfilled rows from
        // rows that always had last_message_at = created_at.
    }
};
