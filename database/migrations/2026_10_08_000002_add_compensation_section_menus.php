<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Slug izin seksi "Compensation" (Payroll Fase 0; HC-D21): komponen gaji + penanda PTKP/BPJS.
 *
 *   employee.section.compensation[.view|.update]  → Master > Employee > detail (data ORANG LAIN)
 *
 * Sengaja TANPA padanan `my-profile.section.compensation.*`: karyawan tidak mengubah gajinya sendiri, dan
 * seksi ini otomatis tersembunyi di My Profile. Sensitif (lihat config/menu_access.php) dan menjadi syarat
 * `TableAccess` untuk `employee_salary_components`.
 *
 * ATURAN BAKU MenuRegistrar: slug baru hanya aktif untuk EC Administrator; role HR dibuka di Menu Access.
 * Hanya menambah baris menu; down() menghapusnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        MenuRegistrar::register('master.employee', ['employee.section.compensation' => 'Compensation'], 21, 'group');
        MenuRegistrar::register('employee.section.compensation', [
            'employee.section.compensation.view'   => 'View Compensation',
            'employee.section.compensation.update' => 'Update Compensation',
        ], 1);
    }

    public function down(): void
    {
        MenuRegistrar::remove([
            'employee.section.compensation.update',
            'employee.section.compensation.view',
            'employee.section.compensation',
        ]);
    }
};
