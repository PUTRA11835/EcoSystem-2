<?php

namespace Database\Seeders;

use Database\Seeders\Kpi\KpiDemoEvaluationSeeder;
use Database\Seeders\Kpi\KpiTemplateSeeder;
use Illuminate\Database\Seeder;

/**
 * Every test account and sample document of the HR / Finance / Legal modules, in the order they depend on each other.
 * Each one can still be run (and cleaned) on its own — see its header — this only saves remembering the order.
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Needs the clean-install data first (php artisan db:seed). Refuses to run in production. Safe to run again: each seeder
 * recognises its own rows (marked "Seeder demo …" / "[DEMO]" / DEMO-* ECI) and does not duplicate them.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('Dihentikan: data uji tidak boleh dibuat di production.');

            return;
        }

        $this->call([
            DemoComparisonAccountsSeeder::class, // DEMO-INT01/02, DEMO-EXT01/02 — dasar untuk seeder di bawahnya
            EssTestAccountsSeeder::class,        // akun uji ESS (Siti Rahma, Budi, Dewi HR) + atasannya
            ContractDemoSeeder::class,           // kontrak PKWT / PKWTT / External dalam berbagai status
            ExternalDemoSeeder::class,           // konsultan eksternal, kontrak, surat
            OffboardingDemoSeeder::class,        // butuh akun DEMO-* dan kontrak di atas
            PayrollUjiSeeder::class,             // dua karyawan uji Payroll (sudah / belum berkontrak)
            AttendanceDemoSeeder::class,         // catatan presensi contoh
            ReimbursementDemoSeeder::class,      // dokumen reimbursement contoh pada setiap status
            KpiTemplateSeeder::class,            // template KPI
            KpiDemoEvaluationSeeder::class,      // evaluasi KPI contoh — butuh EssTestAccountsSeeder + KpiTemplateSeeder
        ]);
    }
}
