<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE ticket MODIFY COLUMN channel ENUM('email','web','imported') NOT NULL DEFAULT 'web'");
    }

    public function down(): void
    {
        DB::statement("UPDATE ticket SET channel = 'web' WHERE channel = 'imported'");
        DB::statement("ALTER TABLE ticket MODIFY COLUMN channel ENUM('email','web') NOT NULL DEFAULT 'web'");
    }
};
