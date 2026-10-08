<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Master Employee tabs now follow the Create / Edit / Delete boxes of Menu Access:
 *
 *   employee.section.<tab>.view    Create = add a record, Delete = remove one   (flags on the View row)
 *   employee.section.<tab>.update  Edit   = change a record                      (as before)
 *
 * Until now "Update" allowed all three. To keep today's behaviour, every role that holds Update on a tab
 * receives Create and Delete on that tab's View row. Nothing is revoked; re-running is harmless.
 * (Basic Data, HR Profile and Engagement hold one record per employee, so they keep View / Edit only.)
 */
return new class extends Migration
{
    public const TABS = [
        'address', 'identification', 'family', 'education', 'qualification',
        'contract', 'bank', 'payment', 'attachment', 'salary',
    ];

    public function up(): void
    {
        $affected = collect();

        foreach (self::TABS as $tab) {
            $viewId = DB::table('menu')->where('slug', "employee.section.{$tab}.view")->value('id');
            $updateId = DB::table('menu')->where('slug', "employee.section.{$tab}.update")->value('id');
            if (!$viewId || !$updateId) {
                continue;
            }

            $roles = DB::table('role_menu')->where('menu_id', $updateId)->where('can_view', 1)->pluck('role_id');
            foreach ($roles as $roleId) {
                DB::table('role_menu')->updateOrInsert(
                    ['role_id' => $roleId, 'menu_id' => $viewId],
                    ['can_view' => 1, 'can_create' => 1, 'can_delete' => 1, 'updated_at' => now()],
                );
            }
            $affected = $affected->merge($roles);
        }

        DB::table('employee_role_assignment')->whereIn('role_id', $affected->unique())->pluck('employee_id')->unique()
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
