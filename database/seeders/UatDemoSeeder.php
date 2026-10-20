<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Satu pintu untuk SEMUA data uji UAT (akun DEMO-*, kontrak, konsultan External, Offboarding) — menjalankan seeder demo yang sudah ada
 * dengan urutan yang benar, atau membersihkannya dengan urutan terbalik. Tidak membuat data baru sendiri.
 *
 *   php artisan db:seed --class=UatDemoSeeder                       # pasang (akun, kontrak, External, Offboarding)
 *   UAT_DEMO_FULL=1 php artisan db:seed --class=UatDemoSeeder       # + presensi, reimbursement, karyawan uji Payroll
 *   UAT_DEMO_CLEAN=1 php artisan db:seed --class=UatDemoSeeder      # bersihkan (urutan terbalik; hanya baris bertanda seeder)
 *
 * Tiap seeder anak aman diulang, menolak production, dan hanya menyentuh barisnya sendiri. Database tempat Anda menjalankan ini
 * = database UAT: JANGAN dijalankan pada database bersama/produksi (akun DEMO-* berkata sandi password123).
 * Catatan: akun yang sudah di-Deactivate lewat Offboarding TIDAK diaktifkan kembali oleh pembersih; menjalankan pasang ulang
 * mengaktifkan kembali akun DEMO-INT/EXT01-02 dan login DEMO-EXT03-05 (lihat PersiapanUAT/04).
 */
class UatDemoSeeder extends Seeder
{
    /** Pasang: urutan bergantung (akun → kontrak → konsultan → offboarding). */
    private const INSTALL = [DemoComparisonAccountsSeeder::class, ContractDemoSeeder::class, ExternalDemoSeeder::class, OffboardingDemoSeeder::class];

    /** Opsional (UAT_DEMO_FULL=1). */
    private const OPTIONAL = [AttendanceDemoSeeder::class, ReimbursementDemoSeeder::class, PayrollUjiSeeder::class];

    /** Penanda pembersih tiap seeder (variabel lingkungan yang dibaca seeder anak). */
    private const CLEAN_FLAG = [
        OffboardingDemoSeeder::class => 'OFFBOARDING_DEMO_CLEAN', ExternalDemoSeeder::class => 'EXTERNAL_DEMO_CLEAN',
        ContractDemoSeeder::class => 'CONTRACT_DEMO_CLEAN', DemoComparisonAccountsSeeder::class => 'DEMO_ACCOUNTS_CLEAN',
        AttendanceDemoSeeder::class => 'ATT_DEMO_CLEAN', PayrollUjiSeeder::class => 'PAYROLL_UJI_CLEAN',
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('Dihentikan: data uji UAT tidak boleh dibuat di production. Gunakan database UAT/staging terpisah.');

            return;
        }

        $clean = (bool) env('UAT_DEMO_CLEAN');
        $full = (bool) env('UAT_DEMO_FULL');
        $list = array_merge(self::INSTALL, $full ? self::OPTIONAL : []);

        if ($clean) {
            foreach (array_reverse($list) as $seeder) {
                $flag = self::CLEAN_FLAG[$seeder] ?? null;
                if ($flag === null) {
                    $this->command?->warn(class_basename($seeder) . ': tidak punya pembersih otomatis — dilewati.');

                    continue;
                }
                $this->withEnv($flag, fn () => $this->call($seeder));
            }

            return;
        }

        foreach ($list as $seeder) {
            $this->call($seeder);
        }
        $this->command?->info('Selesai. Akun uji: lihat docs/humancapital/PersiapanUAT/04-AKUN-UJI-DAN-SEEDER.docx. Bersihkan: UAT_DEMO_CLEAN=1.');
    }

    /** Menyalakan satu variabel lingkungan hanya selama $do berjalan (env() membaca $_ENV/$_SERVER). */
    private function withEnv(string $name, \Closure $do): void
    {
        $old = [$_ENV[$name] ?? null, $_SERVER[$name] ?? null];
        $_ENV[$name] = $_SERVER[$name] = '1';
        putenv("{$name}=1");
        try {
            $do();
        } finally {
            if ($old[0] === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $old[0];
            }
            if ($old[1] === null) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $old[1];
            }
            putenv($name);
        }
    }
}
