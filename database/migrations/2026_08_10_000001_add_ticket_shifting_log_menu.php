<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Slug izin untuk dua tombol shortcut di headbar room chat tiket:
 *
 *   - ticket.sla-log      (sudah ada) → tombol "Log SLA", hanya ticket Incident
 *   - ticket.shifting-log (baru)      → tombol "Log Shifting", semua ticket type
 *
 * Keduanya dibatasi ke EC Administrator + Delivery Support Service Helpdesk
 * atas permintaan eksplisit pemilik sistem (10 Agu 2026). Untuk `ticket.sla-log`
 * ini berarti PENCABUTAN dari Delivery Support Head yang sebelumnya dapat grant
 * dari migrasi 2026_08_03_000001.
 */
return new class extends Migration
{
    private const ROLE = 'Delivery Support Service Helpdesk';

    public function up(): void
    {
        // register() membuat menu-nya dulu (grant awal admin-only), lalu
        // grantToAdminAndRoles() menetapkan daftar role finalnya.
        MenuRegistrar::register('tickets.inbox', [
            'ticket.shifting-log' => 'Log Shifting',
        ], 26);

        MenuRegistrar::grantToAdminAndRoles(
            ['ticket.sla-log', 'ticket.shifting-log'],
            [self::ROLE],
        );
    }

    public function down(): void
    {
        MenuRegistrar::remove(['ticket.shifting-log']);

        // Pulihkan ticket.sla-log ke keadaan sebelum migrasi ini:
        // EC Administrator + Delivery Support Head + Helpdesk.
        MenuRegistrar::grantToAdminAndRoles(
            ['ticket.sla-log'],
            ['Delivery Support Head', self::ROLE],
        );
    }
};
