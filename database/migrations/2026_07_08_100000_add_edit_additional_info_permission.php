<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds a dedicated, configurable permission for editing a ticket's "Additional Info"
 * (Contact Name, Phone, Module, Client). Previously this was gated together with
 * Status/Priority/Type via `ui.ticket.edit-fields` on the UI and hardcoded to
 * admin/helpdesk on the server. Splitting it out lets admins control — via the
 * Management → Roles/Permissions screen — exactly which roles may edit Additional Info.
 *
 * Default grant = the roles that can effectively edit Additional Info today
 * (TICKET_MANAGER_GROUP: EC Administrator, Delivery Support Head, Helpdesk, RPMO Head)
 * so behaviour does not change until an admin reconfigures it.
 */
return new class extends Migration
{
    private string $slug = 'ui.ticket.edit-additional-info';

    public function up(): void
    {
        $now = now();

        $parent = DB::table('menu')->where('slug', 'tickets.inbox')->first();
        if (!$parent) {
            return;
        }

        $menuId = DB::table('menu')->where('slug', $this->slug)->value('id');
        if (!$menuId) {
            $menuId = DB::table('menu')->insertGetId([
                'parent_id'  => $parent->id,
                'name'       => 'Edit Additional Info',
                'slug'       => $this->slug,
                'type'       => 'function',
                'route_name' => null,
                'icon'       => null,
                'order_seq'  => 19,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Default: grant to current effective editors (RoleId::TICKET_MANAGER_GROUP = 1,5,6,7)
        foreach ([1, 5, 6, 7] as $roleId) {
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
        $menu = DB::table('menu')->where('slug', $this->slug)->first();
        if ($menu) {
            DB::table('role_menu')->where('menu_id', $menu->id)->delete();
            DB::table('menu')->where('id', $menu->id)->delete();
        }
    }
};
