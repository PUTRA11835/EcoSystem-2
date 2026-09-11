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
            // ── Clean install: hanya role + admin ──────────────────────────────
            // Data employee/customer riil dimasukkan via Import CSV (Backup & Export),
            // bukan via seeder. Login admin: ECI_ADMIN / password123.
            RoleSeeder::class,            // wajib: isi tabel employee_role (FK role_id)
            MenuSeeder::class,            // isi tabel menu + role_menu (permission matrix)
            HolidaySeeder::class,         // impor hari libur dari config ke tabel `holidays`
            WilayahSeeder::class,         // referensi wilayah Indonesia (dropdown alamat)
            EmployeeSeeder::class,        // hanya akun ECI_ADMIN
            UserSystemRolesSeeder::class, // beri admin baris employee_role_assignment

            // ── Seeder data dummy — dinonaktifkan untuk clean install ───────────
            // Aktifkan kembali bila butuh data contoh untuk development.
            // CustomerSeeder::class,        // customer dummy
            // DeliveryProjectSeeder::class, // delivery project dummy
            // DbmlMissingTablesSeeder::class, // membuat seed_employee dummy
            // ConsultantWorkloadSeeder::class, // butuh ECI_HELPDESK (akan skip)
        ]);
    }
}
