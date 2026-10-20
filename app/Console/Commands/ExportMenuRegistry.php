<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Writes database/data/menu_registry.php from the `menu` table: every page, tab, group and permission slug, parents first.
 * Run it after a migration adds menus, so a clean install (MenuRegistrySeeder) gets the same slugs as this database.
 */
class ExportMenuRegistry extends Command
{
    protected $signature = 'menu:export-registry';

    protected $description = 'Export the menu / permission-slug table to database/data/menu_registry.php (read by MenuRegistrySeeder)';

    public function handle(): int
    {
        $rows = DB::table('menu')->orderBy('id')->get();
        $byId = $rows->keyBy('id');
        $depth = function ($r) use (&$depth, $byId): int {
            return $r->parent_id && isset($byId[$r->parent_id]) ? 1 + $depth($byId[$r->parent_id]) : 0;
        };

        $list = $rows->sortBy(fn ($r) => sprintf('%02d-%08d', $depth($r), $r->id))->values()->map(fn ($r) => [
            'slug'   => $r->slug,
            'name'   => $r->name,
            'type'   => $r->type,
            'parent' => $r->parent_id && isset($byId[$r->parent_id]) ? $byId[$r->parent_id]->slug : null,
            'route'  => $r->route_name,
            'icon'   => $r->icon,
            'seq'    => (int) $r->order_seq,
            'active' => (bool) $r->is_active,
        ])->all();

        $header = "<?php\n\n/*\n * Menu / permission-slug registry — every row of the `menu` table (pages, tabs, groups and function slugs), parents first.\n"
            . ' * Exported ' . date('Y-m-d') . " by `php artisan menu:export-registry`. Read by Database\\Seeders\\MenuRegistrySeeder, which adds only the\n"
            . " * rows that are missing; existing rows and every role grant are left alone.\n */\n\nreturn ";

        file_put_contents(database_path('data/menu_registry.php'), $header . var_export($list, true) . ";\n");
        $this->info(count($list) . ' menu rows written to database/data/menu_registry.php');

        return self::SUCCESS;
    }
}
