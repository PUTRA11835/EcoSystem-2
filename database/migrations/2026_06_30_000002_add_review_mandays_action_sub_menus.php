<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $parent = DB::table('menu')->where('slug', 'ticket.review-mandays')->first();
        if (!$parent) {
            return;
        }

        $subMenus = [
            ['slug' => 'ticket.review-mandays.send-to-customer', 'name' => 'Send to Customer', 'order_seq' => 3],
            ['slug' => 'ticket.review-mandays.approve',          'name' => 'Approve Proposal', 'order_seq' => 4],
            ['slug' => 'ticket.review-mandays.cancel',           'name' => 'Cancel Proposal',  'order_seq' => 5],
        ];

        foreach ($subMenus as $menu) {
            $existing = DB::table('menu')->where('slug', $menu['slug'])->first();
            if ($existing) {
                $menuId = $existing->id;
            } else {
                $menuId = DB::table('menu')->insertGetId([
                    'parent_id'  => $parent->id,
                    'name'       => $menu['name'],
                    'slug'       => $menu['slug'],
                    'type'       => 'function',
                    'route_name' => null,
                    'icon'       => null,
                    'order_seq'  => $menu['order_seq'],
                    'is_active'  => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ([1, 6] as $roleId) {
                DB::table('role_menu')->updateOrInsert(
                    ['role_id' => $roleId, 'menu_id' => $menuId],
                    ['can_view' => true, 'can_create' => false, 'can_edit' => false, 'can_delete' => false, 'created_at' => $now, 'updated_at' => $now]
                );
            }
        }
    }

    public function down(): void
    {
        $slugs = [
            'ticket.review-mandays.send-to-customer',
            'ticket.review-mandays.approve',
            'ticket.review-mandays.cancel',
        ];

        foreach ($slugs as $slug) {
            $menu = DB::table('menu')->where('slug', $slug)->first();
            if ($menu) {
                DB::table('role_menu')->where('menu_id', $menu->id)->delete();
                DB::table('menu')->where('id', $menu->id)->delete();
            }
        }
    }
};
