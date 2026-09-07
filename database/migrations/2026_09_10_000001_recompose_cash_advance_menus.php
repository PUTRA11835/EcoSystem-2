<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menata ulang menu Cash Advance sesuai keputusan pemilik sistem 7 September 2026
 * (jawaban C2 dan C4) — Keputusan D141 & D142.
 *
 * ── KENAPA MIGRASI BARU, BUKAN MENYUNTING 2026_09_07_000001 ────────────────
 *
 * 🔴 Migrasi itu sudah DIKOMIT (`8a4e002 Database CashAdvance`) dan sudah
 * TERDORONG ke `origin/aldy_hr`. Begitu sebuah migrasi meninggalkan mesin
 * penulisnya, ia berhenti menjadi berkas dan berubah menjadi RIWAYAT: rekan yang
 * sudah menjalankannya punya baris `migrations` yang berkata "sudah dijalankan",
 * dan menyunting isinya TIDAK akan pernah membuat basis data mereka ikut
 * berubah. Yang terjadi justru diam-diam: dua orang memegang berkas yang sama
 * dengan skema yang berbeda, tanpa satu pun galat yang memberi tahu.
 *
 * Aturan yang berlaku sejak sekarang untuk sub-modul ini: **migrasi yang sudah
 * dikomit hanya boleh diubah oleh migrasi baru.**
 *
 * ── APA YANG DIUBAH ────────────────────────────────────────────────────────
 *
 * 1. PENAMAAN (C2 / D142) — pembedanya jadi singkatan dalam kurung:
 *
 *      general.cash-advance         'Cash Advance Management'        -> 'Cash Advance (CA)'
 *      general.cash-advance-report  'Cash Advance Report Management' -> 'Cash Advance Report (CAR)'
 *
 *    Sisi karyawan tidak diubah dan memang sudah benar: 'Cash Advance' dan
 *    'Cash Advance Report'. Yang penting dipegang: pembedanya HARUS tetap ada,
 *    karena dua baris bernama persis sama di layar Menu Access membuat pembagian
 *    izin jadi tebak-tebakan.
 *
 * 2. LETAK HALAMAN SETELAN (C4 / D141) — pindah induk:
 *
 *      general.settings.cash-advance  (induk `general`)   DIHAPUS
 *      management.cash-advance-settings (induk `management`)  DIBUAT
 *
 *    Tiga sub-modul sebelumnya menaruh setelannya di `general.settings.*`, dan
 *    akibatnya baru terasa saat izin dibagikan: memberi seseorang hak mengatur
 *    aturan uang muka berarti memberinya slug yang serumah dengan absensi,
 *    lembur, dan reimbursement. Orang yang mengatur uang perusahaan belum tentu
 *    orang yang mengurus kepegawaian — dan seringnya memang bukan.
 *
 *    Dengan induk `management`, pemilik sistem bebas memberikannya ke role mana
 *    pun (Finance, Accounting, Direksi) TANPA ikut membuka apa pun di dropdown
 *    HR & General.
 *
 *    Konsekuensi yang wajib diikuti langkah berikutnya:
 *      - URL-nya  /management/cash-advance-settings
 *      - Nama rutenya `management.cash-advance-settings.*`
 *      - Item sidebarnya masuk dropdown **Management**, bukan HR & General
 *    Berkas rutenya tetap `routes/hr-general.php` — nama BERKAS tidak mengikat
 *    prefix URL, dan menaruhnya di sana membuat sub-modul ini tetap terbaca
 *    dalam satu tempat.
 *
 * 🔴 Slug lama DIHAPUS, bukan di-`UPDATE` namanya. Alasannya bukan kerapian:
 * `general.settings.cash-advance` belum pernah dibagikan ke role mana pun selain
 * EC Administrator (halamannya belum dibuat), jadi tidak ada grant yang hilang.
 * Kalau ia SUDAH dibagikan, jalan yang benar adalah UPDATE `parent_id` seperti
 * pola promosi D71 — memindahkan baris, bukan menggantinya, supaya `role_menu`
 * yang menunjuk `menu.id` tetap utuh.
 */
return new class extends Migration
{
    private const RENAMES = [
        'general.cash-advance'        => 'Cash Advance (CA)',
        'general.cash-advance-report' => 'Cash Advance Report (CAR)',
    ];

    /** Nama sebelum migrasi ini, dipakai down(). */
    private const PREVIOUS_NAMES = [
        'general.cash-advance'        => 'Cash Advance Management',
        'general.cash-advance-report' => 'Cash Advance Report Management',
    ];

    private const OLD_SETTINGS_SLUG = 'general.settings.cash-advance';
    private const OLD_SETTINGS_NAME = 'Settings — Cash Advance & CAR Rules';

    private const NEW_SETTINGS = [
        'management.cash-advance-settings' => 'Cash Advance Settings',
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $slug => $name) {
            DB::table('menu')->where('slug', $slug)->update([
                'name'       => $name,
                'updated_at' => now(),
            ]);
        }

        // order_seq 7 menempatkannya tepat sesudah "ESS Settings" (seq 3) dan
        // "Master Employee Settings" (seq 4) di dropdown Management.
        MenuRegistrar::register('management', self::NEW_SETTINGS, 7, 'page');

        MenuRegistrar::remove([self::OLD_SETTINGS_SLUG]);
    }

    public function down(): void
    {
        MenuRegistrar::remove(array_keys(self::NEW_SETTINGS));

        MenuRegistrar::register('general', [
            self::OLD_SETTINGS_SLUG => self::OLD_SETTINGS_NAME,
        ], 94, 'page');

        foreach (self::PREVIOUS_NAMES as $slug => $name) {
            DB::table('menu')->where('slug', $slug)->update([
                'name'       => $name,
                'updated_at' => now(),
            ]);
        }
    }
};
