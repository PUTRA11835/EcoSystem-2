<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Check or insert 'general' parent menu if missing
        $generalParentId = DB::table('menu')->where('slug', 'general')->value('id');

        if (!$generalParentId) {
            $generalParentId = DB::table('menu')->insertGetId([
                'parent_id'  => null,
                'name'       => 'HR & General',
                'slug'       => 'general',
                'type'       => 'page',
                'route_name' => null,
                'icon'       => 'fas fa-users-cog',
                'order_seq'  => 5,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. HR Leave & Permit Management menu item inside HR & General
        $leavePermitMenuId = DB::table('menu')->where('slug', 'hr_general.leave_permit')->value('id');

        if (!$leavePermitMenuId) {
            $leavePermitMenuId = DB::table('menu')->insertGetId([
                'parent_id'  => $generalParentId,
                'name'       => 'Leave & Permit',
                'slug'       => 'hr_general.leave_permit',
                'type'       => 'page',
                'route_name' => 'hr-general.leave-permit',
                'icon'       => 'fas fa-calendar-minus',
                'order_seq'  => 1,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. ESS My Leave & Permit standalone top-level menu item for employees
        $essMenuId = DB::table('menu')->where('slug', 'ess.my_leave_permit')->value('id');

        if (!$essMenuId) {
            $essMenuId = DB::table('menu')->insertGetId([
                'parent_id'  => null,
                'name'       => 'My Leave & Permit',
                'slug'       => 'ess.my_leave_permit',
                'type'       => 'page',
                'route_name' => 'my-leave-permit',
                'icon'       => 'fas fa-calendar-alt',
                'order_seq'  => 3,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Grant HR & General + HR Leave & Permit ONLY to Admin (Role 1) and HO HR Administrator roles
        $adminRoles = DB::table('employee_role')
            ->whereIn('name', ['EC Administrator', 'HO HR Administrator'])
            ->orWhere('id', 1)
            ->pluck('id');

        foreach ($adminRoles as $roleId) {
            DB::table('role_menu')->updateOrInsert(
                ['role_id' => $roleId, 'menu_id' => $generalParentId],
                ['can_view' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true, 'created_at' => now(), 'updated_at' => now()]
            );

            DB::table('role_menu')->updateOrInsert(
                ['role_id' => $roleId, 'menu_id' => $leavePermitMenuId],
                ['can_view' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // Grant ESS My Leave & Permit to all roles
        $allRoles = DB::table('employee_role')->pluck('id');
        foreach ($allRoles as $roleId) {
            DB::table('role_menu')->updateOrInsert(
                ['role_id' => $roleId, 'menu_id' => $essMenuId],
                ['can_view' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('menu')->whereIn('slug', ['hr_general.leave_permit', 'ess.my_leave_permit'])->delete();
    }
};
