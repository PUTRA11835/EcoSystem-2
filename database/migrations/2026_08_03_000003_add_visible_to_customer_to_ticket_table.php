<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Visibility tiket ke sisi customer (JARVIES).
 *
 * KONTEKS: EcoSystem & JARVIES berbagi tabel `ticket` yang sama. JARVIES
 * menyaring tiket customer murni lewat `customer_id`, sehingga tiket yang
 * dibuat internal dari EcoSystem (Admin / Helpdesk / External API) ikut
 * terlihat oleh customer dan membingungkan mereka.
 *
 * `channel` TIDAK bisa dipakai sebagai pembeda — jalur create internal sengaja
 * meng-hardcode `channel = 'email'` supaya composer To/CC tetap tersedia di
 * halaman tiket, jadi nilainya sama dengan tiket hasil intake email asli.
 * Karena itu dibutuhkan kolom eksplisit.
 *
 * Default `true` → semua tiket existing tetap terlihat; hanya baris yang
 * di-backfill di bawah (dan tiket internal baru) yang disembunyikan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('ticket', 'visible_to_customer')) {
            Schema::table('ticket', function (Blueprint $table) {
                $table->boolean('visible_to_customer')->default(true)->after('is_hidden');
                $table->index('visible_to_customer');
            });
        }

        // ── Backfill tiket internal EcoSystem yang sudah terlanjur ada ────────
        //
        // Pembeda yang andal: tiket dari JARVIES maupun dari email masuk SELALU
        // lewat `staging_tickets` dulu (approve mengisi staging_tickets.ticket_id).
        // Tiket buatan EcoSystem tidak pernah menyentuh staging.
        //
        // `channel = 'imported'` DIKECUALIKAN — itu data historis hasil import CSV
        // yang sengaja tetap boleh dilihat customer sebagai riwayat.
        // Perbandingan ditulis NULL-safe: `channel` bisa NULL (jalur External API),
        // dan `channel <> 'imported'` akan bernilai NULL (= tidak match) untuk baris itu.
        DB::table('ticket')
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('staging_tickets')
                  ->whereColumn('staging_tickets.ticket_id', 'ticket.ticket_id');
            })
            ->where(function ($q) {
                $q->whereNull('channel')->orWhere('channel', '<>', 'imported');
            })
            ->update(['visible_to_customer' => false]);
    }

    public function down(): void
    {
        if (!Schema::hasColumn('ticket', 'visible_to_customer')) {
            return;
        }

        Schema::table('ticket', function (Blueprint $table) {
            $table->dropIndex(['visible_to_customer']);
            $table->dropColumn('visible_to_customer');
        });
    }
};
