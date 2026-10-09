<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Menu Access for General Affairs → Inventory & Assets, under the `general` root:
 *
 *   general.inventory            (group; parent of the tabs)
 *   ├─ general.inventory.overview  page   V the recap: totals, by category, what needs attention
 *   ├─ general.inventory.items     page   V list · C add a line · E edit / adjust stock · D delete a line
 *   ├─ general.inventory.assets    page   V list · C add an asset · E edit / assign · D delete an asset
 *   └─ general.inventory.settings  page   V see the dropdown lists · C add an option · E rename / re-order / switch off · D delete an unused option
 *
 * Standing rule of MenuRegistrar: a new slug starts active ONLY for EC Administrator; the owner shares it
 * from Control Center → Menu Access.
 */
return new class extends Migration
{
    private const PAGES = [
        'general.inventory.overview' => 'Overview',
        'general.inventory.items'    => 'Inventory',
        'general.inventory.assets'   => 'Assets',
        'general.inventory.settings' => 'Settings',
    ];

    public function up(): void
    {
        if (!MenuRegistrar::register('general', ['general.inventory' => 'Inventory & Assets'], 140, 'group')) {
            return;
        }
        MenuRegistrar::register('general.inventory', self::PAGES, 1, 'page');
        MenuRegistrar::grantToAdminAndRoles(['general.inventory.items', 'general.inventory.assets', 'general.inventory.settings'], [], ['create', 'edit', 'delete']);

        $this->flushPermCache();
    }

    public function down(): void
    {
        MenuRegistrar::remove([...array_keys(self::PAGES), 'general.inventory']);
        $this->flushPermCache();
    }

    private function flushPermCache(): void
    {
        DB::table('employee_role_assignment')->pluck('employee_id')->unique()->each(function ($employeeId) {
            Cache::forget("perm_slugs_{$employeeId}");
            Cache::forget("perm_matrix_{$employeeId}");
        });
    }
};
