<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Menu Access untuk External → Timesheet & Payment (menambah ke seksi External yang sudah ada):
 *
 *   Timesheet & Payment  general.external.timesheet            (ada) tab Timesheets — kotak C/E/D kini ditegakkan:
 *                                                                C isi lembar atas nama konsultan · E ubah lembar / hapus Draft
 *   Invoices & Payments  general.external.timesheet.invoices   page  tab Invoices & Payments (verifikasi & Paid mengikuti penyetuju)
 *   Settings             general.external.timesheet.settings   page  C tambah langkah/pengecualian · E ubah · D hapus
 *   View Amounts         general.external.timesheet.amount     function (SENSITIF: tarif, bruto, jumlah invoice)
 *   My Timesheet & Invoice general.external.my-timesheet       page  halaman konsultan sendiri (self-service)
 *
 * ATURAN BAKU MenuRegistrar: slug baru hanya aktif untuk EC Administrator. Slug `…timesheet` yang SUDAH ada TIDAK di-reset:
 * hanya baris EC Administrator yang diberi kotak C/E/D, grant role lain dibiarkan seperti keputusan pemilik.
 */
return new class extends Migration
{
    private const NEW_PAGES = [
        'general.external.timesheet.invoices' => 'Invoices & Payments',
        'general.external.timesheet.settings' => 'Timesheet Settings',
        'general.external.my-timesheet'       => 'My Timesheet & Invoice',
    ];

    public function up(): void
    {
        if (!DB::table('menu')->where('slug', 'general.external')->exists()) {
            return;
        }

        MenuRegistrar::register('general.external', self::NEW_PAGES, 7, 'page');
        MenuRegistrar::register('general.external', ['general.external.timesheet.amount' => 'View Amounts'], 20, 'function');

        // Kotak C/E/D pada tiga slug yang menegakkannya — hanya untuk EC Administrator (admin yang membagikan ke role lain).
        MenuRegistrar::grantToAdminAndRoles(['general.external.timesheet.settings'], [], ['create', 'edit', 'delete']);
        $adminRole = (int) DB::table('employee_role')->where('name', 'EC Administrator')->value('id');
        $timesheet = DB::table('menu')->where('slug', 'general.external.timesheet')->value('id');
        if ($adminRole && $timesheet) {
            DB::table('role_menu')->where('role_id', $adminRole)->where('menu_id', $timesheet)
                ->update(['can_create' => 1, 'can_edit' => 1, 'can_delete' => 1, 'updated_at' => now()]);
        }

        $this->flushPermCache();
    }

    public function down(): void
    {
        MenuRegistrar::remove([...array_keys(self::NEW_PAGES), 'general.external.timesheet.amount']);
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
