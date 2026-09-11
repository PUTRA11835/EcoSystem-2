<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ticket.propose-mandays was the original permission for the Propose Mandays UI, but the
 * 2026-07-16 split into ticket.propose-mandays-customer / -resolution replaced it in every
 * UI check. It kept being granted to role EMPLOYEE and was quietly repurposed in
 * show.blade.php's $canManageMembers as a "is this role a DS User" marker — toggling it in
 * Role & Menu Access no longer affected Propose Mandays visibility, only Manage Members.
 * That coupling has been removed (manage-members now checks the role directly), so this
 * dead menu slug can go.
 */
return new class extends Migration
{
    public function up(): void
    {
        // role_menu rows cascade-delete via FK when the menu row is removed.
        DB::table('menu')->where('slug', 'ticket.propose-mandays')->delete();
    }

    public function down(): void
    {
        $parent = DB::table('menu')->where('slug', 'tickets.inbox')->value('id');
        if (!$parent) {
            return;
        }

        $now = now();
        $menuId = DB::table('menu')->insertGetId([
            'parent_id'  => $parent,
            'name'       => 'Propose Mandays',
            'slug'       => 'ticket.propose-mandays',
            'type'       => 'function',
            'route_name' => null,
            'icon'       => null,
            'order_seq'  => 13,
            'is_active'  => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $employeeRoleId = DB::table('employee_role')->where('id', 2)->value('id');
        if ($employeeRoleId) {
            DB::table('role_menu')->insert([
                'role_id'    => $employeeRoleId,
                'menu_id'    => $menuId,
                'can_view'   => true,
                'can_create' => false,
                'can_edit'   => false,
                'can_delete' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
