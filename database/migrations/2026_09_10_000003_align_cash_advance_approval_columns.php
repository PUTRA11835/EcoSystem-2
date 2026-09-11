<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menyelaraskan dua kolom tabel riwayat persetujuan CA & CAR dengan tiga
 * sub-modul yang sudah berjalan — Keputusan D147.
 *
 * ── APA YANG BERBEDA, DAN KENAPA ITU MASALAH ───────────────────────────────
 *
 * Ditemukan saat menulis model di langkah A2, dengan membandingkan langsung ke
 * basis data:
 *
 *   tabel                              status (default)   kolom catatan
 *   ────────────────────────────────   ────────────────   ─────────────
 *   overtime_request_approvals         waiting            notes  (text)
 *   reimbursement_request_approvals    waiting            notes  (text)
 *   purchase_request_approvals         waiting            notes  (text)
 *   cash_advance_approvals             pending  ← beda    note   ← beda
 *   cash_advance_report_approvals      pending  ← beda    note   ← beda
 *
 * Nilai `pending` datang dari badge "Pending" pada tangkapan layar aplikasi
 * acuan — dan di situ letak kekeliruannya: **label yang dibaca pengguna bukan
 * nilai yang disimpan basis data.** Layar tetap boleh menulis "Pending"; yang
 * disimpan sebaiknya sama dengan tiga saudaranya.
 *
 * 🔴 Kenapa ini bukan sekadar kerapian. Empat sub-modul ini memakai mesin
 * persetujuan yang bentuknya sengaja dibuat kembar. Satu nilai enum yang berbeda
 * berarti setiap orang yang membaca dua modul sekaligus harus mengingat
 * pengecualian, dan setiap kueri yang kelak menyapu persetujuan lintas modul
 * (mis. kartu "menunggu persetujuan saya" di dasbor) akan diam-diam kehilangan
 * baris CA. Kehilangan baris tidak pernah memunculkan galat.
 *
 * ── KENAPA SEKARANG, DAN KENAPA MASIH MURAH ────────────────────────────────
 * Kedua tabel masih NOL BARIS. Mengubah nilai bawaan dan mengganti nama kolom
 * hari ini tidak menyentuh satu pun data. Sebulan lagi, dengan dokumen keuangan
 * berjalan di dalamnya, perubahan yang sama menuntut skrip konversi data dan
 * jendela pemeliharaan.
 *
 * Nomor migrasi 000003, bukan suntingan atas 2026_09_07_000005 & _000008 —
 * keduanya sudah dikomit dan terdorong ke remote (aturan D145).
 */
return new class extends Migration
{
    private const TABLES = [
        'cash_advance_approvals',
        'cash_advance_report_approvals',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->renameColumn('note', 'notes');
            });

            Schema::table($tableName, function (Blueprint $table) {
                // text, bukan varchar(500) — menyamai tiga tabel sejenis.
                $table->text('notes')->nullable()->change();

                // waiting, bukan pending. Label "Pending" tetap boleh dipakai di
                // layar; yang diselaraskan adalah nilai yang DISIMPAN.
                $table->string('status', 20)->default('waiting')->change();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('status', 20)->default('pending')->change();
            });

            Schema::table($tableName, function (Blueprint $table) {
                $table->renameColumn('notes', 'note');
            });

            Schema::table($tableName, function (Blueprint $table) {
                $table->string('note', 500)->nullable()->change();
            });
        }
    }
};
