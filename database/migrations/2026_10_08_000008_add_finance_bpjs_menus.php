<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Menu Access BPJS (Payroll Fase 2), di bawah akar `finance` (dibuat migrasi 2026_10_08_000005):
 *
 *   finance.bpjs                  (group; induk tab)
 *   └─ finance.bpjs.settings      page   C = buat versi pengaturan baru (tidak ada Edit/Delete: append-only)
 *
 * Tab Letters (BPJS Letter) dan Report menambah slug-nya sendiri pada fasenya.
 * ATURAN BAKU MenuRegistrar: slug baru hanya aktif untuk EC Administrator.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!MenuRegistrar::register('finance', ['finance.bpjs' => 'BPJS'], 20, 'group')) {
            return;   // akar `finance` belum ada — jalankan migrasi sebelumnya
        }
        MenuRegistrar::register('finance.bpjs', ['finance.bpjs.settings' => 'Settings'], 1, 'page');
        MenuRegistrar::grantToAdminAndRoles(['finance.bpjs.settings'], [], ['create']);

        $this->flushPermCache();
    }

    public function down(): void
    {
        MenuRegistrar::remove(['finance.bpjs.settings', 'finance.bpjs']);
        $this->flushPermCache();
    }

    private function flushPermCache(): void
    {
        DB::table('employee_role_assignment')->pluck('employee_id')->unique()->each(function ($employeeId) {
            Cache::forget("perm_slugs_{$employeeId}");
            Cache::forget("perm_matrix_{$employeeId}");
        });
    }
};
