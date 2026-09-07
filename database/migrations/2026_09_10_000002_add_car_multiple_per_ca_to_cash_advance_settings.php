<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sakelar "berapa CAR yang boleh menempel pada satu CA" — jawaban C8,
 * Keputusan D143.
 *
 * Pemilik sistem bertanya: satu CAR per CA dulu, atau langsung diberi
 * konfigurasi? Jawabannya **konfigurasi**, karena di kasus ini melonggarkannya
 * memang nyaris tanpa biaya — dan itu bukan selalu benar untuk setiap sakelar.
 *
 * Yang membuatnya murah di sini ada tiga:
 *
 *   1. Relasinya SUDAH satu-ke-banyak sejak `cash_advance_reports` dibuat
 *      (`cash_advance_id` sebagai kunci asing biasa). Tidak ada migrasi yang
 *      perlu dijalankan untuk melonggarkannya.
 *   2. Batasnya ditegakkan SERVICE, bukan indeks unik. Indeks unik pada tabel
 *      yang sudah berisi jauh lebih mahal dibuang daripada satu baris
 *      pemeriksaan yang dihapus.
 *   3. Kolom `car_count` pada `cash_advances` sudah ada sejak migrasi 000004.
 *
 * 🔴 SATU SYARAT yang harus dipegang sejak A2, kalau tidak sakelar ini jadi
 * jebakan alih-alih kemudahan: penyelesaian CA WAJIB dihitung dari JUMLAH
 * seluruh CAR yang berstatus `approved`, bukan dari satu baris CAR terakhir.
 *
 *   dengan sakelar MATI  -> jumlah dari satu baris, hasilnya identik
 *   dengan sakelar HIDUP -> langsung benar, nol perubahan kode
 *
 * Menulisnya sebagai "ambil CAR-nya" lebih dulu berarti menyalakan sakelar ini
 * kelak diam-diam menghasilkan sisa uang yang SALAH. Salahnya tidak akan muncul
 * sebagai galat — hanya sebagai angka yang keliru di dokumen keuangan, dan
 * itulah jenis kesalahan yang paling lama tidak ketahuan.
 *
 * Bawaannya FALSE = satu CAR per CA, bentuk yang dipakai aplikasi acuan dan
 * yang paling mudah dijelaskan ke tim. Nyalakan hanya setelah tim memutuskan
 * bahwa pertanggungjawaban bercicil memang dibutuhkan.
 *
 * ── KENAPA MIGRASI TERPISAH ────────────────────────────────────────────────
 * `2026_09_07_000002` sudah dikomit dan terdorong ke remote. Migrasi yang sudah
 * dikomit hanya boleh diubah oleh migrasi baru — lihat docblock
 * `2026_09_10_000001_recompose_cash_advance_menus.php`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_advance_settings', function (Blueprint $table) {
            $table->boolean('car_multiple_per_ca')
                  ->default(false)
                  ->after('car_require_receipt_url');
        });
    }

    public function down(): void
    {
        Schema::table('cash_advance_settings', function (Blueprint $table) {
            $table->dropColumn('car_multiple_per_ca');
        });
    }
};
