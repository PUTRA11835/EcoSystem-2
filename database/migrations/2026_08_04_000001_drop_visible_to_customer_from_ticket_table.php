<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Membatalkan mekanisme `ticket.visible_to_customer` (migration 2026_08_03_000003).
 *
 * KEPUTUSAN BARU: pembeda tiket yang disembunyikan dari customer di JARVIES
 * bukan lagi "dibuat dari EcoSystem / tidak lewat staging" — cakupan itu terlalu
 * luas dan ikut menyembunyikan tiket customer yang kebetulan dibuat dari
 * EcoSystem. Sekarang pembedanya HANYA `ticket_type = 'Internal'`
 * (lihat App\Models\Ticket::TYPE_INTERNAL), yang disaring langsung di JARVIES.
 *
 * Efek samping yang disengaja: seluruh tiket yang kemarin ter-backfill jadi
 * tersembunyi otomatis kembali terlihat oleh customer, karena kolom penandanya
 * hilang. Penandaan tiket internal dilakukan manual lewat ticket type.
 *
 * URUTAN DEPLOY WAJIB: deploy repo JARVIES dulu (guard-nya sudah tidak memakai
 * kolom ini), BARU jalankan migration ini. Kalau terbalik, query JARVIES yang
 * masih menyebut `visible_to_customer` akan error kolom tidak ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('ticket', 'visible_to_customer')) {
            return;
        }

        // Dijalankan sebagai SATU statement ALTER mentah, bukan Schema::table(),
        // dengan tiga pengaman untuk traffic production:
        //
        // 1. Index-nya TIDAK di-drop terpisah. MySQL/MariaDB otomatis membuang
        //    index single-column saat kolomnya dibuang, sedangkan Laravel akan
        //    mengirim dropIndex + dropColumn sebagai dua ALTER = dua kali ambil
        //    metadata lock. Satu statement = satu jendela lock.
        // 2. LOCK=NONE → DML (insert/update tiket) tetap jalan selama rebuild.
        //    Kalau versi server tidak sanggup tanpa memblokir, statement-nya
        //    GAGAL dengan error, bukan diam-diam mengunci tabel. Gagal jauh lebih
        //    baik daripada tiket macet; tinggal jadwalkan maintenance window.
        // 3. lock_wait_timeout pendek → kalau ada transaksi lain yang masih
        //    memegang tabel `ticket`, ALTER menyerah setelah 10 detik. Tanpa ini
        //    ALTER menunggu berjam-jam dan SEMUA query `ticket` ikut antre di
        //    belakangnya (metadata lock pileup) — mode kegagalan yang paling
        //    berbahaya di sini, jauh lebih berisiko daripada rebuild-nya sendiri.
        $previous = DB::selectOne('SELECT @@session.lock_wait_timeout AS v')->v;

        DB::statement('SET SESSION lock_wait_timeout = 10');

        try {
            DB::statement(
                'ALTER TABLE `ticket` DROP COLUMN `visible_to_customer`, ALGORITHM=INPLACE, LOCK=NONE'
            );
        } finally {
            DB::statement('SET SESSION lock_wait_timeout = ' . (int) $previous);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ticket', 'visible_to_customer')) {
            return;
        }

        // Dikembalikan dengan default `true` untuk SEMUA baris — backfill lama
        // sengaja tidak diulang.
        Schema::table('ticket', function (Blueprint $table) {
            $table->boolean('visible_to_customer')->default(true)->after('is_hidden');
            $table->index('visible_to_customer');
        });
    }
};
