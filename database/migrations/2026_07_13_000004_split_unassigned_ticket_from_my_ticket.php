<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Unassigned Ticket is its own filter/tab, not a scope variant of "My Ticket".
 * Rename the menu slug so it's no longer grouped under the ticket.my-tickets.* family,
 * and it now gates a dedicated endpoint (GET /api/tickets/unassigned) instead of being
 * one of the branches inside TicketController::myTickets().
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('menu')->where('slug', 'ticket.my-tickets.unassigned')->update([
            'slug'       => 'ticket.unassigned',
            'name'       => 'Unassigned Ticket',
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menu')->where('slug', 'ticket.unassigned')->update([
            'slug'       => 'ticket.my-tickets.unassigned',
            'name'       => 'My Ticket (Unassigned)',
            'updated_at' => now(),
        ]);
    }
};
