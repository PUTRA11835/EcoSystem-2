<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Menu Access of Offering Letter and Letter Templates, and their starting grants.
 *
 *   HR & General
 *   ├─ Offering Letter               general.offering-letter (group)
 *   │   ├─ Offering Letter Table     general.recruitment.offers             (exists)
 *   │   └─ Offering Settings         general.recruitment.offers.settings    (exists)
 *   └─ Letter Templates              general.letters (group)
 *       ├─ Dashboard                 general.letters.dashboard
 *       ├─ Requests                  general.letters.requests
 *       ├─ Letter Register           general.letters.register
 *       ├─ Create Letter             general.letters.compose
 *       └─ Settings                  general.letter-templates               (exists)
 *   My Letter Requests               general.my-letter-requests (self-service, top level)
 *
 * The existing slugs keep their slug and V / C / E / D grants; they are only
 * named after their tab and put under a group. A group carries View only, and
 * every role that can open one of its tabs receives it, so a ticked tab never
 * sits under an unticked parent. The Dashboard has a slug of its own rather
 * than the group's: the group is given to every role with any tab.
 *
 * Starting grants of the new slugs — from here on maintained in Management → Roles:
 *   hub tabs            EC Administrator only, view / create / edit / delete
 *   My Letter Requests  EC Administrator and User System Registered — the role
 *                       every employee holds and every new hire is given —
 *                       view / create / edit / delete (own requests only)
 */
return new class extends Migration
{
    private const CRUD = ['create', 'edit', 'delete'];

    /** group slug => [name, order_seq under HR & General, [tab slug => [name, order_seq, old name, old order_seq]]] */
    private const GROUPS = [
        'general.offering-letter' => ['Offering Letter', 75, [
            'general.recruitment.offers'          => ['Offering Letter Table', 1, 'Offering Letter — Letters', 75],
            'general.recruitment.offers.settings' => ['Offering Settings', 2, 'Offering Letter — Settings', 76],
        ]],
        'general.letters' => ['Letter Templates', 77, [
            'general.letter-templates' => ['Settings', 5, 'Letter Templates — Settings', 77],
        ]],
    ];

    /** New tabs of Letter Templates: slug => [name, order_seq in the group] */
    private const LETTER_TABS = [
        'general.letters.dashboard' => ['Dashboard', 1],
        'general.letters.requests'  => ['Requests', 2],
        'general.letters.register'  => ['Letter Register', 3],
        'general.letters.compose'   => ['Create Letter', 4],
    ];

    private const ESS = 'general.my-letter-requests';

    public function up(): void
    {
        $this->groupMenus();

        if (DB::table('menu')->where('slug', 'general.letters')->exists()) {
            foreach (self::LETTER_TABS as $slug => [$name, $seq]) {
                MenuRegistrar::register('general.letters', [$slug => $name], $seq, 'page');
            }
            MenuRegistrar::grantToAdminAndRoles(array_keys(self::LETTER_TABS), [], self::CRUD);
        }

        // Self-service: a top-level page like My Reimbursement / My Purchase Request.
        if (MenuRegistrar::register('general', [self::ESS => 'My Letter Requests'], 90, 'page')) {
            DB::table('menu')->where('slug', self::ESS)->update(['parent_id' => null, 'order_seq' => 6, 'updated_at' => now()]);
            MenuRegistrar::grantToAdminAndRoles([self::ESS], ['User System Registered'], self::CRUD);
        }

        $this->flushPermCache();
    }

    public function down(): void
    {
        MenuRegistrar::remove([...array_keys(self::LETTER_TABS), self::ESS]);
        $this->ungroupMenus();
        $this->flushPermCache();
    }

    private function groupMenus(): void
    {
        $now = now();

        foreach (self::GROUPS as $groupSlug => [$groupName, $seq, $tabs]) {
            if (!MenuRegistrar::register('general', [$groupSlug => $groupName], $seq, 'group')) {
                continue;
            }

            $groupId = DB::table('menu')->where('slug', $groupSlug)->value('id');

            foreach ($tabs as $slug => [$name, $tabSeq]) {
                DB::table('menu')->where('slug', $slug)->update([
                    'parent_id' => $groupId, 'name' => $name, 'order_seq' => $tabSeq, 'updated_at' => $now,
                ]);
            }

            $roleIds = DB::table('role_menu')
                ->join('menu', 'menu.id', '=', 'role_menu.menu_id')
                ->whereIn('menu.slug', array_keys($tabs))
                ->where('role_menu.can_view', true)
                ->distinct()
                ->pluck('role_menu.role_id');

            foreach ($roleIds as $roleId) {
                DB::table('role_menu')->updateOrInsert(
                    ['role_id' => $roleId, 'menu_id' => $groupId],
                    ['can_view' => true, 'can_create' => false, 'can_edit' => false, 'can_delete' => false, 'created_at' => $now, 'updated_at' => $now]
                );
            }
        }
    }

    private function ungroupMenus(): void
    {
        $parentId = DB::table('menu')->where('slug', 'general')->value('id');

        foreach (self::GROUPS as [, , $tabs]) {
            foreach ($tabs as $slug => [, , $oldName, $oldSeq]) {
                DB::table('menu')->where('slug', $slug)->update([
                    'parent_id' => $parentId, 'name' => $oldName, 'order_seq' => $oldSeq, 'updated_at' => now(),
                ]);
            }
        }

        MenuRegistrar::remove(array_keys(self::GROUPS));
    }

    private function flushPermCache(): void
    {
        DB::table('employee_role_assignment')->pluck('employee_id')->unique()->each(function ($employeeId) {
            Cache::forget("perm_slugs_{$employeeId}");
            Cache::forget("perm_matrix_{$employeeId}");
        });
    }
};
