<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Slug Menu Access untuk sub-modul HR & General → Cash Advance (CA) dan
 * Cash Advance Report (CAR).
 *
 * Mengikuti konvensi granular yang sama dengan empat sub-modul sebelumnya:
 *   {induk}.{anak}          -> halaman
 *   {induk}.{anak}.{aksi}   -> tombol / kemampuan di dalam halaman
 *
 * ── TIGA BELAS SLUG, SATU SLUG SETELAN ─────────────────────────────────────
 *
 * CA dan CAR mendapat enam slug masing-masing (satu ESS, satu pengelolaan,
 * empat kemampuan), tetapi HANYA SATU slug setelan untuk berdua:
 * `general.settings.cash-advance`. Alasannya bukan penghematan, melainkan bahwa
 * keduanya adalah SATU siklus bisnis — batas nominal CA dan tenggat pelaporan
 * CAR selalu diatur bersamaan, oleh orang yang sama, dalam satu percakapan.
 * Memecahnya jadi dua slug memaksa pemilik sistem mengingat bahwa separuh
 * aturannya tinggal di layar lain. Bila kelak izinnya memang perlu dipisah,
 * menambah slug kedua adalah migrasi satu baris; menyatukan dua slug yang
 * terlanjur dibagikan jauh lebih mahal.
 *
 * ── EMPAT SLUG DIPROMOSIKAN KE TINGKAT ATAS ────────────────────────────────
 *
 *   general.my-cash-advance          } supaya pemilik sistem yang hanya ingin
 *   general.my-cash-advance-report   } memberi hak MENGAJUKAN tidak terdorong
 *                                      ikut mencentang induk `general` (D69-D71)
 *
 *   general.cash-advance             } supaya penyetuju Finance tidak perlu
 *   general.cash-advance-report      } diberi slug induk `general` hanya agar
 *                                      dropdown "HR & General" dirender
 *
 * Pola yang sama sudah dipakai Reimbursement (migrasi 2026_08_25_000001) dan
 * Purchase Request (2026_08_28_000001).
 *
 * `.approve` vs `.manage` sengaja dipisah (pola D77):
 *   approve : boleh MEMBUKA halaman pengelolaan dan bertindak pada langkah
 *             persetujuan yang sedang menunggu dirinya
 *   manage  : boleh MENGUBAH dan MENGHAPUS dokumen — termasuk yang bagi karyawan
 *             sudah tertutup
 *
 * Keadaan awal grant diatur MenuRegistrar: aktif HANYA untuk EC Administrator,
 * mati untuk seluruh role lain.
 *
 * 🔴 CATATAN OPERASIONAL. Role default seluruh karyawan adalah
 * 55 `User System Registered`. Slug ESS modul ini — `general.my-cash-advance`
 * dan `general.my-cash-advance-report` — perlu diberikan kepadanya lewat Control
 * Center sebelum karyawan biasa dapat membukanya. Migrasi TIDAK memberikannya,
 * sesuai aturan baku MenuRegistrar.
 */
return new class extends Migration
{
    private const PARENT_SLUG = 'general';

    /** Slug yang dipromosikan ke tingkat atas sidebar. */
    private const PROMOTED = [
        'general.my-cash-advance',
        'general.cash-advance',
        'general.my-cash-advance-report',
        'general.cash-advance-report',
    ];

    /** Halaman yang muncul sebagai item navigasi. */
    private const PAGES = [
        'general.my-cash-advance'        => 'Cash Advance',
        'general.cash-advance'           => 'Cash Advance Management',
        'general.my-cash-advance-report' => 'Cash Advance Report',
        'general.cash-advance-report'    => 'Cash Advance Report Management',
        'general.settings.cash-advance'  => 'Settings — Cash Advance & CAR Rules',
    ];

    /** Kemampuan di dalam halaman: tombol, ekspor, persetujuan. */
    private const FUNCTIONS = [
        'general.cash-advance.approve'        => 'Cash Advance — Approve / Reject',
        'general.cash-advance.manage'         => 'Cash Advance — Edit / Delete',
        'general.cash-advance.export'         => 'Cash Advance — Export Excel',
        'general.cash-advance.create'         => 'Cash Advance — Create On Behalf',
        'general.cash-advance-report.approve' => 'Cash Advance Report — Approve / Reject',
        'general.cash-advance-report.manage'  => 'Cash Advance Report — Edit / Delete',
        'general.cash-advance-report.export'  => 'Cash Advance Report — Export Excel',
        'general.cash-advance-report.create'  => 'Cash Advance Report — Create On Behalf',
    ];

    public function up(): void
    {
        MenuRegistrar::register(self::PARENT_SLUG, self::PAGES, 90, 'page');
        MenuRegistrar::register(self::PARENT_SLUG, self::FUNCTIONS, 100, 'function');

        // Promosi ke tingkat atas. Memakai UPDATE, bukan hapus-buat-ulang, supaya
        // grant di `role_menu` tetap utuh — grant merujuk `menu.id`, bukan posisi
        // maupun nama (Keputusan D71).
        //
        // order_seq 6 menyamai My Attendance, Overtime, Reimbursement, dan
        // Purchase Request; dengan id yang lebih besar, barisnya muncul tepat
        // SESUDAHNYA di daftar Menu Access. Urutan di sidebar sendiri diatur
        // berkas Blade, bukan kolom ini.
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

        $seq = 90;
        foreach (self::PROMOTED as $slug) {
            DB::table('menu')->where('slug', $slug)->update([
                'parent_id'  => $parentId,
                'order_seq'  => $seq++,
                'updated_at' => now(),
            ]);
        }

        MenuRegistrar::remove(array_keys(self::FUNCTIONS));
        MenuRegistrar::remove(array_keys(self::PAGES));
    }
};
