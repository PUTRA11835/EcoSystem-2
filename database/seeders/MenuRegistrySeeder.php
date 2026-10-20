<?php

namespace Database\Seeders;

use App\Support\MenuRegistrar;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Menu / permission slugs that came after MenuSeeder (offboarding, legal basis, contracts, payroll tabs, external, approvals, …).
 *
 * On a clean install the migrations run while the `menu` table is still empty, so every MenuRegistrar::register() call in them is skipped
 * (its parent does not exist yet) — and MenuSeeder only knows the older list. This seeder fills the gap from database/data/menu_registry.php
 * (written by `php artisan menu:export-registry`).
 *
 * Safe to run any time: a slug that already exists is not touched (name, parent, order and every role grant stay), and a slug it creates
 * follows the standing rule — active for EC Administrator only, off for every other role; the owner decides the rest in Menu Access.
 *
 *   php artisan db:seed --class=MenuRegistrySeeder
 */
class MenuRegistrySeeder extends Seeder
{
    public function run(): void
    {
        $registry = require database_path('data/menu_registry.php');
        $idBySlug = DB::table('menu')->pluck('id', 'slug')->all();
        $created = [];
        $skipped = 0;

        foreach ($registry as $row) {
            if (isset($idBySlug[$row['slug']])) {
                continue;
            }

            $parentId = null;
            if ($row['parent'] !== null) {
                $parentId = $idBySlug[$row['parent']] ?? null;
                if ($parentId === null) {   // parent missing and not in the registry either: skip rather than orphan the row
                    $skipped++;
                    continue;
                }
            }

            $idBySlug[$row['slug']] = DB::table('menu')->insertGetId([
                'parent_id'  => $parentId,
                'name'       => $row['name'],
                'slug'       => $row['slug'],
                'type'       => $row['type'],
                'route_name' => $row['route'],
                'icon'       => $row['icon'],
                'order_seq'  => $row['seq'],
                'is_active'  => $row['active'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $created[] = $row['slug'];
        }

        if ($created) {
            MenuRegistrar::grantToAdminOnly($created);
        }

        $this->command?->info(count($created) . ' menu slug(s) added, ' . (count($registry) - count($created) - $skipped) . ' already present'
            . ($skipped ? ", {$skipped} skipped (parent missing)" : '') . '.');
    }
}
