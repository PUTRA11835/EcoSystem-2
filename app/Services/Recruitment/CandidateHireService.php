<?php

namespace App\Services\Recruitment;

use App\Enums\RoleId;
use App\Models\Employee;
use App\Models\EmployeeBasicData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Turns the person an accepted offer was made to into a real, login-capable Employee — the same
 * three-table shape EmployeeController::store() creates for a manually-added
 * hire (employee + employee_basic_data + auth_users), just triggered from an
 * accepted Offer instead of the Master Employee form.
 *
 * The account starts with the default password HR typed in the acceptance
 * form and `is_already_cp = false` — the "initial password" shape the system
 * already knows (see AdminBackupController): signing in with it does not open
 * a session, it emails the person a link to set their own password first
 * (AuthController::login → PasswordSetupController::generateAndSendToken).
 * The sign-in details themselves are emailed by OfferMailer::sendAccountDetails().
 */
class CandidateHireService
{
    /**
     * @param  array{full_name: string, eci: string, email: string, password: string, position?: ?string, home_base?: ?string}  $account
     * @param  string|null  $joinDate  join date (Y-m-d) from the offering letter -> employee_basic_data.since_date;
     *                                 falls back to today when the offer has none.
     */
    public function hire(array $account, ?string $joinDate = null): Employee
    {
        return DB::transaction(function () use ($account, $joinDate) {
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

            [$firstName, $lastName] = $this->splitName($account['full_name']);

            DB::table('employee_basic_data')->insert([
                'employee_id'   => $employeeId,
                'first_name'    => $firstName,
                'last_name'     => $lastName,
                'search_term_1' => strtoupper($firstName),
                'search_term_2' => $lastName ? strtoupper($lastName) : null,
                'nick_name'     => $this->uniqueNickName($account['full_name'], $account['eci']),
                'since_date'    => $joinDate ?? now()->toDateString(),
                'home_base'     => $account['home_base'] ?? null,
                'employee_type' => EmployeeBasicData::deriveEmployeeType($account['home_base'] ?? null),
                'position'      => $account['position'] ?? null,
                'created_by'    => session('user.eci', 'Recruitment'),
                'created_on'    => now(),
                'block'         => false,
                'deletion_flag' => false,
            ]);

            DB::table('auth_users')->insert([
                'employee_id'   => $employeeId,
                'customer_id'   => null,
                'username'      => $account['eci'],
                'email'         => $account['email'],
                'password'      => Hash::make($account['password']),
                'is_active'     => true,
                'is_already_cp' => false,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            return Employee::findOrFail($employeeId);
        });
    }

    /** @return array{0: string, 1: ?string} */
    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2);

        return [$parts[0] ?? $fullName, $parts[1] ?? null];
    }

    /**
     * A nick name no other employee has (the column is unique): the first
     * name, then the first two names, then the whole name, then the first
     * name with the ECI. It can be changed later in Master Employee.
     */
    private function uniqueNickName(string $fullName, string $eci): string
    {
        $words = preg_split('/\s+/', trim($fullName));
        $candidates = array_unique(array_filter([
            $words[0] ?? null,
            isset($words[1]) ? "{$words[0]} {$words[1]}" : null,
            trim($fullName),
            ($words[0] ?? $fullName) . " {$eci}",
        ]));

        foreach ($candidates as $nickName) {
            if (!DB::table('employee_basic_data')->where('nick_name', $nickName)->exists()) {
                return $nickName;
            }
        }

        return "{$fullName} {$eci}";
    }
}
