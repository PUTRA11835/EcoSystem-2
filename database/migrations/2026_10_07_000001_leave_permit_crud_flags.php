<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Leave & Permit: the HR actions now follow the Create / Edit boxes of Menu Access instead of a hard-coded role list
 * (LeavePermitController used role ids 1, 4, 5, 7 and a slug that never existed).
 *
 *   hr_general.leave_permit            Create = Log Leave / Permit (all tabs)      Edit = edit a logged application
 *   hr_general.leave_permit.tab-inbox  Edit   = approve / reject / ask for revision
 *   hr_general.leave_permit.tab-types  Create = add a leave type                    Edit = edit / (de)activate a type
 *
 * To keep today's behaviour, exactly the roles that could act before (1, 4, 5, 7) AND already hold the page receive
 * the boxes; every other role keeps View only, as it had. Nothing is revoked; re-running is harmless.
 */
return new class extends Migration
{
    private const LEGACY_HR_ROLES = [1, 4, 5, 7];

    private const GRANTS = [
        'hr_general.leave_permit'           => ['can_create' => 1, 'can_edit' => 1],
        'hr_general.leave_permit.tab-inbox' => ['can_edit' => 1],
        'hr_general.leave_permit.tab-types' => ['can_create' => 1, 'can_edit' => 1],
    ];

    public function up(): void
    {
        $page = DB::table('menu')->where('slug', 'hr_general.leave_permit')->value('id');
        if (!$page) {
            return;
        }
        $roles = DB::table('role_menu')->where('menu_id', $page)->whereIn('role_id', self::LEGACY_HR_ROLES)->pluck('role_id');
        $now = now();

        foreach (self::GRANTS as $slug => $flags) {
            $menuId = DB::table('menu')->where('slug', $slug)->value('id');
            if (!$menuId) {
                continue; // tab slugs are created by 2026_10_06_000001; nothing to do if they are absent
            }
            foreach ($roles as $roleId) {
                DB::table('role_menu')->updateOrInsert(
                    ['role_id' => $roleId, 'menu_id' => $menuId],
                    $flags + ['can_view' => 1, 'updated_at' => $now],
                );
            }
        }

        DB::table('employee_role_assignment')->whereIn('role_id', $roles)->pluck('employee_id')->unique()
            ->each(function ($employeeId) {
                Cache::forget("perm_slugs_{$employeeId}");
                Cache::forget("perm_matrix_{$employeeId}");
            });
    }

    public function down(): void
    {
        // The flags are plain data that Menu Access owns from now on; they are not taken back.
    }
};
