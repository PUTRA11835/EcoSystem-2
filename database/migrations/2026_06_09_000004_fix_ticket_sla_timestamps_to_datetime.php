<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * ticket_sla.sla_start_at is TIMESTAMP NOT NULL — MySQL's first NOT NULL TIMESTAMP
     * column gets implicit ON UPDATE CURRENT_TIMESTAMP, overwriting sla_start_at
     * (the SLA clock baseline) on every row update (ball_holder changes, pauses, etc.).
     * Convert all TIMESTAMP columns in ticket_sla to DATETIME to remove this behavior.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE ticket_sla
            MODIFY sla_start_at      DATETIME NOT NULL,
            MODIFY response_due_at   DATETIME NULL,
            MODIFY resolution_due_at DATETIME NULL,
            MODIFY first_responded_at DATETIME NULL,
            MODIFY session_start_at   DATETIME NULL,
            MODIFY solution_started_at DATETIME NULL,
            MODIFY resolved_at        DATETIME NULL,
            MODIFY sla_paused_at      DATETIME NULL,
            MODIFY created_at         DATETIME NULL,
            MODIFY updated_at         DATETIME NULL
        ");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE ticket_sla
            MODIFY sla_start_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            MODIFY response_due_at   TIMESTAMP NULL,
            MODIFY resolution_due_at TIMESTAMP NULL,
            MODIFY first_responded_at TIMESTAMP NULL,
            MODIFY session_start_at   TIMESTAMP NULL,
            MODIFY solution_started_at TIMESTAMP NULL,
            MODIFY resolved_at        TIMESTAMP NULL,
            MODIFY sla_paused_at      TIMESTAMP NULL,
            MODIFY created_at         TIMESTAMP NULL,
            MODIFY updated_at         TIMESTAMP NULL
        ");
    }
};
