<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Helpdesk's "Unassigned" tab was previously hardcoded (always visible, no permission
 * check). Now that it's gated behind the 'ticket.unassigned' menu permission, grant it
 * by default to Helpdesk(6) so their existing behavior is preserved after this deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        $menuId = DB::table('menu')->where('slug', 'ticket.unassigned')->value('id');
        if (!$menuId) return;

        $now = now();
        DB::table('role_menu')->updateOrInsert(
            ['role_id' => 6, 'menu_id' => $menuId],
            ['can_view' => true, 'can_create' => false, 'can_edit' => false, 'can_delete' => false, 'created_at' => $now, 'updated_at' => $now]
        );
    }

    public function down(): void
    {
        $menuId = DB::table('menu')->where('slug', 'ticket.unassigned')->value('id');
        if (!$menuId) return;

        DB::table('role_menu')->where('role_id', 6)->where('menu_id', $menuId)->delete();
    }
};
