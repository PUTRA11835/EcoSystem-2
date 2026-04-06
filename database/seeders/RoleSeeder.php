<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'id' => 1,
                'name' => 'Admin',
                'description' => 'Administrator dengan akses penuh untuk mengelola semua data master',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 2,
                'name' => 'Employee',
                'description' => 'Karyawan dengan akses untuk mengedit data pribadi mereka sendiri',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 3,
                'name' => 'Internship',
                'description' => 'Karyawan magang dengan akses terbatas sesuai kebutuhan internship',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 4,
                'name' => 'Head of Project',
                'description' => 'Kepala proyek dengan wewenang untuk approval timesheet consultant',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 5,
                'name' => 'Head of Support',
                'description' => 'Kepala support dengan wewenang untuk approval timesheet consultant',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 6,
                'name' => 'Helpdesk',
                'description' => 'Staff helpdesk yang menangani dan mengassign ticket ke delivery support',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'id' => 7,
                'name' => 'RPMO',
                'description' => 'Resource & Project Management Office yang bertanggung jawab membuat delivery (project dan support)',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        foreach ($roles as $role) {
            DB::table('employee_role')->updateOrInsert(
                ['id' => $role['id']],
                array_merge($role, [
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ])
            );
        }
    
    }
}
