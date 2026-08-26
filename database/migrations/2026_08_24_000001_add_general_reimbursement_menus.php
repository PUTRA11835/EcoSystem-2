<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Slug Menu Access untuk sub-modul HR & General → Reimbursement.
 *
 * Mengikuti konvensi granular yang sama dengan Attendance dan Overtime:
 *   {induk}.{anak}          -> halaman
 *   {induk}.{anak}.{aksi}   -> tombol / kemampuan di dalam halaman
 *
 * KEDELAPAN SLUG DIDAFTARKAN SEKALIGUS, termasuk `general.reimbursement.import`
 * yang halamannya baru dibangun di langkah R7. Alasannya bukan kemalasan:
 * mendaftarkan slug yang menyusul memerlukan migrasi kedua, sementara
 * mendaftarkannya sekarang TIDAK memberi akses kepada siapa pun — MenuRegistrar
 * hanya memberi grant awal ke EC Administrator. Biaya menundanya lebih besar
 * daripada biaya menyiapkannya (Keputusan D103 dst., jawaban R8).
 *
 * Penempatan `general.my-reimbursement`:
 * Terdaftar sebagai ANAK `general` supaya seluruh izin modul HR berkumpul di satu
 * cabang saat pemilik sistem mengaturnya, lalu DIPROMOSIKAN ke tingkat atas di
 * migrasi ini juga. Pada Overtime promosi itu dikerjakan migrasi terpisah
 * (2026_08_21_000001) karena kebutuhannya baru disadari kemudian; di sini
 * kebutuhannya sudah diketahui sejak awal, jadi tidak ada gunanya memecahnya
 * menjadi dua migrasi.
 *
 * Alasan promosinya tetap sama seperti My Attendance dan Overtime: selama baris
 * ini bersarang di bawah "HR & General", pemilik sistem yang hanya ingin memberi
 * hak MENGAJUKAN terdorong ikut mencentang induknya — dan itulah yang dulu
 * memunculkan dropdown terbuka kosong (Keputusan D69–D71).
 *
 * `general.reimbursement.approve` vs `.manage` sengaja dipisah, seperti pada
 * Overtime (Keputusan D77):
 *   approve : boleh MEMBUKA halaman pengelolaan dan bertindak pada langkah
 *             persetujuan yang sedang menunggu dirinya
 *   manage  : boleh MENGUBAH dan MENGHAPUS dokumen — termasuk yang bagi karyawan
 *             sudah tertutup
 * Tanpa pemisahan itu, memberi hak meninjau otomatis memberi hak menghapus
 * dokumen keuangan.
 *
 * Keadaan awal grant diatur MenuRegistrar: aktif HANYA untuk EC Administrator,
 * mati untuk seluruh role lain. Pemberian ke role lain — HO GA (28/29/30) dan
 * HO Finance (25/26/27) — dilakukan pemilik sistem lewat Control Center ->
 * Menu Access.
 */
return new class extends Migration
{
    private const PARENT_SLUG = 'general';

    /** Slug yang dipromosikan ke tingkat atas sidebar. */
    private const SLUG_EMPLOYEE = 'general.my-reimbursement';

    /** Halaman yang muncul sebagai item navigasi. */
    private const PAGES = [
        'general.my-reimbursement'       => 'Reimbursement',
        'general.reimbursement'          => 'Reimbursement Management',
        'general.settings.reimbursement' => 'Settings — Reimbursement Rules',
    ];

    /** Kemampuan di dalam halaman: tombol, ekspor, persetujuan, impor. */
    private const FUNCTIONS = [
        'general.reimbursement.approve' => 'Reimbursement — Approve / Reject',
        'general.reimbursement.manage'  => 'Reimbursement — Edit / Delete',
        'general.reimbursement.export'  => 'Reimbursement — Export Excel',
        'general.reimbursement.create'  => 'Reimbursement — Create On Behalf',
        'general.reimbursement.import'  => 'Reimbursement — Import Excel',
    ];

    public function up(): void
    {
        MenuRegistrar::register(self::PARENT_SLUG, self::PAGES, 50, 'page');
        MenuRegistrar::register(self::PARENT_SLUG, self::FUNCTIONS, 60, 'function');

        // Promosi ke tingkat atas. Memakai UPDATE, bukan hapus-buat-ulang, supaya
        // grant di `role_menu` tetap utuh — grant merujuk `menu.id`, bukan posisi
        // maupun nama (Keputusan D71).
        //
        // order_seq 6 menyamai My Attendance dan Overtime; dengan id yang lebih
        // besar, barisnya muncul tepat SESUDAHNYA — persis urutan di sidebar.
        DB::table('menu')->where('slug', self::SLUG_EMPLOYEE)->update([
            'parent_id'  => null,
            'order_seq'  => 6,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Dikembalikan ke bawah induknya lebih dulu supaya keadaan tabel `menu`
        // konsisten bila penghapusannya gagal di tengah jalan.
        DB::table('menu')->where('slug', self::SLUG_EMPLOYEE)->update([
            'parent_id'  => DB::table('menu')->where('slug', self::PARENT_SLUG)->value('id'),
            'order_seq'  => 50,
            'updated_at' => now(),
        ]);

        MenuRegistrar::remove(array_keys(self::FUNCTIONS));
        MenuRegistrar::remove(array_keys(self::PAGES));
    }
};
