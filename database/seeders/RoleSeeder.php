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
                'name' => 'Admin',
                'description' => 'Administrator dengan akses penuh untuk mengelola semua data master',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Employee',
                'description' => 'Karyawan dengan akses untuk mengedit data pribadi mereka sendiri',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
            [
                'name' => 'Customer',
                'description' => 'Pelanggan dengan akses untuk mengedit data mereka sendiri',
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ],
        ];

        foreach ($roles as $role) {
            DB::table('role')->updateOrInsert(
                ['name' => $role['name']], 
                array_merge($role, [
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ])
            );
        }
    
    }
}