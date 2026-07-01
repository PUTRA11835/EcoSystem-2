<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $menu = DB::table('menu')->where('slug', 'ticket.assign-pic')->first();
        if (!$menu) {
            return;
        }

        $now = now();
        DB::table('role_menu')->updateOrInsert(
            ['role_id' => 14, 'menu_id' => $menu->id],
            ['can_view' => true, 'can_create' => false, 'can_edit' => false, 'can_delete' => false, 'created_at' => $now, 'updated_at' => $now]
        );
    }

    public function down(): void
    {
        $menu = DB::table('menu')->where('slug', 'ticket.assign-pic')->first();
        if ($menu) {
            DB::table('role_menu')->where('role_id', 14)->where('menu_id', $menu->id)->delete();
        }
    }
};
