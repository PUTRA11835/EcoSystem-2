<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menyeragamkan nama slug KEMAMPUAN Cash Advance dengan aturan penamaan yang
 * ditetapkan pemilik sistem — Keputusan D142, dilengkapi 8 September 2026.
 *
 * ── ATURANNYA ──────────────────────────────────────────────────────────────
 *
 *   Sisi karyawan (ESS)      : "Cash Advance"        · "Cash Advance Report"
 *   Sisi admin / assignable  : "Cash Advance (CA)"   · "Cash Advance Report (CAR)"
 *
 * Dua slug HALAMAN sisi admin sudah memakai bentuk itu sejak migrasi
 * `2026_09_10_000001`. Yang belum: DELAPAN slug kemampuan (`.approve`,
 * `.manage`, `.export`, `.create` untuk masing-masing modul) — dan slug-slug
 * itulah yang paling banyak dibaca orang, karena merekalah yang dicentang satu
 * per satu di Control Center → Menu Access.
 *
 * 🔴 Kenapa ini bukan sekadar kosmetik. Di layar Menu Access, delapan baris ini
 * berdiri berdampingan dengan slug milik empat sub-modul lain. Tanpa singkatan,
 * "Cash Advance — Export Excel" dan "Cash Advance Report — Export Excel" hanya
 * berbeda satu kata di tengah kalimat, dan orang yang membagi izin harus membaca
 * teliti setiap baris. Dengan "(CA)" dan "(CAR)", keduanya terbaca sekilas.
 * Salah bagi izin pada modul yang mengeluarkan uang perusahaan bukan kesalahan
 * yang murah.
 *
 * ── MENGUBAH `name`, BUKAN `slug` ──────────────────────────────────────────
 * Yang diubah HANYA kolom `name`. Slug, `parent_id`, dan `order_seq` tidak
 * disentuh — grant di `role_menu` menunjuk `menu.id`, jadi nol izin yang hilang
 * (pola D71). Migrasi ini aman dijalankan pada basis data yang izinnya sudah
 * dibagikan.
 *
 * Ditulis sebagai migrasi BARU, bukan suntingan atas `2026_09_07_000001` yang
 * sudah dikomit dan terdorong ke remote — aturan D145.
 */
return new class extends Migration
{
    /** [slug => [nama sesudah, nama sebelum]] */
    private const RENAMES = [
        'general.cash-advance.approve' => [
            'Cash Advance (CA) — Approve / Reject',
            'Cash Advance — Approve / Reject',
        ],
        'general.cash-advance.manage' => [
            'Cash Advance (CA) — Edit / Delete',
            'Cash Advance — Edit / Delete',
        ],
        'general.cash-advance.export' => [
            'Cash Advance (CA) — Export Excel',
            'Cash Advance — Export Excel',
        ],
        'general.cash-advance.create' => [
            'Cash Advance (CA) — Create On Behalf',
            'Cash Advance — Create On Behalf',
        ],
        'general.cash-advance-report.approve' => [
            'Cash Advance Report (CAR) — Approve / Reject',
            'Cash Advance Report — Approve / Reject',
        ],
        'general.cash-advance-report.manage' => [
            'Cash Advance Report (CAR) — Edit / Delete',
            'Cash Advance Report — Edit / Delete',
        ],
        'general.cash-advance-report.export' => [
            'Cash Advance Report (CAR) — Export Excel',
            'Cash Advance Report — Export Excel',
        ],
        'general.cash-advance-report.create' => [
            'Cash Advance Report (CAR) — Create On Behalf',
            'Cash Advance Report — Create On Behalf',
        ],
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $slug => [$after, $before]) {
            DB::table('menu')->where('slug', $slug)->update([
                'name'       => $after,
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as $slug => [$after, $before]) {
            DB::table('menu')->where('slug', $slug)->update([
                'name'       => $before,
                'updated_at' => now(),
            ]);
        }
    }
};
