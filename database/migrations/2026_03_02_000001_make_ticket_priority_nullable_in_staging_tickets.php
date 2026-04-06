<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ticket_priority di staging_tickets harus nullable karena email masuk
 * tidak memiliki priority — priority baru diset oleh helpdesk saat approve.
 *
 * Sebelumnya kolom ini NOT NULL default 'Medium', dan StagingTicketService
 * secara eksplisit mengisi null untuk email-channel → menyebabkan error 1048.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE staging_tickets MODIFY COLUMN ticket_priority ENUM('Low','Medium','High') NULL DEFAULT NULL");
    }

    public function down(): void
    {
        // Isi dulu kolom yang null agar tidak error saat revert ke NOT NULL
        DB::statement("UPDATE staging_tickets SET ticket_priority = 'Medium' WHERE ticket_priority IS NULL");
        DB::statement("ALTER TABLE staging_tickets MODIFY COLUMN ticket_priority ENUM('Low','Medium','High') NOT NULL DEFAULT 'Medium'");
    }
};
