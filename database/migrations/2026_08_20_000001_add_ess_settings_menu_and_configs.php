<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\AppConfig;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Ensure management parent menu exists
        $managementParentId = DB::table('menu')->where('slug', 'management')->value('id');
        if (!$managementParentId) {
            $managementParentId = DB::table('menu')->insertGetId([
                'parent_id'  => null,
                'name'       => 'Management',
                'slug'       => 'management',
                'type'       => 'page',
                'route_name' => null,
                'icon'       => 'fas fa-shield-alt',
                'order_seq'  => 10,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Insert management.ess-settings menu item
        $essSettingsMenuId = DB::table('menu')->where('slug', 'management.ess-settings')->value('id');
        if (!$essSettingsMenuId) {
            $essSettingsMenuId = DB::table('menu')->insertGetId([
                'parent_id'  => $managementParentId,
                'name'       => 'ESS Settings',
                'slug'       => 'management.ess-settings',
                'type'       => 'page',
                'route_name' => 'management.ess-settings.index',
                'icon'       => 'fas fa-sliders-h',
                'order_seq'  => 3,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Grant access to management.ess-settings for Admin & HR roles
        $adminRoleId = DB::table('employee_role')->where('id', 1)->value('id') ?? 1;
        DB::table('role_menu')->updateOrInsert(
            ['role_id' => $adminRoleId, 'menu_id' => $essSettingsMenuId],
            ['can_view' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true, 'created_at' => now(), 'updated_at' => now()]
        );

        // Also grant to HO HR Administrator / Head roles if present
        $hrRoles = DB::table('employee_role')
            ->where('name', 'like', '%HR%')
            ->pluck('id');

        foreach ($hrRoles as $hrRoleId) {
            DB::table('role_menu')->updateOrInsert(
                ['role_id' => $hrRoleId, 'menu_id' => $essSettingsMenuId],
                ['can_view' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        // 3. Initialize default app_configs for ESS menu items
        $defaultEssSettings = [
            'home'                  => true,
            'my_profile'            => true,
            'my_attendance'         => true,
            'my_leave_permit'       => true,
            'overtime'              => true,
            'paystub'               => true,
            'expense_reimbursement' => true,
            'purchase_request'      => true,
            'advance_payment_ca'    => true,
            'advance_payment_car'   => true,
            'loans'                 => true,
            'my_kpis'               => true,
            'events_calendar'       => true,
            'my_timesheet'          => true,
        ];

        if (!AppConfig::where('key', 'ess_menu_settings')->exists()) {
            AppConfig::setJson('ess_menu_settings', $defaultEssSettings, 'Global visibility settings for ESS menu items');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('menu')->where('slug', 'management.ess-settings')->delete();
        AppConfig::where('key', 'ess_menu_settings')->delete();
    }
};
