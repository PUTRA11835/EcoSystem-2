<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Slug izin untuk tombol "AI Summarize" di daftar tiket.
 *
 * Sesuai aturan baku proyek, slug baru lahir aktif HANYA untuk EC Administrator
 * dan mati untuk seluruh role lain - pemilik sistem yang memutuskan siapa boleh
 * memakainya lewat Control Center > Menu Access. Tombol ini memicu panggilan AI
 * berbayar, jadi justru tidak boleh menyebar diam-diam.
 */
return new class extends Migration
{
    public function up(): void
    {
        MenuRegistrar::register('tickets.inbox', [
            'ui.ticket.btn-ai-summarize' => 'AI Summarize Ticket',
        ], 19);
    }

    public function down(): void
    {
        MenuRegistrar::remove(['ui.ticket.btn-ai-summarize']);
    }
};
