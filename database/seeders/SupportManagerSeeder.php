<?php

namespace Database\Seeders;

use App\Enums\RoleId;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

/**
 * Seeder untuk Delivery Support Manager (atasan dari MO Support team).
 *
 * Idempotent — kalau employee dengan ECI yang sama sudah ada, di-skip.
 * Jalankan dengan:
 *   php artisan db:seed --class=SupportManagerSeeder
 */
class SupportManagerSeeder extends Seeder
{
    public function run(): void
    {
        $managers = [
            [
                'eci'        => 'ECI-SM01',
                'nick_name'  => 'Garnesi',
                'first_name' => 'Garnesi Putri',
                'last_name'  => 'Pertiwi',
                'gender'     => 'Perempuan',
                'email'      => 'garnesi.pertiwi@eclectic.co.id',
            ],
            [
                'eci'        => 'ECI-SM02',
                'nick_name'  => 'Santo',
                'first_name' => 'Santo',
                'last_name'  => 'Suharyono',
                'gender'     => 'Laki-laki',
                'email'      => 'santo.suharyono@eclectic.co.id',
            ],
        ];

        foreach ($managers as $sm) {
            // Idempotent — kalau employee sudah ada, masih lengkapi role assignment
            // (kasus migrasi lama: employee dibuat tanpa baris employee_role_assignment
            //  sehingga login gagal "no access to EcoSystem").
            $existing = DB::table('employee')->where('eci', $sm['eci'])->first();
            if ($existing) {
                $this->ensureRoleAssignments((int) $existing->employee_id);
                $this->command->info("• {$sm['eci']} — {$sm['first_name']} {$sm['last_name']} sudah ada, role assignments dipastikan lengkap.");
                continue;
            }

            DB::beginTransaction();
            try {
                $employeeId = DB::table('employee')->insertGetId([
                    'role_id'             => RoleId::DELIVERY_SUPPORT_MANAGER->value,
                    'eci'                 => $sm['eci'],
                    'modules'             => null,
                    'monthly_capacity_md' => 20.00,
                    'is_active'           => true,
                    'created_at'          => Carbon::now(),
                    'updated_at'          => Carbon::now(),
                ]);

                DB::table('employee_basic_data')->insert([
                    'employee_id'         => $employeeId,
                    'title'               => $sm['gender'] === 'Laki-laki' ? 'Mr.' : 'Ms.',
                    'nick_name'           => $sm['nick_name'],
                    'gender'              => $sm['gender'],
                    'religion'            => 'Islam',
                    'first_name'          => $sm['first_name'],
                    'last_name'           => $sm['last_name'],
                    'search_term_1'       => strtoupper($sm['first_name']),
                    'search_term_2'       => strtoupper($sm['last_name']),
                    'marital_status'      => 'Belum Menikah',
                    'birth_date'          => '1985-01-01',
                    'birth_place'         => 'Yogyakarta',
                    'since_date'          => Carbon::now()->toDateString(),
                    'created_by'          => 'SupportManagerSeeder',
                    'created_on'          => Carbon::now(),
                    'personnel_area'      => 'PA-YOG',
                    'personnel_subarea'   => 'PSA-SLEMAN',
                    'employee_group'      => 'Management',
                    'employee_subgroup'   => 'Support Manager',
                    'position'            => 'Support Manager',
                    'division'            => 'Consulting Services',
                    'department'          => 'MO Support',
                    'direct_supervision'  => null,
                    'manager'             => 'Head of Support',
                    'authorization_group' => 'SUPPORT-MGR',
                    'block'               => false,
                    'deletion_flag'       => false,
                    'created_at'          => Carbon::now(),
                    'updated_at'          => Carbon::now(),
                ]);

                // auth_users — supaya bisa login. Idempotent: hanya insert kalau belum ada.
                $authExists = DB::table('auth_users')
                    ->where('email', $sm['email'])
                    ->orWhere('username', $sm['eci'])
                    ->exists();

                if (!$authExists) {
                    DB::table('auth_users')->insert([
                        'employee_id'   => $employeeId,
                        'username'      => $sm['eci'],
                        'email'         => $sm['email'],
                        'password'      => Hash::make('password123'),
                        'is_active'     => true,
                        'is_already_cp' => true,
                        'created_at'    => Carbon::now(),
                        'updated_at'    => Carbon::now(),
                    ]);
                }

                // employee_role_assignment — wajib supaya login lolos cek
                // 'User System Registered' (id=61). Tanpa ini, login dapat 403
                // "Your account does not have access to EcoSystem".
                $this->ensureRoleAssignments($employeeId);

                DB::commit();
                $this->command->info("✓ {$sm['eci']} — {$sm['first_name']} {$sm['last_name']} (Support Manager) created.");

            } catch (\Throwable $e) {
                DB::rollBack();
                $this->command->error("✗ Failed for {$sm['eci']}: " . $e->getMessage());
            }
        }

        $this->command->info('Selesai. Total Support Manager target: ' . count($managers));
    }

    /**
     * Pastikan employee_role_assignment punya 2 baris: role utama (Support Manager)
     * dan 'User System Registered' (id=61). Idempotent — skip yang sudah ada.
     */
    private function ensureRoleAssignments(int $employeeId): void
    {
        $now = Carbon::now();
        $userSystemRoleId = (int) DB::table('employee_role')
            ->where('name', 'User System Registered')
            ->value('id');

        $required = array_filter([
            RoleId::DELIVERY_SUPPORT_MANAGER->value,
            $userSystemRoleId ?: null,
        ]);

        foreach ($required as $roleId) {
            $exists = DB::table('employee_role_assignment')
                ->where('employee_id', $employeeId)
                ->where('role_id', $roleId)
                ->exists();
            if (!$exists) {
                DB::table('employee_role_assignment')->insert([
                    'employee_id' => $employeeId,
                    'role_id'     => $roleId,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]);
            }
        }
    }
}
