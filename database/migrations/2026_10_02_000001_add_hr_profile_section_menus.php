<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Slug izin seksi "HR Profile" (Tahap 3, langkah H3.4; HC-D20, HC-D26).
 *
 * Mengikuti struktur seksi karyawan yang sudah ada (lihat 2026_07_02_200003):
 *   employee.section.hr_profile[.view|.update]   → halaman Master > Employee > detail (data ORANG LAIN)
 *   my-profile.section.hr_profile[.view|.update] → halaman My Profile (data DIRI SENDIRI)
 * CheckEmployeeSectionAccess memilih salah satunya menurut target request.
 *
 * ATURAN BAKU MenuRegistrar: slug baru HANYA aktif untuk EC Administrator, mati untuk role lain.
 * Jadi setelah migrasi, tab HR Profile tidak muncul bagi siapa pun selain administrator sampai
 * pemilik sistem mencentangnya di Control Center → Menu Access. Perilaku pengguna yang sudah live
 * TIDAK berubah. Tabel employee_hr_profile sudah tertutup bagi asisten AI (TableAccess) dan
 * baru terbuka bagi pemegang slug `employee.section.hr_profile.view` setelah migrasi ini berjalan.
 *
 * Hanya menambah baris menu/role_menu; down() menghapusnya beserta grant-nya.
 */
return new class extends Migration
{
    private const SLUGS = [
        'employee.section.hr_profile',
        'employee.section.hr_profile.view',
        'employee.section.hr_profile.update',
        'my-profile.section.hr_profile',
        'my-profile.section.hr_profile.view',
        'my-profile.section.hr_profile.update',
    ];

    public function up(): void
    {
        // Master > Employee > detail (order_seq 10–19 sudah dipakai 10 seksi lama)
        MenuRegistrar::register('master.employee', ['employee.section.hr_profile' => 'HR Profile'], 20, 'group');
        MenuRegistrar::register('employee.section.hr_profile', [
            'employee.section.hr_profile.view'   => 'View HR Profile',
            'employee.section.hr_profile.update' => 'Update HR Profile',
        ], 1);

        // My Profile (order_seq 1–10 sudah dipakai 10 seksi lama)
        MenuRegistrar::register('my-profile', ['my-profile.section.hr_profile' => 'HR Profile'], 11, 'group');
        MenuRegistrar::register('my-profile.section.hr_profile', [
            'my-profile.section.hr_profile.view'   => 'View HR Profile',
            'my-profile.section.hr_profile.update' => 'Update HR Profile',
        ], 1);
    }

    public function down(): void
    {
        MenuRegistrar::remove(array_reverse(self::SLUGS));
    }
};
