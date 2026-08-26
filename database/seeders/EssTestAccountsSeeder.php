<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class EssTestAccountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        // 1. Resolve role IDs
        $ecUserRoleId  = DB::table('employee_role')->where('name', 'EC User')->value('id') ?? 3;
        $hrAdminRoleId = DB::table('employee_role')->where('name', 'HO HR Administrator')->value('id');

        if (!$hrAdminRoleId) {
            $hrAdminRoleId = DB::table('employee_role')->insertGetId([
                'name'        => 'HO HR Administrator',
                'description' => 'Human Resources Administrator',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        $systemRoleId = DB::table('employee_role')->where('name', 'User System Registered')->value('id');
        if (!$systemRoleId) {
            $systemRoleId = DB::table('employee_role')->insertGetId([
                'name'        => 'User System Registered',
                'description' => 'System login access',
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        // Grant management.permissions & hr_general.leave_permit to HR Administrator role
        $permissionMenus = DB::table('menu')
            ->whereIn('slug', ['management', 'management.permissions', 'management.ess-settings', 'general', 'hr_general.leave_permit'])
            ->pluck('id');

        foreach ($permissionMenus as $mId) {
            DB::table('role_menu')->updateOrInsert(
                ['role_id' => $hrAdminRoleId, 'menu_id' => $mId],
                ['can_view' => true, 'can_create' => true, 'can_edit' => true, 'can_delete' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        // Test accounts dataset
        $accounts = [
            [
                'eci'        => 'ESS001',
                'email'      => 'ess.female@ecosystem.local',
                'username'   => 'ess.female',
                'password'   => 'Password123!',
                'first_name' => 'Siti',
                'last_name'  => 'Rahma (ESS Female)',
                'nick_name'  => 'Rahma ESS',
                'gender'     => 'Female',
                'title'      => 'Ms.',
                'position'   => 'Staff',
                'roles'      => [$ecUserRoleId, $systemRoleId],
            ],
            [
                'eci'        => 'ESS002',
                'email'      => 'ess.male@ecosystem.local',
                'username'   => 'ess.male',
                'password'   => 'Password123!',
                'first_name' => 'Budi',
                'last_name'  => 'Santoso (ESS Male)',
                'nick_name'  => 'Budi ESS',
                'gender'     => 'Male',
                'title'      => 'Mr.',
                'position'   => 'Staff',
                'roles'      => [$ecUserRoleId, $systemRoleId],
            ],
            [
                'eci'        => 'HR001',
                'email'      => 'hr.admin@ecosystem.local',
                'username'   => 'hr.admin',
                'password'   => 'Password123!',
                'first_name' => 'Dewi',
                'last_name'  => 'Lestari (HR Admin)',
                'nick_name'  => 'Dewi HR',
                'gender'     => 'Female',
                'title'      => 'Ms.',
                'position'   => 'HR Manager',
                'roles'      => [$hrAdminRoleId, $systemRoleId],
            ],
        ];

        foreach ($accounts as $acc) {
            // Check existing employee by eci
            $employeeId = DB::table('employee')->where('eci', $acc['eci'])->value('employee_id');

            if (!$employeeId) {
                $employeeId = DB::table('employee')->insertGetId([
                    'eci'        => $acc['eci'],
                    'is_active'  => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } else {
                DB::table('employee')->where('employee_id', $employeeId)->update([
                    'is_active'  => true,
                    'updated_at' => $now,
                ]);
            }

            // Assign roles in pivot
            foreach ($acc['roles'] as $rId) {
                DB::table('employee_role_assignment')->updateOrInsert(
                    ['employee_id' => $employeeId, 'role_id' => $rId],
                    ['created_at' => $now, 'updated_at' => $now]
                );
            }

            // Upsert basic data
            DB::table('employee_basic_data')->updateOrInsert(
                ['employee_id' => $employeeId],
                [
                    'title'          => $acc['title'],
                    'nick_name'      => $acc['nick_name'],
                    'gender'         => $acc['gender'],
                    'first_name'     => $acc['first_name'],
                    'last_name'      => $acc['last_name'],
                    'search_term_1'  => mb_strtoupper($acc['first_name']),
                    'search_term_2'  => mb_strtoupper($acc['last_name']),
                    'marital_status' => 'Single',
                    'birth_date'     => '1995-01-01',
                    'birth_place'    => 'Jakarta',
                    'position'       => $acc['position'],
                    'division'       => 'Human Capital',
                    'home_base'      => 'Jakarta',
                    'block'          => false,
                    'deletion_flag'  => false,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]
            );

            // Upsert auth_users account
            DB::table('auth_users')->updateOrInsert(
                ['employee_id' => $employeeId],
                [
                    'customer_id'   => null,
                    'username'      => $acc['username'],
                    'email'         => $acc['email'],
                    'password'      => Hash::make($acc['password']),
                    'is_active'     => true,
                    'is_already_cp' => true,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]
            );
        }
    }
}
