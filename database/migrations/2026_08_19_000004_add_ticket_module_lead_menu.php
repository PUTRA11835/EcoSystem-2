<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Tab "Ticket Modul" di halaman Ticket — hanya muncul untuk employee yang
 * memang jadi lead di minimal satu module (dicek runtime di TicketViewController,
 * bukan lewat menu ini). Menu ini hanya mengontrol ROLE mana yang boleh melihat
 * tab tersebut kalau mereka module lead; siapa saja yang module lead tetap
 * ditentukan lewat tabel module_leads.
 *
 * Lahir admin-only sesuai aturan baku MenuRegistrar — pemilik sistem yang
 * menentukan role mana yang di-grant lewat Control Center > Menu Access.
 */
return new class extends Migration
{
    public function up(): void
    {
        MenuRegistrar::register('tickets.inbox', [
            'ticket.module-lead' => 'Ticket Modul',
        ], 30);
    }

    public function down(): void
    {
        MenuRegistrar::remove(['ticket.module-lead']);
    }
};
