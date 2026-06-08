<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // Roles 1-7: nama sesuai migration 2026_04_16_000001 (sudah di-rename)
        $coreRoles = [
            ['id' => 1, 'name' => 'EC Administrator',               'description' => 'Administrator dengan akses penuh untuk mengelola semua data master'],
            ['id' => 2, 'name' => 'Delivery Support User',          'description' => 'Karyawan dengan akses untuk mengedit data pribadi mereka sendiri'],
            ['id' => 3, 'name' => 'EC User',                        'description' => 'Karyawan magang dengan akses terbatas sesuai kebutuhan internship'],
            ['id' => 4, 'name' => 'Delivery Project Head',          'description' => 'Kepala proyek dengan wewenang untuk approval timesheet consultant'],
            ['id' => 5, 'name' => 'Delivery Support Head',          'description' => 'Kepala support dengan wewenang untuk approval timesheet consultant'],
            ['id' => 6, 'name' => 'Delivery Support Service Helpdesk', 'description' => 'Staff helpdesk yang menangani dan mengassign ticket ke delivery support'],
            ['id' => 7, 'name' => 'Delivery RPMO Head',             'description' => 'Resource & Project Management Office yang bertanggung jawab membuat delivery'],
        ];

        foreach ($coreRoles as $role) {
            DB::table('employee_role')->updateOrInsert(
                ['id' => $role['id']],
                ['name' => $role['name'], 'description' => $role['description'], 'created_at' => $now, 'updated_at' => $now]
            );
        }

        // Role wajib untuk akses sistem — insert jika belum ada
        DB::table('employee_role')->insertOrIgnore([
            ['name' => 'User System Registered', 'description' => 'Role dasar yang diperlukan untuk login ke EcoSystem', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
