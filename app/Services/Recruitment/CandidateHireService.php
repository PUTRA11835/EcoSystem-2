<?php

namespace App\Services\Recruitment;

use App\Enums\RoleId;
use App\Http\Controllers\PasswordSetupController;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Turns the person an accepted offer was made to into a real, login-capable Employee — the same
 * three-table shape EmployeeController::store() creates for a manually-added
 * hire (employee + employee_basic_data + auth_users), just triggered from an
 * accepted Offer instead of the Master Employee form.
 *
 * The account starts passwordless (a random placeholder hash) and
 * `is_already_cp = false`; PasswordSetupController::generateAndSendToken()
 * emails the candidate a "set your password" link — the exact flow already
 * used for every other new account in this system.
 */
class CandidateHireService
{
    /**
     * @param  array{eci: string, nick_name: string, email: string}  $account
     */
    public function hire(string $fullName, array $account): Employee
    {
        return DB::transaction(function () use ($fullName, $account) {
            $employeeId = DB::table('employee')->insertGetId([
                'eci'        => $account['eci'],
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $userSystemRegisteredId = DB::table('employee_role')->where('name', 'User System Registered')->value('id');
            $now = now();
            foreach (array_filter([$userSystemRegisteredId, RoleId::EC_USER->value]) as $roleId) {
                DB::table('employee_role_assignment')->insertOrIgnore([
                    'employee_id' => $employeeId,
                    'role_id'     => $roleId,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }

            [$firstName, $lastName] = $this->splitName($fullName);

            DB::table('employee_basic_data')->insert([
                'employee_id'   => $employeeId,
                'first_name'    => $firstName,
                'last_name'     => $lastName,
                'search_term_1' => strtoupper($firstName),
                'search_term_2' => $lastName ? strtoupper($lastName) : null,
                'nick_name'     => $account['nick_name'],
                'created_by'    => session('user.eci', 'Recruitment'),
                'created_on'    => now(),
                'block'         => false,
                'deletion_flag' => false,
            ]);

            $authUserId = DB::table('auth_users')->insertGetId([
                'employee_id'   => $employeeId,
                'customer_id'   => null,
                'username'      => $account['eci'],
                'email'         => $account['email'],
                'password'      => Hash::make(Str::random(32)),
                'is_active'     => true,
                'is_already_cp' => false,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            $authUser = DB::table('auth_users')->where('id', $authUserId)->first();
            PasswordSetupController::generateAndSendToken($authUser);

            return Employee::findOrFail($employeeId);
        });
    }

    /** @return array{0: string, 1: ?string} */
    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2);

        return [$parts[0] ?? $fullName, $parts[1] ?? null];
    }
}
