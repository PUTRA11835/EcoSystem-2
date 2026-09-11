<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Roles 5 (HOS), 6 (Helpdesk), 7 (RPMO) perlu can_view ke parent 'management' group
     * agar "Hidden Tickets" muncul di sidebar mereka.
     * Mereka hanya akan melihat sub-item yang memang mereka punya akses (management.hidden-tickets).
     */
    public function up(): void
    {
        $now = now();

        $management = DB::table('menu')->where('slug', 'management')->first();
        if (!$management) {
            return;
        }

        foreach ([5, 6, 7] as $roleId) {
            DB::table('role_menu')->updateOrInsert(
                ['role_id' => $roleId, 'menu_id' => $management->id],
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
        $management = DB::table('menu')->where('slug', 'management')->first();
        if (!$management) {
            return;
        }

        DB::table('role_menu')
            ->whereIn('role_id', [5, 6, 7])
            ->where('menu_id', $management->id)
            ->delete();
    }
};
