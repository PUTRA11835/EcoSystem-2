<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminRoleAssignmentSeeder extends Seeder
{
    public function run(): void
    {
        $admin = DB::table('employee')->where('eci', 'ECI_ADMIN')->first();

        if (!$admin) {
            $this->command->warn('Employee dengan eci=ECI_ADMIN tidak ditemukan. Skipping.');
            return;
        }

        DB::table('employee_role_assignment')->updateOrInsert(
            ['employee_id' => $admin->employee_id, 'role_id' => 1],
            ['created_at' => Carbon::now(), 'updated_at' => Carbon::now()]
        );

        $this->command->info('Admin role assignment berhasil.');
    }
}
