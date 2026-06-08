<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $this->call([
            // ECOSYSTEM Core Seeders
            RoleSeeder::class,
            EmployeeSeeder::class,
            UserSystemRolesSeeder::class,
            CustomerSeeder::class,

            DeliveryProjectSeeder::class,
            DbmlMissingTablesSeeder::class,
            ConsultantWorkloadSeeder::class,
        ]);
    }
}
