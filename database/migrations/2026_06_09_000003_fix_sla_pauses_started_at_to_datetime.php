<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * TIMESTAMP columns in MySQL get implicit ON UPDATE CURRENT_TIMESTAMP behaviour
     * which overwrites started_at whenever ended_at / duration_hours are written.
     * Converting to DATETIME removes that behaviour permanently.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE ticket_sla_pauses MODIFY started_at DATETIME NOT NULL");
        DB::statement("ALTER TABLE ticket_sla_pauses MODIFY ended_at   DATETIME NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE ticket_sla_pauses MODIFY started_at TIMESTAMP NOT NULL");
        DB::statement("ALTER TABLE ticket_sla_pauses MODIFY ended_at   TIMESTAMP NULL");
    }
};
