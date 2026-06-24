<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

/**
 * Migration to add Helpdesk role and a Helpdesk employee
 *
 * Helpdesk role is used to assign tickets to delivery support
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Add Helpdesk role if not exists
        $helpdeskRole = DB::table('employee_role')->where('name', 'Helpdesk')->first();

        if (!$helpdeskRole) {
            $roleId = DB::table('employee_role')->insertGetId([
                'name' => 'Helpdesk',
                'description' => 'Staff helpdesk yang menangani dan mengassign ticket ke delivery support',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        } else {
            $roleId = $helpdeskRole->id;
        }

        // Clean-install: employee test "ECI_HELPDESK" sengaja TIDAK dibuat lagi.
        // Pembuatan role 'Helpdesk' di atas tetap dipertahankan. Data employee riil
        // dimasukkan via Import CSV, bukan migration/seeder.
        return;

        // 2. Add Helpdesk employee if not exists
        $helpdeskEmployee = DB::table('employee')->where('eci', 'ECI_HELPDESK')->first();

        if (!$helpdeskEmployee) {
            $employeeId = DB::table('employee')->insertGetId([
                'eci' => 'ECI_HELPDESK',
                'role_id' => $roleId,
                'password' => Hash::make('initial'),
                'is_active' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            // Add basic data for helpdesk employee
            DB::table('employee_basic_data')->insert([
                'employee_id' => $employeeId,
                'title' => 'Mr.',
                'nick_name' => 'Helpdesk',
                'gender' => 'Laki-laki',
                'religion' => 'Islam',
                'first_name' => 'Helpdesk',
                'last_name' => 'Support',
                'search_term_1' => 'HELPDESK',
                'search_term_2' => 'SUPPORT',
                'marital_status' => 'Belum Menikah',
                'birth_date' => '1995-01-01',
                'birth_place' => 'Yogyakarta',
                'since_date' => Carbon::now()->toDateString(),
                'created_by' => 'Migration Script',
                'created_on' => Carbon::now(),
                'personnel_area' => 'PA-YOG',
                'personnel_subarea' => 'PSA-SLEMAN',
                'employee_group' => 'Support',
                'employee_subgroup' => 'Helpdesk',
                'position' => 'Helpdesk Staff',
                'division' => 'Support',
                'department' => 'Customer Support',
                'block' => false,
                'deletion_flag' => false,
            ]);

            // Add address for helpdesk employee
            DB::table('employee_address')->insert([
                'employee_id' => $employeeId,
                'address_type' => 'Office',
                'country' => 'Indonesia',
                'region' => 'DI Yogyakarta',
                'city' => 'Yogyakarta',
                'district' => 'Sleman',
                'rural_urban_village' => 'Catur Tunggal',
                'street' => 'Jl. Kaliurang KM',
                'house_number' => '5.6',
                'postal_code' => '55281',
                'language' => 'Indonesian',
                'preferred_communication' => 'email',
                'cell_phone' => '081290000006',
                'email_personal' => 'helpdesk@eclectic.co.id',
                'email_work' => 'helpdesk@eclectic.co.id',
                'valid_from' => Carbon::now()->toDateString(),
                'is_primary' => true,
                'is_verified' => true,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            // Add qualification for helpdesk (DSM enabled to access support module)
            DB::table('employee_qualification')->insert([
                'employee_id' => $employeeId,
                'qualification_type' => 'Certification',
                'module' => 'Support Helpdesk',
                'language' => 'Indonesian',
                'qualification_level' => 'Intermediate',
                'first_year' => Carbon::now()->year,
                'certified' => true,
                'dpm' => false,
                'dsm' => true, // Enable DSM for support access
                'valid_from' => Carbon::now()->toDateString(),
                'valid_to' => Carbon::now()->addYears(5)->toDateString(),
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);
        }
    }

    public function down(): void
    {
        // Remove helpdesk employee data
        $helpdeskEmployee = DB::table('employee')->where('eci', 'ECI_HELPDESK')->first();

        if ($helpdeskEmployee) {
            DB::table('employee_qualification')->where('employee_id', $helpdeskEmployee->employee_id)->delete();
            DB::table('employee_address')->where('employee_id', $helpdeskEmployee->employee_id)->delete();
            DB::table('employee_basic_data')->where('employee_id', $helpdeskEmployee->employee_id)->delete();
            DB::table('employee')->where('employee_id', $helpdeskEmployee->employee_id)->delete();
        }

        // Optionally remove helpdesk role
        // DB::table('employee_role')->where('name', 'Helpdesk')->delete();
    }
};
