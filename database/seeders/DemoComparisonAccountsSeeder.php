<?php

namespace Database\Seeders;

use App\Models\EmployeeBasicData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Akun uji untuk MEMBANDINGKAN pegawai Internal vs External (menu yang didapat dan studi kasus Human Capital).
 *
 * Dua pasang dengan role identik, sehingga perbedaan yang tampak murni karena jenis pegawai:
 *   DEMO-INT01 / DEMO-EXT01 → Delivery Support User   (+ EC User + User System Registered)
 *   DEMO-INT02 / DEMO-EXT02 → Delivery Project User   (+ EC User + User System Registered)
 *
 * Login: username = ECI (mis. DEMO-INT01), password = password123.
 * Data profil sengaja bervariasi untuk studi kasus: DEMO-INT01 dan DEMO-EXT01 PUNYA join date; DEMO-INT02 dan
 * DEMO-EXT02 TIDAK (muncul di filter Onboarding "Missing: Join date" dan penanda HR di Command Center).
 *
 *   php artisan db:seed --class=DemoComparisonAccountsSeeder
 *   DEMO_ACCOUNTS_CLEAN=1 php artisan db:seed --class=DemoComparisonAccountsSeeder   # hapus HANYA akun DEMO-*
 *
 * Aman diulang (updateOrInsert), tidak didaftarkan di DatabaseSeeder, menolak berjalan di production
 * (kata sandi mudah ditebak).
 */
class DemoComparisonAccountsSeeder extends Seeder
{
    private const PASSWORD = 'password123';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->warn('Dihentikan: akun demo berkata sandi mudah tidak boleh dibuat di production.');

            return;
        }

        $accounts = $this->accounts();

        if (env('DEMO_ACCOUNTS_CLEAN')) {
            $this->clean(array_column($accounts, 'eci'));

            return;
        }

        $role = fn (string $name) => (int) DB::table('employee_role')->where('name', $name)->value('id');
        $base = array_filter([$role('EC User'), $role('User System Registered')]);
        $now  = now();

        foreach ($accounts as $a) {
            $roles = array_merge($base, array_filter([$role($a['role'])]));
            if (count($roles) < 3) {
                $this->warn("{$a['eci']}: role \"{$a['role']}\" tidak ditemukan — dilewati.");
                continue;
            }

            DB::transaction(function () use ($a, $roles, $now) {
                $employeeId = DB::table('employee')->where('eci', $a['eci'])->value('employee_id')
                    ?: DB::table('employee')->insertGetId(['eci' => $a['eci'], 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
                DB::table('employee')->where('employee_id', $employeeId)->update(['is_active' => true, 'updated_at' => $now]);

                foreach ($roles as $rid) {
                    DB::table('employee_role_assignment')->updateOrInsert(
                        ['employee_id' => $employeeId, 'role_id' => $rid],
                        ['created_at' => $now, 'updated_at' => $now]
                    );
                }

                $homeBase = $a['external'] ? 'Others' : 'Yogyakarta';
                DB::table('employee_basic_data')->updateOrInsert(['employee_id' => $employeeId], [
                    'title' => $a['gender'] === 'Female' ? 'Ms.' : 'Mr.',
                    'first_name' => $a['first'], 'last_name' => $a['last'], 'nick_name' => $a['nick'],
                    'search_term_1' => mb_strtoupper($a['first']), 'search_term_2' => mb_strtoupper($a['last']),
                    'gender' => $a['gender'], 'marital_status' => 'Single', 'birth_date' => '1996-04-12', 'birth_place' => 'Yogyakarta',
                    'since_date' => $a['join_date'],
                    'position' => $a['position'], 'department' => 'RESOURCE, PROJECT MANAGEMENT & OPERATIONS', 'division' => 'OPERATIONS',
                    'employee_group' => $a['external'] ? 'EXTERNAL' : 'INTERNAL', 'personnel_area' => 'OPERATIONS',
                    'home_base' => $homeBase,
                    'employee_type' => EmployeeBasicData::deriveEmployeeType($homeBase),
                    'created_by' => 'DemoSeeder', 'created_on' => $now,
                    'block' => false, 'deletion_flag' => false,
                ]);

                DB::table('auth_users')->updateOrInsert(['employee_id' => $employeeId], [
                    'customer_id' => null, 'username' => $a['eci'], 'email' => strtolower($a['eci']) . '@ecosystem.local',
                    'password' => Hash::make(self::PASSWORD),
                    'is_active' => true, 'is_already_cp' => true, // langsung bisa login, tanpa alur "set password"
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            });

            $this->info(sprintf('  %-11s %-8s %-22s join date: %s', $a['eci'], $a['external'] ? 'External' : 'Internal', $a['role'], $a['join_date'] ?? '(kosong)'));
        }

        $this->info('Selesai. Login dengan username = ECI di atas, password = ' . self::PASSWORD);
    }

    /** @return array<int, array<string, mixed>> */
    private function accounts(): array
    {
        return [
            ['eci' => 'DEMO-INT01', 'external' => false, 'role' => 'Delivery Support User', 'first' => 'Intan', 'last' => 'Demo Internal Support', 'nick' => 'Intan Demo', 'gender' => 'Female', 'position' => 'SUPPORT CONSULTANT', 'join_date' => '2025-03-03'],
            ['eci' => 'DEMO-INT02', 'external' => false, 'role' => 'Delivery Project User', 'first' => 'Indra', 'last' => 'Demo Internal Project', 'nick' => 'Indra Demo', 'gender' => 'Male', 'position' => 'PROJECT CONSULTANT', 'join_date' => null],
            ['eci' => 'DEMO-EXT01', 'external' => true, 'role' => 'Delivery Support User', 'first' => 'Eka', 'last' => 'Demo External Support', 'nick' => 'Eka Demo', 'gender' => 'Female', 'position' => 'SAP CONSULTANT', 'join_date' => '2025-06-02'],
            ['eci' => 'DEMO-EXT02', 'external' => true, 'role' => 'Delivery Project User', 'first' => 'Eko', 'last' => 'Demo External Project', 'nick' => 'Eko Demo', 'gender' => 'Male', 'position' => 'SAP CONSULTANT', 'join_date' => null],
        ];
    }

    /** Hanya ECI berawalan DEMO-; menghapus child lebih dulu, di satu transaksi. */
    private function clean(array $ecis): void
    {
        $ids = DB::table('employee')->whereIn('eci', $ecis)->where('eci', 'like', 'DEMO-%')->pluck('employee_id')->all();
        DB::transaction(function () use ($ids) {
            foreach (['attendance_records', 'employee_role_assignment', 'auth_users', 'employee_basic_data', 'employee_history', 'notifications'] as $t) {
                if (\Illuminate\Support\Facades\Schema::hasTable($t)) {
                    DB::table($t)->whereIn('employee_id', $ids)->delete();
                }
            }
            DB::table('employee')->whereIn('employee_id', $ids)->delete();
        });
        $this->info('Dibersihkan: ' . count($ids) . ' akun DEMO-*.');
    }

    private function info(string $m): void
    {
        $this->command ? $this->command->info($m) : print($m . "\n");
    }

    private function warn(string $m): void
    {
        $this->command ? $this->command->warn($m) : print($m . "\n");
    }
}
