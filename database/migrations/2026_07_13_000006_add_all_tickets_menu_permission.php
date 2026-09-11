<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "All Tickets" tab is now gated by its own menu permission (Role & Menu Access),
 * for every internal role — including Admin/Head/Helpdesk, not just DS User/Manager.
 * Grant it by default to every role that currently has 'tickets.inbox' access, so
 * nobody loses access to the ticket list they already had after this deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        $parent = DB::table('menu')->where('slug', 'tickets.inbox')->value('id');
        if (!$parent) return;

        $menuId = DB::table('menu')->where('slug', 'ticket.all-tickets')->value('id');
        if (!$menuId) {
            $menuId = DB::table('menu')->insertGetId([
                'slug'       => 'ticket.all-tickets',
                'name'       => 'All Tickets',
                'type'       => 'function',
                'parent_id'  => $parent,
                'route_name' => null,
                'icon'       => null,
                'order_seq'  => 5,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $roleIdsWithTicketsInbox = DB::table('role_menu')
            ->where('menu_id', $parent)
            ->where('can_view', true)
            ->pluck('role_id');

        $now = now();
        foreach ($roleIdsWithTicketsInbox as $roleId) {
            DB::table('role_menu')->updateOrInsert(
                ['role_id' => $roleId, 'menu_id' => $menuId],
                ['can_view' => true, 'can_create' => false, 'can_edit' => false, 'can_delete' => false, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        DB::table('menu')->where('slug', 'ticket.all-tickets')->delete();
    }
};
