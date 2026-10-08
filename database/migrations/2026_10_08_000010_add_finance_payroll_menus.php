<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Menu Access Payroll (Fase 3), di bawah akar `finance`:
 *
 *   finance.payroll                 (group; induk tab)
 *   ├─ finance.payroll.periods      page      C buat periode & penyesuaian · E hitung/hitung ulang · D hapus periode terbuka
 *   ├─ finance.payroll.settings     page      E ubah kebijakan perhitungan
 *   ├─ finance.payroll.simulation   page      hitung tanpa menyimpan
 *   ├─ finance.payroll.approve      function  menyetujui / membuka kembali periode   (SoD: terpisah)
 *   ├─ finance.payroll.pay          function  menandai dibayar
 *   └─ finance.payroll.lock         function  mengunci periode
 *
 * Tiga hak alur dipisah supaya penyusun, penyetuju, dan pengunci dapat orang berbeda. Slug sensitif
 * (lihat config/menu_access.php). ATURAN BAKU MenuRegistrar: slug baru hanya aktif untuk EC Administrator.
 */
return new class extends Migration
{
    private const PAGES = [
        'finance.payroll.periods'    => 'Periods',
        'finance.payroll.settings'   => 'Settings',
        'finance.payroll.simulation' => 'Simulation',
    ];

    private const FUNCTIONS = [
        'finance.payroll.approve' => 'Approve payroll',
        'finance.payroll.pay'     => 'Mark payroll paid',
        'finance.payroll.lock'    => 'Lock payroll',
    ];

    public function up(): void
    {
        if (!MenuRegistrar::register('finance', ['finance.payroll' => 'Payroll'], 10, 'group')) {
            return;
        }
        MenuRegistrar::register('finance.payroll', self::PAGES, 1, 'page');
        MenuRegistrar::register('finance.payroll', self::FUNCTIONS, 10, 'function');

        MenuRegistrar::grantToAdminAndRoles(['finance.payroll.periods'], [], ['create', 'edit', 'delete']);
        MenuRegistrar::grantToAdminAndRoles(['finance.payroll.settings'], [], ['edit']);

        $this->flushPermCache();
    }

    public function down(): void
    {
        MenuRegistrar::remove([...array_keys(self::FUNCTIONS), ...array_keys(self::PAGES), 'finance.payroll']);
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
