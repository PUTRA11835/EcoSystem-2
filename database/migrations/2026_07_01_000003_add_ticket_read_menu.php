<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // ── ticket.read — function menu (parent: tickets.inbox) ──────────────────
        $ticketsInbox = DB::table('menu')->where('slug', 'tickets.inbox')->first();
        if ($ticketsInbox) {
            $existing = DB::table('menu')->where('slug', 'ticket.read')->first();
            if (!$existing) {
                $readMenuId = DB::table('menu')->insertGetId([
                    'parent_id'  => $ticketsInbox->id,
                    'name'       => 'Read Ticket',
                    'slug'       => 'ticket.read',
                    'type'       => 'function',
                    'route_name' => null,
                    'icon'       => null,
                    'order_seq'  => 20,
                    'is_active'  => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                $readMenuId = $existing->id;
            }

            // Grant ticket.read ke role: 1 (Admin), 5 (Support Head), 6 (Helpdesk), 7 (RPMO)
            $this->grantRoles($readMenuId, [1, 5, 6, 7], $now);
        }
    }

    public function down(): void
    {
        $menu = DB::table('menu')->where('slug', 'ticket.read')->first();
        if ($menu) {
            DB::table('role_menu')->where('menu_id', $menu->id)->delete();
            DB::table('menu')->where('id', $menu->id)->delete();
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
