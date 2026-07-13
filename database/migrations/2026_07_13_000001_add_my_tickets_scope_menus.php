<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $parent = DB::table('menu')->where('slug', 'tickets.inbox')->value('id');
        if (!$parent) return;

        $now = now();

        $menus = [
            'ticket.my-tickets.ds-user'    => ['name' => 'My Ticket (DS User)', 'order_seq' => 20],
            'ticket.my-tickets.ds-manager' => ['name' => 'My Ticket (DS Manager)', 'order_seq' => 21],
        ];

        $menuIds = [];
        foreach ($menus as $slug => $menu) {
            $existingId = DB::table('menu')->where('slug', $slug)->value('id');
            if ($existingId) {
                $menuIds[$slug] = $existingId;
                continue;
            }
            $menuIds[$slug] = DB::table('menu')->insertGetId([
                'slug'       => $slug,
                'name'       => $menu['name'],
                'type'       => 'function',
                'parent_id'  => $parent,
                'route_name' => null,
                'icon'       => null,
                'order_seq'  => $menu['order_seq'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Default grants preserve current hardcoded behavior:
        // DS User style (PIC/member query) — Delivery Support User(2), Helpdesk(6), RPMO Head(7).
        foreach ([2, 6, 7] as $roleId) {
            DB::table('role_menu')->updateOrInsert(
                ['role_id' => $roleId, 'menu_id' => $menuIds['ticket.my-tickets.ds-user']],
                ['can_view' => true, 'can_create' => false, 'can_edit' => false, 'can_delete' => false, 'created_at' => $now, 'updated_at' => $now]
            );
        }
        // DS Manager style (managed delivery support query) — Delivery Support Manager(14).
        DB::table('role_menu')->updateOrInsert(
            ['role_id' => 14, 'menu_id' => $menuIds['ticket.my-tickets.ds-manager']],
            ['can_view' => true, 'can_create' => false, 'can_edit' => false, 'can_delete' => false, 'created_at' => $now, 'updated_at' => $now]
        );
    }

    public function down(): void
    {
        DB::table('menu')->whereIn('slug', ['ticket.my-tickets.ds-user', 'ticket.my-tickets.ds-manager'])->delete();
    }
};
