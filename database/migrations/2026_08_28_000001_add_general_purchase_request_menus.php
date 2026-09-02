<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Slug Menu Access untuk sub-modul HR & General → Purchase Request.
 *
 * Mengikuti konvensi granular yang sama dengan Attendance, Overtime, dan
 * Reimbursement:
 *   {induk}.{anak}          -> halaman
 *   {induk}.{anak}.{aksi}   -> tombol / kemampuan di dalam halaman
 *
 * TUJUH SLUG, BUKAN DELAPAN — `general.purchase-request.import` SENGAJA TIDAK
 * DIDAFTARKAN (Keputusan D130). Pada Reimbursement slug `.import` didaftarkan
 * lebih dulu meski halamannya menyusul, dan itu keputusan yang benar di sana
 * karena halamannya memang direncanakan. Di sini impor tidak dibangun sama
 * sekali: PR biasanya 1-5 baris dan lahir dari kebutuhan harian, bukan dari
 * berkas. Slug yang halamannya tidak akan pernah ada hanya membingungkan pemilik
 * sistem saat membagi izin di Control Center.
 *
 * DUA SLUG DIPROMOSIKAN KE TINGKAT ATAS di migrasi ini juga:
 *
 *   general.my-purchase-request  — supaya pemilik sistem yang hanya ingin memberi
 *                                  hak MENGAJUKAN tidak terdorong ikut mencentang
 *                                  induk `general` (Keputusan D69-D71)
 *   general.purchase-request     — supaya penyetuju GA/Finance tidak perlu diberi
 *                                  slug induk `general` hanya agar dropdown-nya
 *                                  dirender (alasan yang sama dengan migrasi
 *                                  2026_08_25_000001 untuk Reimbursement)
 *
 * Pada Reimbursement promosi kedua itu dikerjakan migrasi terpisah karena
 * kebutuhannya baru disadari kemudian. Di sini kebutuhannya sudah diketahui sejak
 * awal, jadi tidak ada gunanya memecahnya menjadi dua migrasi.
 *
 * `general.purchase-request.approve` vs `.manage` sengaja dipisah (pola D77):
 *   approve : boleh MEMBUKA halaman pengelolaan dan bertindak pada langkah
 *             persetujuan yang sedang menunggu dirinya
 *   manage  : boleh MENGUBAH dan MENGHAPUS dokumen — termasuk yang bagi karyawan
 *             sudah tertutup
 *
 * Keadaan awal grant diatur MenuRegistrar: aktif HANYA untuk EC Administrator,
 * mati untuk seluruh role lain.
 *
 * 🔴 CATATAN OPERASIONAL yang ditemukan saat merancang sub-modul ini:
 * role default seluruh karyawan adalah 55 `User System Registered` (210 orang),
 * BUKAN role 3 `EC User` (3 orang) maupun 69 `ESS` (0 orang). Role 55 hari ini
 * hanya punya satu grant (`ess.my_leave_permit`), sehingga slug ESS modul ini —
 * termasuk `general.my-purchase-request` — perlu diberikan kepadanya lewat
 * Control Center sebelum karyawan biasa dapat membukanya. Migrasi TIDAK
 * memberikannya, sesuai aturan baku MenuRegistrar.
 */
return new class extends Migration
{
    private const PARENT_SLUG = 'general';

    /** Slug yang dipromosikan ke tingkat atas sidebar. */
    private const PROMOTED = [
        'general.my-purchase-request',
        'general.purchase-request',
    ];

    /** Halaman yang muncul sebagai item navigasi. */
    private const PAGES = [
        'general.my-purchase-request'       => 'Purchase Request',
        'general.purchase-request'          => 'Purchase Request Management',
        'general.settings.purchase-request' => 'Settings — Purchase Request Rules',
    ];

    /** Kemampuan di dalam halaman: tombol, ekspor, persetujuan. */
    private const FUNCTIONS = [
        'general.purchase-request.approve' => 'Purchase Request — Approve / Reject',
        'general.purchase-request.manage'  => 'Purchase Request — Edit / Delete',
        'general.purchase-request.export'  => 'Purchase Request — Export Excel',
        'general.purchase-request.create'  => 'Purchase Request — Create On Behalf',
    ];

    public function up(): void
    {
        MenuRegistrar::register(self::PARENT_SLUG, self::PAGES, 70, 'page');
        MenuRegistrar::register(self::PARENT_SLUG, self::FUNCTIONS, 80, 'function');

        // Promosi ke tingkat atas. Memakai UPDATE, bukan hapus-buat-ulang, supaya
        // grant di `role_menu` tetap utuh — grant merujuk `menu.id`, bukan posisi
        // maupun nama (Keputusan D71).
        //
        // order_seq 6 menyamai My Attendance, Overtime, dan Reimbursement; dengan
        // id yang lebih besar, barisnya muncul tepat SESUDAHNYA di daftar Menu
        // Access. Urutan di sidebar sendiri diatur berkas Blade, bukan kolom ini.
        DB::table('menu')->whereIn('slug', self::PROMOTED)->update([
            'parent_id'  => null,
            'order_seq'  => 6,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Dikembalikan ke bawah induknya lebih dulu supaya keadaan tabel `menu`
        // konsisten bila penghapusannya gagal di tengah jalan.
        $parentId = DB::table('menu')->where('slug', self::PARENT_SLUG)->value('id');

        DB::table('menu')->where('slug', 'general.my-purchase-request')->update([
            'parent_id'  => $parentId,
            'order_seq'  => 70,
            'updated_at' => now(),
        ]);

        DB::table('menu')->where('slug', 'general.purchase-request')->update([
            'parent_id'  => $parentId,
            'order_seq'  => 71,
            'updated_at' => now(),
        ]);

        MenuRegistrar::remove(array_keys(self::FUNCTIONS));
        MenuRegistrar::remove(array_keys(self::PAGES));
    }
};
