<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Slug Menu Access untuk sub-modul HR & General → Attendance.
 *
 * Menu induk `general` (id 20) sudah ada sejak lama tetapi belum punya satu pun
 * anak. Modul ini menjadi anak pertamanya.
 *
 * Mengikuti konvensi granular yang berlaku di basis kode:
 *   {induk}.{anak}          -> halaman
 *   {induk}.{anak}.{aksi}   -> tombol / kemampuan di dalam halaman
 *
 * Catatan penempatan `general.my-attendance`:
 * Slug ini terdaftar sebagai ANAK `general` supaya seluruh izin modul HR
 * berkumpul di satu cabang saat pemilik sistem mengaturnya di Control Center.
 * Penempatannya di SIDEBAR berbeda: ia dirender sebagai item tingkat atas,
 * bukan di dalam dropdown, karena setiap karyawan melakukan presensi sementara
 * hanya sebagian yang mengakses HR & General. Letak izin dan letak tampilan
 * memang tidak harus sama — sidebar di aplikasi ini dirender manual di Blade.
 *
 * Keadaan awal grant diatur MenuRegistrar: aktif HANYA untuk EC Administrator,
 * mati untuk seluruh role lain. Pemberian ke role HR dan ke seluruh karyawan
 * dilakukan pemilik sistem lewat Control Center -> Menu Access, atau lewat
 * perintah `php artisan hr:grant-my-attendance` untuk pembagian massal.
 */
return new class extends Migration
{
    private const PARENT_SLUG = 'general';

    /** Halaman yang muncul sebagai item navigasi. */
    private const PAGES = [
        'general.attendance'             => 'Attendance — Daily Recap',
        'general.attendance.correction'  => 'Attendance — Corrections Review',
        'general.my-attendance'          => 'My Attendance',
        'general.settings'               => 'HR Settings',
        'general.settings.branches'      => 'Settings — Branches',
        'general.settings.shifts'        => 'Settings — Shifts',
        'general.settings.attendance'    => 'Settings — Attendance Rules',
    ];

    /** Kemampuan di dalam halaman: tombol, ekspor, persetujuan. */
    private const FUNCTIONS = [
        'general.attendance.monthly'            => 'Attendance — Monthly Recap',
        'general.attendance.export'             => 'Attendance — Export Excel',
        'general.attendance.correction.approve' => 'Attendance — Approve / Reject Correction',
        'general.settings.branches.manage'      => 'Settings — Branches Create / Edit / Delete',
        'general.settings.shifts.manage'        => 'Settings — Shifts Create / Edit / Delete',
    ];

    public function up(): void
    {
        MenuRegistrar::register(self::PARENT_SLUG, self::PAGES, 10, 'page');
        MenuRegistrar::register(self::PARENT_SLUG, self::FUNCTIONS, 20, 'function');
    }

    public function down(): void
    {
        MenuRegistrar::remove(array_keys(self::FUNCTIONS));
        MenuRegistrar::remove(array_keys(self::PAGES));
    }
};
