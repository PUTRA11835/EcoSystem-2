<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

/**
 * Seed employee C25028 (Fithriya Nur Hana) as a "Delivery Project Administrator".
 *
 * Role context: "Delivery Project Administrator" mirrors the Helpdesk role but in
 * the Delivery Project domain. It is NOT given support-ticket powers (it is kept
 * out of HELPDESK_GROUP / TICKET_MANAGER_GROUP). The project module is not
 * role-gated, so this role already has functional access to administer projects.
 *
 * Login: password "password123", is_already_cp = true (can log in immediately,
 * following the convention used by the existing employee seeder).
 */
return new class extends Migration
{
    private const ECI   = 'ECI058';
    private const EMAIL = 'fithriya.nurhana@eclectic.co.id';
    private const ROLE_NAME = 'Delivery Project Administrator';

    public function up(): void
    {
        // 1. Resolve (or create) the "Delivery Project Administrator" role.
        $roleId = DB::table('employee_role')->where('name', self::ROLE_NAME)->value('id');
        if (!$roleId) {
            $roleId = DB::table('employee_role')->insertGetId([
                'name'        => self::ROLE_NAME,
                'description' => 'Project-domain administrator (mirrors Helpdesk, scoped to the Delivery Project module).',
                'created_at'  => Carbon::now(),
                'updated_at'  => Carbon::now(),
            ]);
        }

        // 2. Skip entirely if this employee already exists (idempotent).
        if (DB::table('employee')->where('eci', self::ECI)->exists()) {
            return;
        }

        // 3. Create the employee record.
        $employeeId = DB::table('employee')->insertGetId([
            'role_id'    => $roleId,
            'eci'        => self::ECI,
            'is_active'  => true,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // 4. Pivot role assignment (multi-role source of truth).
        DB::table('employee_role_assignment')->insertOrIgnore([
            'employee_id' => $employeeId,
            'role_id'     => $roleId,
            'created_at'  => Carbon::now(),
            'updated_at'  => Carbon::now(),
        ]);

        // 5. Basic data. Indonesian source values are mapped to the system's
        //    English enum so the master-employee dropdowns display them correctly:
        //    "Perempuan" -> Female, "Belum Menikah" -> Single.
        DB::table('employee_basic_data')->insert([
            'employee_id'    => $employeeId,
            'title'          => 'Ms.',
            'nick_name'      => 'Fithriya',
            'gender'         => 'Female',
            'religion'       => 'Islam',
            'first_name'     => 'Fithriya',
            'last_name'      => 'Nur Hana',
            'search_term_1'  => 'FITHRIYA',
            'search_term_2'  => 'NUR HANA',
            'marital_status' => 'Single',
            'birth_date'     => '2002-05-17',
            'birth_place'    => 'Magelang',
            'position'       => 'Support',
            'division'       => 'Project & Operational',
            'home_base'      => 'Yogyakarta',
            'grade'          => 'Management Trainee',
            'created_by'     => 'Migration Script',
            'created_on'     => Carbon::now(),
            'block'          => false,
            'deletion_flag'  => false,
            'created_at'     => Carbon::now(),
            'updated_at'     => Carbon::now(),
        ]);

        // 6. Identification (NIK / KTP).
        DB::table('employee_identification')->insertOrIgnore([
            'employee_id'           => $employeeId,
            'identification_type'   => 'NIK',
            'identification_number' => '3371015705020002',
            'country'               => 'Indonesia',
            'created_at'            => Carbon::now(),
            'updated_at'            => Carbon::now(),
        ]);

        // 7. Login account — only if not already present (guards re-runs / clashes).
        $authExists = DB::table('auth_users')
            ->where('email', self::EMAIL)
            ->orWhere('username', self::ECI)
            ->exists();

        if (!$authExists) {
            DB::table('auth_users')->insert([
                'employee_id'   => $employeeId,
                'customer_id'   => null,
                'username'      => self::ECI,
                'email'         => self::EMAIL,
                'phone'         => null,
                'password'      => Hash::make('password123'),
                'is_active'     => true,
                'is_already_cp' => true, // password set here → can log in immediately
                'created_at'    => Carbon::now(),
                'updated_at'    => Carbon::now(),
            ]);
        }
    }

    public function down(): void
    {
        $employeeId = DB::table('employee')->where('eci', self::ECI)->value('employee_id');
        if (!$employeeId) {
            return;
        }

        DB::table('auth_users')->where('employee_id', $employeeId)->delete();
        DB::table('employee_identification')->where('employee_id', $employeeId)->delete();
        DB::table('employee_role_assignment')->where('employee_id', $employeeId)->delete();
        DB::table('employee_basic_data')->where('employee_id', $employeeId)->delete();
        DB::table('employee')->where('employee_id', $employeeId)->delete();

        // Note: the "Delivery Project Administrator" role is intentionally left in
        // place (it predates this migration and may be assigned to others).
    }
};
