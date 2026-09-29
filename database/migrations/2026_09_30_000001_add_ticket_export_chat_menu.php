<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Slug izin tombol "Export Chat" di headbar room chat tiket — unduh percakapan
 * tiket (tanpa internal note) sebagai PDF.
 *
 * Mengikuti aturan baku MenuRegistrar: lahir aktif HANYA untuk EC Administrator.
 * Role lain diberi akses lewat Control Center → Menu Access.
 */
return new class extends Migration
{
    private const SLUG = 'ticket.export-chat';

    public function up(): void
    {
        MenuRegistrar::register('tickets.inbox', [
            self::SLUG => 'Export Chat (PDF)',
        ], 31);
    }

    public function down(): void
    {
        MenuRegistrar::remove([self::SLUG]);
    }
};
