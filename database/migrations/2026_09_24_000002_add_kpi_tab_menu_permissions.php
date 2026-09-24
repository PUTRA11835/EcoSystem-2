<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * KPI module — make every tab a permission that Menu Access can toggle.
 *
 *   My KPI tabs       general.my-kpi.tab-self | tab-lead | tab-peer | tab-upward
 *   KPI Evaluation    general.kpi-evaluation.teams   (the "Lead & Project" tab)
 *
 * The other KPI tabs / actions were already registered (kpi-evaluation,
 * .templates, .templates.manage, .create, .review, .approve, .export).
 *
 * MenuRegistrar births new slugs EC-Administrator-only. These tabs already
 * existed for people, though, so — like the earlier KPI menu restructure —
 * the current access is carried over so nobody silently loses a tab:
 *   - `.teams` copies the grants of `general.kpi-evaluation`;
 *   - the My KPI tabs are viewable by every existing role (they were open to
 *     any employee before). Revoke per role in Management → Roles → Menu Access.
 */
return new class extends Migration
{
    private const MY_KPI_TABS = [
        'general.my-kpi.tab-self'   => 'My KPI — Self-Assessment tab',
        'general.my-kpi.tab-lead'   => 'My KPI — Lead Assessment tab (incl. My Team review)',
        'general.my-kpi.tab-peer'   => 'My KPI — Peer Assessment tab (My Team peer)',
        'general.my-kpi.tab-upward' => 'My KPI — Upward Assessment tab',
    ];

    private const TEAMS = 'general.kpi-evaluation.teams';

    public function up(): void
    {
        MenuRegistrar::register('general.my-kpi', self::MY_KPI_TABS, 10, 'function');
        MenuRegistrar::register('general.kpi-evaluation', [self::TEAMS => 'KPI Evaluation — Lead & Project'], 77, 'page');

        $now = now();

        // My KPI tabs: every existing role keeps seeing them.
        $roleIds = DB::table('employee_role')->pluck('id');
        $tabIds  = DB::table('menu')->whereIn('slug', array_keys(self::MY_KPI_TABS))->pluck('id');
        foreach ($tabIds as $menuId) {
            foreach ($roleIds as $roleId) {
                DB::table('role_menu')->updateOrInsert(
                    ['role_id' => $roleId, 'menu_id' => $menuId],
                    ['can_view' => true, 'can_create' => false, 'can_edit' => false, 'can_delete' => false, 'created_at' => $now, 'updated_at' => $now]
                );
            }
        }

        // Lead & Project: whoever could open KPI Evaluation keeps it.
        $from = DB::table('menu')->where('slug', 'general.kpi-evaluation')->first();
        $to   = DB::table('menu')->where('slug', self::TEAMS)->first();
        if ($from && $to) {
            foreach (DB::table('role_menu')->where('menu_id', $from->id)->get() as $row) {
                DB::table('role_menu')->updateOrInsert(
                    ['role_id' => $row->role_id, 'menu_id' => $to->id],
                    ['can_view' => $row->can_view, 'can_create' => false, 'can_edit' => false, 'can_delete' => false, 'created_at' => $now, 'updated_at' => $now]
                );
            }
        }

        DB::table('employee_role_assignment')->pluck('employee_id')->unique()->each(function ($empId) {
            Cache::forget("perm_slugs_{$empId}");
            Cache::forget("perm_matrix_{$empId}");
        });
    }

    public function down(): void
    {
        MenuRegistrar::remove([...array_keys(self::MY_KPI_TABS), self::TEAMS]);
    }
};
