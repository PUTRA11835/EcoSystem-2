<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Slug izin Delivery Support → Remove Ticket from DS.
 *
 * Sebelumnya tombol "Remove Ticket" di halaman detail Delivery Support
 * di-hardcode ke role EC Administrator (RoleId::EC_ADMINISTRATOR) langsung di
 * Blade (`$isEcAdmin`) + controller (`removeTicketLink`) — tidak bisa diatur
 * lewat Control Center → Menu Access.
 *
 * Slug ini menggantikan hardcode itu. Keadaan awalnya PERSIS sama dengan
 * perilaku lama: hanya EC Administrator yang punya grant (default
 * MenuRegistrar::register). Pemberian akses ke role lain selanjutnya lewat
 * Control Center → Menu Access.
 */
return new class extends Migration
{
    private const SLUG = 'delivery-support.remove-ticket';

    public function up(): void
    {
        MenuRegistrar::register('delivery.support', [
            self::SLUG => 'Remove Ticket from DS',
        ], 11, 'function');
    }

    public function down(): void
    {
        MenuRegistrar::remove([self::SLUG]);
    }
};
