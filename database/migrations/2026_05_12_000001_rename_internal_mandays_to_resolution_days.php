<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // renameColumn on ENUM breaks default value quoting in some MySQL versions — use raw DDL instead
        DB::statement("ALTER TABLE `ticket` CHANGE `internal_mandays_status` `resolution_days_status` ENUM('none','draft','pending_head','approved','rejected') NOT NULL DEFAULT 'none'");

        DB::table('notifications')
            ->where('type', 'internal_mandays_proposed')
            ->update(['type' => 'resolution_days_proposed']);
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `ticket` CHANGE `resolution_days_status` `internal_mandays_status` ENUM('none','draft','pending_head','approved','rejected') NOT NULL DEFAULT 'none'");

        DB::table('notifications')
            ->where('type', 'resolution_days_proposed')
            ->update(['type' => 'internal_mandays_proposed']);
    }
};
