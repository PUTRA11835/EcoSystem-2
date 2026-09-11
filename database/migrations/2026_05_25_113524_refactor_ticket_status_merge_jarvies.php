<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor: Merge `status` + `jarvies_status` → single unified `status` field.
 *
 * New ENUM values:
 *   open | inprocess | waiting_on_customer | waiting_on_3rd_party
 *   waiting_to_confirmation | hold | cancelled | closed
 *
 * Migration priority (highest first):
 *   1. Terminal states from old status  : cancel → cancelled, closed → closed, hold → hold
 *   2. Granular workflow from jarvies   : in process, author action, sent in to SAP, proposed solution
 *   3. Fallback from old status         : in_progress, reply, wait_to_close
 *   4. Default                          : everything else → open
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Step 1: Relax ENUM to VARCHAR so we can freely UPDATE values ────────
        DB::statement("ALTER TABLE ticket MODIFY COLUMN status VARCHAR(60) NOT NULL DEFAULT 'open'");

        // ── Step 2: Migrate terminal states first (highest priority) ────────────
        DB::statement("UPDATE ticket SET status = 'cancelled' WHERE status = 'cancel'");
        DB::statement("UPDATE ticket SET status = 'closed'    WHERE status = 'closed'");
        DB::statement("UPDATE ticket SET status = 'hold'      WHERE status = 'hold'");

        // ── Step 3: Merge jarvies_status for non-terminal tickets ───────────────
        // Only touch tickets NOT already in a terminal/hold state
        $nonTerminal = "status NOT IN ('cancelled','closed','hold')";

        DB::statement("UPDATE ticket SET status = 'inprocess'
            WHERE {$nonTerminal} AND jarvies_status = 'in process'");

        DB::statement("UPDATE ticket SET status = 'waiting_on_customer'
            WHERE {$nonTerminal} AND status NOT IN ('inprocess')
            AND jarvies_status = 'author action'");

        DB::statement("UPDATE ticket SET status = 'waiting_on_3rd_party'
            WHERE {$nonTerminal} AND status NOT IN ('inprocess','waiting_on_customer')
            AND jarvies_status = 'sent in to SAP'");

        DB::statement("UPDATE ticket SET status = 'waiting_to_confirmation'
            WHERE {$nonTerminal} AND status NOT IN ('inprocess','waiting_on_customer','waiting_on_3rd_party')
            AND jarvies_status = 'proposed solution'");

        // jarvies_status = 'closed' acts as terminal
        DB::statement("UPDATE ticket SET status = 'closed'
            WHERE {$nonTerminal} AND jarvies_status = 'closed'");

        // ── Step 4: Fallback from old status values not yet mapped ──────────────
        DB::statement("UPDATE ticket SET status = 'inprocess'
            WHERE status = 'in_progress'");

        DB::statement("UPDATE ticket SET status = 'waiting_on_customer'
            WHERE status = 'reply'");

        DB::statement("UPDATE ticket SET status = 'waiting_to_confirmation'
            WHERE status = 'wait_to_close'");

        // ── Step 5: Any remaining unmapped value → open ─────────────────────────
        DB::statement("UPDATE ticket
            SET status = 'open'
            WHERE status NOT IN ('open','inprocess','waiting_on_customer','waiting_on_3rd_party','waiting_to_confirmation','hold','cancelled','closed')");

        // ── Step 6: Drop jarvies_status column ──────────────────────────────────
        Schema::table('ticket', function ($table) {
            $table->dropColumn('jarvies_status');
        });

        // ── Step 7: Apply strict ENUM with new unified values ───────────────────
        DB::statement("ALTER TABLE ticket MODIFY COLUMN status
            ENUM('open','inprocess','waiting_on_customer','waiting_on_3rd_party','waiting_to_confirmation','hold','cancelled','closed')
            NOT NULL DEFAULT 'open'");
    }

    public function down(): void
    {
        // Restore VARCHAR for rollback flexibility
        DB::statement("ALTER TABLE ticket MODIFY COLUMN status VARCHAR(60) NOT NULL DEFAULT 'open'");

        // Re-add jarvies_status
        DB::statement("ALTER TABLE ticket ADD COLUMN jarvies_status VARCHAR(255) NULL AFTER status");

        // Best-effort reverse mapping
        DB::statement("UPDATE ticket SET status = 'open',       jarvies_status = 'sent it to support' WHERE status = 'open'");
        DB::statement("UPDATE ticket SET status = 'in_progress',jarvies_status = 'in process'         WHERE status = 'inprocess'");
        DB::statement("UPDATE ticket SET status = 'reply',      jarvies_status = 'author action'      WHERE status = 'waiting_on_customer'");
        DB::statement("UPDATE ticket SET status = 'in_progress',jarvies_status = 'sent in to SAP'     WHERE status = 'waiting_on_3rd_party'");
        DB::statement("UPDATE ticket SET status = 'wait_to_close',jarvies_status = 'proposed solution' WHERE status = 'waiting_to_confirmation'");
        DB::statement("UPDATE ticket SET status = 'cancel',     jarvies_status = NULL                  WHERE status = 'cancelled'");

        // Restore original ENUM
        DB::statement("ALTER TABLE ticket MODIFY COLUMN status
            ENUM('open','in_progress','hold','cancel','closed','reply','wait_to_close')
            NOT NULL DEFAULT 'open'");
    }
};
