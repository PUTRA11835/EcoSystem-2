<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $parent = DB::table('menu')->where('slug', 'tickets.inbox')->value('id');
        if (!$parent) return;

        if (DB::table('menu')->where('slug', 'ticket.my-tickets.unassigned')->exists()) return;

        DB::table('menu')->insert([
            'slug'       => 'ticket.my-tickets.unassigned',
            'name'       => 'My Ticket (Unassigned)',
            'type'       => 'function',
            'parent_id'  => $parent,
            'route_name' => null,
            'icon'       => null,
            'order_seq'  => 22,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // No default grant — admin assigns this permission manually per role via Role & Menu Access.
    }

    public function down(): void
    {
        DB::table('menu')->where('slug', 'ticket.my-tickets.unassigned')->delete();
    }
};
