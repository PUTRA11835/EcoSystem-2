<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $parent = DB::table('menu')->where('slug', 'tickets.inbox')->value('id');
        if (!$parent) return;

        if (DB::table('menu')->where('slug', 'ticket.activity-log')->exists()) return;

        DB::table('menu')->insert([
            'slug'       => 'ticket.activity-log',
            'name'       => 'Ticket Activity Log',
            'type'       => 'function',
            'parent_id'  => $parent,
            'route_name' => null,
            'icon'       => null,
            'order_seq'  => 20,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('menu')->where('slug', 'ticket.activity-log')->delete();
    }
};
