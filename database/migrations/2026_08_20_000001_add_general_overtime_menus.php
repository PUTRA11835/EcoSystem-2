<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Slug Menu Access untuk sub-modul HR & General → Overtime.
 *
 * Mengikuti konvensi granular yang sama dengan Attendance:
 *   {induk}.{anak}          -> halaman
 *   {induk}.{anak}.{aksi}   -> tombol / kemampuan di dalam halaman
 *
 * Catatan penempatan `general.my-overtime`:
 * Sama seperti `general.my-attendance`, slug ini terdaftar sebagai ANAK
 * `general` supaya seluruh izin modul HR berkumpul di satu cabang saat pemilik
 * sistem mengaturnya, tetapi DIRENDER sebagai item tingkat atas di sidebar —
 * setiap karyawan mengajukan lembur, sementara hanya sebagian yang meninjau.
 *
 * Tentang `general.overtime.approve` vs `general.overtime.manage`:
 * Keduanya sengaja dipisah karena menjawab pertanyaan yang berbeda.
 *   approve : boleh MEMBUKA halaman peninjauan dan bertindak pada langkah
 *             persetujuan yang sedang menunggu dirinya
 *   manage  : boleh mengubah pengajuan yang bagi karyawan sudah tertutup —
 *             yang sudah ditolak, atau yang periodenya sudah dikunci
 * Tanpa pemisahan ini, memberi hak meninjau otomatis memberi hak membongkar
 * pengajuan yang sudah selesai (Keputusan D77).
 *
 * Keadaan awal grant diatur MenuRegistrar: aktif HANYA untuk EC Administrator,
 * mati untuk seluruh role lain. Pemberian ke role lain dilakukan pemilik sistem
 * lewat Control Center -> Menu Access.
 */
return new class extends Migration
{
    private const PARENT_SLUG = 'general';

    /** Halaman yang muncul sebagai item navigasi. */
    private const PAGES = [
        'general.my-overtime'       => 'My Overtime',
        'general.overtime'          => 'Overtime — Review',
        'general.settings.overtime' => 'Settings — Overtime Rules',
    ];

    /** Kemampuan di dalam halaman: tombol, ekspor, persetujuan. */
    private const FUNCTIONS = [
        'general.overtime.approve' => 'Overtime — Approve / Reject',
        'general.overtime.manage'  => 'Overtime — Manage Locked / Rejected Requests',
        'general.overtime.export'  => 'Overtime — Export Excel',
    ];

    public function up(): void
    {
        MenuRegistrar::register(self::PARENT_SLUG, self::PAGES, 30, 'page');
        MenuRegistrar::register(self::PARENT_SLUG, self::FUNCTIONS, 40, 'function');
    }

    public function down(): void
    {
        MenuRegistrar::remove(array_keys(self::FUNCTIONS));
        MenuRegistrar::remove(array_keys(self::PAGES));
    }
};
