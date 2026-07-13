<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ticket.view-all / ticket.view-own / ticket.view-team were seeded years ago but never
 * read by any controller or view — toggling them in Role & Menu Access had no effect.
 * Remove them and take over their list position (order_seq 2-4) with the My Ticket
 * scope permissions added in the previous two migrations, which ARE wired into
 * TicketController::myTickets().
 */
return new class extends Migration
{
    public function up(): void
    {
        // role_menu rows cascade-delete via FK when the menu row is removed.
        DB::table('menu')->whereIn('slug', ['ticket.view-all', 'ticket.view-own', 'ticket.view-team'])->delete();

        $now = now();
        $positions = [
            'ticket.my-tickets.ds-user'    => 2,
            'ticket.my-tickets.ds-manager' => 3,
            'ticket.my-tickets.unassigned' => 4,
        ];
        foreach ($positions as $slug => $orderSeq) {
            DB::table('menu')->where('slug', $slug)->update(['order_seq' => $orderSeq, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        $now = now();
        DB::table('menu')->where('slug', 'ticket.my-tickets.ds-user')->update(['order_seq' => 20, 'updated_at' => $now]);
        DB::table('menu')->where('slug', 'ticket.my-tickets.ds-manager')->update(['order_seq' => 21, 'updated_at' => $now]);
        DB::table('menu')->where('slug', 'ticket.my-tickets.unassigned')->update(['order_seq' => 22, 'updated_at' => $now]);

        $parent = DB::table('menu')->where('slug', 'tickets.inbox')->value('id');
        if (!$parent) return;

        DB::table('menu')->insert([
            ['slug' => 'ticket.view-all',  'name' => 'View All Tickets',  'type' => 'function', 'parent_id' => $parent, 'route_name' => null, 'icon' => null, 'order_seq' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'ticket.view-own',  'name' => 'View Own Tickets',  'type' => 'function', 'parent_id' => $parent, 'route_name' => null, 'icon' => null, 'order_seq' => 3, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'ticket.view-team', 'name' => 'View Team Tickets', 'type' => 'function', 'parent_id' => $parent, 'route_name' => null, 'icon' => null, 'order_seq' => 4, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
};
