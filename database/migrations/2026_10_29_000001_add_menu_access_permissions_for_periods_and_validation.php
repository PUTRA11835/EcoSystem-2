<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Izin fungsi baru supaya Period Management dan cakupan MD Validation sepenuhnya lewat Menu Access.
 *
 * Sebelumnya ketiganya ditentukan oleh daftar RoleId di dalam controller, sehingga administrator tidak
 * bisa mengubahnya dari Control Center dan sebuah menu bisa tampil tetapi halamannya menolak (403).
 *
 * Pemberian awal MENIRU perilaku lama supaya tidak ada yang berubah sampai administrator mengubahnya:
 *   rpmo.periods.manage-global      : EC Administrator + Delivery RPMO Head
 *   rpmo.periods.manage-project     : EC Administrator + Delivery Project Head
 *   rpmo.periods.manage-support     : EC Administrator + Delivery Support Head
 *   reporting.validation.view-all   : EC Administrator + Delivery Support Head
 * (Role diresolve lewat NAMA, bukan ID, karena ID bisa berbeda antar database.)
 */
return new class extends Migration
{
    private const PERIOD_PARENT = 'rpmo.periods';
    private const VALIDATION_PARENT = 'reporting.validation';

    private const GRANTS = [
        'rpmo.periods.manage-global'    => ['Delivery RPMO Head'],
        'rpmo.periods.manage-project'   => ['Delivery Project Head'],
        'rpmo.periods.manage-support'   => ['Delivery Support Head'],
        'reporting.validation.view-all' => ['Delivery Support Head'],
    ];

    public function up(): void
    {
        // Pada database baru (migrate:fresh --seed) tabel menu masih kosong saat migrasi berjalan; izin ini lalu
        // dibuat oleh MenuSeeder. Karena itu menu induk yang belum ada DILEWATI, sama seperti migrasi menu lainnya.
        $registered = [];

        if (MenuRegistrar::register(self::PERIOD_PARENT, [
            'rpmo.periods.manage-global'  => 'Period Management: Manage Global (create, open, close, force close, RPMO decisions)',
            'rpmo.periods.manage-project' => 'Period Management: Manage Project Domain',
            'rpmo.periods.manage-support' => 'Period Management: Manage Support Domain',
        ], 10, 'function')) {
            array_push($registered, 'rpmo.periods.manage-global', 'rpmo.periods.manage-project', 'rpmo.periods.manage-support');
        }

        if (MenuRegistrar::register(self::VALIDATION_PARENT, [
            'reporting.validation.view-all' => 'MD Validation: View All Employees',
        ], 20, 'function')) {
            $registered[] = 'reporting.validation.view-all';
        }

        foreach (self::GRANTS as $slug => $roleNames) {
            if (in_array($slug, $registered, true)) {
                MenuRegistrar::grantToAdminAndRoles([$slug], $roleNames);
            }
        }
    }

    public function down(): void
    {
        MenuRegistrar::remove(array_keys(self::GRANTS));
    }
};
