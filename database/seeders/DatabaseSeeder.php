<?php

namespace Database\Seeders;

use Database\Seeders\Kpi\KpiDemoEvaluationSeeder;
use Database\Seeders\Kpi\KpiTemplateSeeder;
use Database\Seeders\LeavePermit\LeavePermitTypeSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {

        $this->call([
            // ── Clean install: role, permission & reference data ────────────────
            // Data employee/customer riil dimasukkan via Import CSV (Backup & Export),
            // bukan via seeder. Login admin: ECI_ADMIN / password123.
            RoleSeeder::class,             // wajib: isi tabel employee_role (FK role_id)
            MenuSeeder::class,             // isi tabel menu + role_menu (permission matrix)
            HolidaySeeder::class,          // impor hari libur dari config ke tabel `holidays`
            WilayahSeeder::class,          // referensi wilayah Indonesia (dropdown alamat)
            EmployeeSeeder::class,         // hanya akun ECI_ADMIN
            UserSystemRolesSeeder::class,  // beri admin baris employee_role_assignment
            LeavePermitTypeSeeder::class,  // katalog jenis cuti/izin (Cuti Tahunan, Sakit, dst) — wajib, bukan data dummy

            // ── Demo / testing data — dinonaktifkan untuk clean install ─────────
            // Aktifkan (hapus komentar) hanya untuk instance demo/testing, atau
            // jalankan satu per satu lewat:
            //   php artisan db:seed --class="Database\Seeders\Kpi\KpiTemplateSeeder"
            // CustomerSeeder::class,            // customer dummy
            // DeliveryProjectSeeder::class,     // delivery project dummy
            // DbmlMissingTablesSeeder::class,   // membuat seed_employee dummy
            // ConsultantWorkloadSeeder::class,  // butuh ECI_HELPDESK (akan skip)
            // EssTestAccountsSeeder::class,     // akun uji ESS (Siti Rahma, Budi, Dewi HR) + relasi atasan mereka
            // KpiTemplateSeeder::class,         // contoh template KPI (Self/Lead/Bench Consultant/PMO/dst) — aman untuk data riil, hanya menarget by posisi
            // KpiDemoEvaluationSeeder::class,   // evaluasi KPI palsu untuk employee_id tertentu — jalankan setelah EssTestAccountsSeeder + KpiTemplateSeeder
        ]);
    }
}
