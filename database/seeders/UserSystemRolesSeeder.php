<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSystemRolesSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // ── 1. Resolve role IDs ───────────────────────────────────────────────
        // ID 1 (EC Administrator) dan ID 3 (EC User) selalu tetap dari RoleSeeder
        $roleEcAdmin = 1;
        $roleEcUser  = 3;

        // "User System Registered" tidak punya ID tetap (auto-increment) — lookup by name
        $roleUsrReg = DB::table('employee_role')->where('name', 'User System Registered')->value('id');

        if (!$roleUsrReg) {
            $this->command->error('"User System Registered" role not found. Pastikan RoleSeeder sudah dijalankan.');
            return;
        }

        // ── 2. ECI_ADMIN → EC Administrator ──────────────────────────────────
        $admin = DB::table('employee')->where('eci', 'ECI_ADMIN')->first();

        if ($admin) {
            DB::table('employee_role_assignment')->insertOrIgnore([
                'employee_id' => $admin->employee_id,
                'role_id'     => $roleEcAdmin,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $this->command->line("  [ECI_ADMIN] EC Administrator role assigned (employee_id={$admin->employee_id}).");
        } else {
            $this->command->warn('Employee ECI_ADMIN not found — skipping EC Administrator assignment.');
        }

        // ── 3. All employees → auth_users + User System Registered + EC User ──
        $allEmployees = DB::table('employee')->get();

        // Build lookup sets for O(1) checks
        $hasAuthUser = array_flip(
            DB::table('auth_users')
                ->whereNotNull('employee_id')
                ->pluck('employee_id')
                ->toArray()
        );

        // Pre-fetch primary work emails from employee_address
        $emailByEmpId = DB::table('employee_address')
            ->whereNotNull('email_work')
            ->where('is_primary', true)
            ->pluck('email_work', 'employee_id');

        // Emails already used in auth_users (for duplicate check)
        $usedEmails = array_flip(
            DB::table('auth_users')->whereNotNull('email')->pluck('email')->toArray()
        );

        $assignedRoles = DB::table('employee_role_assignment')
            ->whereIn('role_id', [$roleUsrReg, $roleEcUser])
            ->get(['employee_id', 'role_id'])
            ->groupBy('employee_id')
            ->map(fn($rows) => $rows->pluck('role_id')->flip()->toArray());

        $authToInsert   = [];
        $usrRegToInsert = [];
        $ecUserToInsert = [];

        foreach ($allEmployees as $emp) {
            $empId     = $emp->employee_id;
            $empRoles  = $assignedRoles->get($empId, []);

            // Create auth_users record if missing
            if (!array_key_exists($empId, $hasAuthUser)) {
                $rawEmail = $emailByEmpId->get($empId);
                // Skip email if already used by another auth_users record
                $email = ($rawEmail && !array_key_exists($rawEmail, $usedEmails)) ? $rawEmail : null;
                if ($email) $usedEmails[$email] = true; // mark as used for this batch

                $authToInsert[] = [
                    'employee_id'   => $empId,
                    'username'      => $emp->eci,
                    'email'         => $email,
                    'password'      => Hash::make('initial'),
                    'is_active'     => true,
                    'is_already_cp' => true,  // langsung bisa login, password default: initial
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }

            // Assign "User System Registered" role
            if (!array_key_exists($roleUsrReg, $empRoles)) {
                $usrRegToInsert[] = [
                    'employee_id' => $empId,
                    'role_id'     => $roleUsrReg,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }

            // Assign "EC User" role (default)
            if (!array_key_exists($roleEcUser, $empRoles)) {
                $ecUserToInsert[] = [
                    'employee_id' => $empId,
                    'role_id'     => $roleEcUser,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }
        }

        if (!empty($authToInsert)) {
            DB::table('auth_users')->insertOrIgnore($authToInsert);
        }
        if (!empty($usrRegToInsert)) {
            DB::table('employee_role_assignment')->insertOrIgnore($usrRegToInsert);
        }
        if (!empty($ecUserToInsert)) {
            DB::table('employee_role_assignment')->insertOrIgnore($ecUserToInsert);
        }

        $total = $allEmployees->count();
        $this->command->info("Processed {$total} employee(s).");
        $this->command->line('  Auth users created  : ' . count($authToInsert));
        $this->command->line('  User System Registered assigned : ' . count($usrRegToInsert));
        $this->command->line('  EC User (default) assigned      : ' . count($ecUserToInsert));
    }
}
