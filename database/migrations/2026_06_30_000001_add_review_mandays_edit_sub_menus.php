<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $inbox = DB::table('menu')->where('slug', 'tickets.inbox')->first();
        if (!$inbox) {
            return;
        }

        // Pastikan ticket.review-mandays ada (mungkin migration sebelumnya skip)
        $reviewMandays = DB::table('menu')->where('slug', 'ticket.review-mandays')->first();
        if (!$reviewMandays) {
            $reviewMandaysId = DB::table('menu')->insertGetId([
                'parent_id'  => $inbox->id,
                'name'       => 'Review Mandays Proposal',
                'slug'       => 'ticket.review-mandays',
                'type'       => 'function',
                'route_name' => null,
                'icon'       => null,
                'order_seq'  => 14,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach ([1, 6, 7] as $roleId) {
                DB::table('role_menu')->updateOrInsert(
                    ['role_id' => $roleId, 'menu_id' => $reviewMandaysId],
                    ['can_view' => true, 'can_create' => false, 'can_edit' => false, 'can_delete' => false, 'created_at' => $now, 'updated_at' => $now]
                );
            }
        } else {
            $reviewMandaysId = $reviewMandays->id;
        }

        $subMenus = [
            ['slug' => 'ticket.review-mandays.edit-description',    'name' => 'Edit Proposal Description', 'order_seq' => 1],
            ['slug' => 'ticket.review-mandays.edit-proposal-notes', 'name' => 'Edit Proposal Notes',       'order_seq' => 2],
        ];

        foreach ($subMenus as $menu) {
            $existing = DB::table('menu')->where('slug', $menu['slug'])->first();
            if ($existing) {
                $menuId = $existing->id;
            } else {
                $menuId = DB::table('menu')->insertGetId([
                    'parent_id'  => $reviewMandaysId,
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
            'ticket.review-mandays.edit-description',
            'ticket.review-mandays.edit-proposal-notes',
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
