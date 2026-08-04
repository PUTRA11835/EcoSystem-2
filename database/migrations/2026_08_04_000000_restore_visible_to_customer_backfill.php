<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membatalkan efek backfill migration 2026_08_03_000003.
 *
 * MASALAH: backfill itu memakai "tidak punya baris di `staging_tickets`" sebagai
 * tanda tiket internal EcoSystem. Asumsi itu salah untuk tiket LAMA hasil import
 * dari sistem sebelumnya — tiket tersebut tidak pernah lewat staging, dan
 * pengecualian `channel = 'imported'` tidak menolongnya karena kolom `channel`
 * pada data import terisi 'email'. Akibatnya 181 tiket customer asli (tertua
 * Maret 2025) hilang dari JARVIES, mis. ticket_number 8000003989 milik ENDO.
 *
 * Semua baris dikembalikan ke visible (default kolom = true), yaitu kondisi
 * sebelum backfill jalan. Aman: saat migration ini dibuat tidak ada satu pun
 * baris `visible_to_customer = 0` dengan `ticket_type = 'Internal'`, jadi tidak
 * ada tiket yang benar-benar internal yang ikut terbuka.
 *
 * Sengaja diberi timestamp SEBELUM 2026_08_04_000001_drop_... supaya pada
 * environment baru urutannya: add (backfill) → restore (batalkan) → drop.
 * Setelah kolomnya di-drop, penanda tiket internal murni `ticket_type = 'Internal'`
 * (lihat App\Models\Ticket::TYPE_INTERNAL) yang disaring di repo JARVIES.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('ticket', 'visible_to_customer')) {
            return;
        }

        DB::table('ticket')
            ->where('visible_to_customer', false)
            ->update(['visible_to_customer' => true]);
    }

    /**
     * Tidak dibalik. Menyembunyikan ulang berarti mengembalikan bug-nya, dan
     * daftar baris yang dulu ter-backfill tidak disimpan di mana pun.
     */
    public function down(): void
    {
        //
    }
};
