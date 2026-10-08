<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tab Report untuk BPJS dan PPh 21 (Fase 3b). Hanya melihat/ekspor slip yang sudah dihitung — tanpa Create/Edit/Delete.
 *   finance.bpjs.report     page   (tab kedua BPJS)
 *   finance.pph21.report    page   (tab kedua PPh 21)
 * ATURAN BAKU MenuRegistrar: slug baru hanya aktif untuk EC Administrator.
 */
return new class extends Migration
{
    public function up(): void
    {
        MenuRegistrar::register('finance.bpjs', ['finance.bpjs.report' => 'Report'], 2, 'page');
        MenuRegistrar::register('finance.pph21', ['finance.pph21.report' => 'Report'], 2, 'page');
        $this->flushPermCache();
    }

    public function down(): void
    {
        MenuRegistrar::remove(['finance.bpjs.report', 'finance.pph21.report']);
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
