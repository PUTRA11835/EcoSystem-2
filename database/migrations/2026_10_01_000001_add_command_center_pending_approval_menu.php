<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Slug Menu Access untuk kotak "Pending Approval" di Command Center
 * (Dashboard — HC-D36, lanjutan dari HC-D25 "Sidebar Favorit").
 *
 * 🔴 LATAR BELAKANG. Command Center punya dua bagian: (1) pintasan menu favorit
 * + semua menu — TERBUKA untuk siapa pun yang sudah login, sama seperti fitur
 * pin sidebar HC-D25 yang sudah ada; (2) ringkasan jumlah dokumen yang menunggu
 * persetujuan orang itu, dihitung lintas 5 modul alur kerja (Overtime,
 * Reimbursement, Purchase Request, Cash Advance, Cash Advance Report) lewat
 * `pendingIdsFor()` yang sudah ada di masing-masing service.
 *
 * Pemilik sistem meminta bagian (2) SAJA diatur lewat Menu Access — bukan
 * otomatis tampil untuk semua orang — karena tidak semua karyawan adalah
 * penyetuju, sementara bagian (1) harus tetap terbuka untuk semua. Satu slug
 * BARU, type `function` (bukan halaman — tidak ada rute baru, hanya menyalakan
 * query & tampilan kotak ini di view Dashboard).
 *
 * Keadaan awal grant diatur MenuRegistrar: aktif HANYA untuk EC Administrator,
 * mati untuk seluruh role lain — pemilik sistem membagikannya secara manual
 * lewat Control Center -> Menu Access ke role yang memang berperan sebagai
 * penyetuju (mis. HR Head, Finance).
 */
return new class extends Migration
{
    private const SLUG = 'general.command-center.pending-approval';
    private const NAME = 'Command Center — Pending Approval';

    public function up(): void
    {
        MenuRegistrar::register('general', [self::SLUG => self::NAME], 130, 'function');
    }

    public function down(): void
    {
        MenuRegistrar::remove([self::SLUG]);
    }
};
