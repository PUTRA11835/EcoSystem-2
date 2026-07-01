<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // ── 1. ticket.hide — function menu (parent: tickets.inbox) ───────────────
        $ticketsInbox = DB::table('menu')->where('slug', 'tickets.inbox')->first();
        if ($ticketsInbox) {
            $existing = DB::table('menu')->where('slug', 'ticket.hide')->first();
            if (!$existing) {
                $hideMenuId = DB::table('menu')->insertGetId([
                    'parent_id'  => $ticketsInbox->id,
                    'name'       => 'Hide Ticket',
                    'slug'       => 'ticket.hide',
                    'type'       => 'function',
                    'route_name' => null,
                    'icon'       => null,
                    'order_seq'  => 19,
                    'is_active'  => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $hideMenuId = $existing->id;
            }

            // Grant ticket.hide ke role: 1 (Admin), 5 (Support Head), 6 (Helpdesk), 7 (RPMO)
            $this->grantRoles($hideMenuId, [1, 5, 6, 7], $now);
        }

        // ── 2. management.hidden-tickets — page menu (parent: management) ────────
        $management = DB::table('menu')->where('slug', 'management')->first();
        if ($management) {
            $existing = DB::table('menu')->where('slug', 'management.hidden-tickets')->first();
            if (!$existing) {
                $hiddenTicketsMenuId = DB::table('menu')->insertGetId([
                    'parent_id'  => $management->id,
                    'name'       => 'Hidden Tickets',
                    'slug'       => 'management.hidden-tickets',
                    'type'       => 'page',
                    'route_name' => 'management.hidden-tickets.index',
                    'icon'       => null,
                    'order_seq'  => 5,
                    'is_active'  => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $hiddenTicketsMenuId = $existing->id;
            }

            // Grant management.hidden-tickets ke role yang sama dengan ticket.hide
            $this->grantRoles($hiddenTicketsMenuId, [1, 5, 6, 7], $now);
        }
    }

    public function down(): void
    {
        foreach (['ticket.hide', 'management.hidden-tickets'] as $slug) {
            $menu = DB::table('menu')->where('slug', $slug)->first();
            if ($menu) {
                DB::table('role_menu')->where('menu_id', $menu->id)->delete();
                DB::table('menu')->where('id', $menu->id)->delete();
            }
        }
    }

    private function grantRoles(int $menuId, array $roleIds, $now): void
    {
        foreach ($roleIds as $roleId) {
            DB::table('role_menu')->updateOrInsert(
                ['role_id' => $roleId, 'menu_id' => $menuId],
                [
                    'can_view'   => true,
                    'can_create' => false,
                    'can_edit'   => false,
                    'can_delete' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
};
