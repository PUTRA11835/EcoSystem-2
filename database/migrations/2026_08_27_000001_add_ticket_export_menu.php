<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Slug izin Ticket → Export Excel.
 *
 * Sebelumnya akses export di-hardcode ke 3 role (EC Administrator, Delivery
 * Support Head, Delivery Support Service Helpdesk) langsung di Blade +
 * controller — tidak bisa diatur lewat Control Center → Menu Access. Slug
 * ini menggantikan hardcode itu dengan grant yang PERSIS SAMA (bukan
 * admin-only default), supaya tidak ada role yang mendadak kehilangan akses
 * saat migrasi ini jalan. Perubahan akses selanjutnya lewat Control Center.
 */
return new class extends Migration
{
    private const SLUG = 'ticket.export';

    public function up(): void
    {
        $registered = MenuRegistrar::register('tickets.inbox', [
            self::SLUG => 'Export Ticket',
        ], 27, 'function');

        if ($registered) {
            MenuRegistrar::grantToAdminAndRoles([self::SLUG], [
                'Delivery Support Head',
                'Delivery Support Service Helpdesk',
            ]);
        }
    }

    public function down(): void
    {
        MenuRegistrar::remove([self::SLUG]);
    }
};
