<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tambah nilai 'Very High' ke enum ticket_priority pada tabel:
 *   - staging_tickets
 *   - ticket
 *
 * Sebelumnya enum hanya: Low, Medium, High
 * Controller sudah menerima 'Very High' dalam validasi sejak awal,
 * namun DB menolaknya → runtime error saat insert.
 * JARVIES bisa mengirim priority 'Very High' dari form customer.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE staging_tickets MODIFY COLUMN ticket_priority ENUM('Low','Medium','High','Very High') NULL DEFAULT NULL");
        DB::statement("ALTER TABLE ticket MODIFY COLUMN ticket_priority ENUM('Low','Medium','High','Very High') NULL");
    }

    public function down(): void
    {
        // Set 'Very High' ke 'High' sebelum hapus nilai dari enum
        DB::statement("UPDATE staging_tickets SET ticket_priority = 'High' WHERE ticket_priority = 'Very High'");
        DB::statement("UPDATE ticket SET ticket_priority = 'High' WHERE ticket_priority = 'Very High'");

        DB::statement("ALTER TABLE staging_tickets MODIFY COLUMN ticket_priority ENUM('Low','Medium','High') NULL DEFAULT NULL");
        DB::statement("ALTER TABLE ticket MODIFY COLUMN ticket_priority ENUM('Low','Medium','High') NULL");
    }
};
