<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE ticket MODIFY COLUMN status ENUM('open','in_progress','hold','cancel','closed','reply','wait_to_close') DEFAULT 'open'");
    }

    public function down(): void
    {
        // Tickets with wait_to_close will be set to hold before reverting
        DB::statement("UPDATE ticket SET status = 'hold' WHERE status = 'wait_to_close'");
        DB::statement("ALTER TABLE ticket MODIFY COLUMN status ENUM('open','in_progress','hold','cancel','closed','reply') DEFAULT 'open'");
    }
};
