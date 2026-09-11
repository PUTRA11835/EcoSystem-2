<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $parent = DB::table('menu')->where('slug', 'tickets.inbox')->first();
        if (!$parent) {
            return;
        }

        $existing = DB::table('menu')->where('slug', 'ticket.sla-log')->first();
        $menuId = $existing?->id ?? DB::table('menu')->insertGetId([
            'parent_id'  => $parent->id,
            'name'       => 'Log SLA',
            'slug'       => 'ticket.sla-log',
            'type'       => 'function',
            'route_name' => null,
            'icon'       => null,
            'order_seq'  => 25,
            'is_active'  => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // ticket.sla-log → role 1 (ADMIN), 5 (HOS), 6 (HELPDESK) — sama seperti sla.report
        foreach ([1, 5, 6] as $roleId) {
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

    public function down(): void
    {
        $menu = DB::table('menu')->where('slug', 'ticket.sla-log')->first();
        if ($menu) {
            DB::table('role_menu')->where('menu_id', $menu->id)->delete();
            DB::table('menu')->where('id', $menu->id)->delete();
        }
    }
};
