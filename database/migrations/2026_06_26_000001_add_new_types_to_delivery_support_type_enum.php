<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE delivery_support MODIFY COLUMN type ENUM('AMS','MO','ATS','CR','RISE','CLOUD','POSTPAID','Project','Internal') NOT NULL");
    }

    public function down(): void
    {
        // Rows with new types will be lost if downgraded — convert first
        DB::statement("UPDATE delivery_support SET type = 'Internal' WHERE type IN ('CR','RISE','CLOUD','POSTPAID')");
        DB::statement("ALTER TABLE delivery_support MODIFY COLUMN type ENUM('AMS','MO','ATS','Project','Internal') NOT NULL");
    }
};
