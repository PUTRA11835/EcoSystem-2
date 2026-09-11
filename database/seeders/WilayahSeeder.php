<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Impor data referensi wilayah Indonesia (~91.6k baris) ke tabel `wilayah`.
 *
 * Sumber: database/data/wilayah.sql (Kepmendagri No 300.2.2-2138 Tahun 2025),
 * berisi statement INSERT multi-baris. Idempotent: dilewati bila tabel sudah
 * terisi, sehingga aman dipanggil ulang tanpa menduplikasi/PK-conflict.
 */
class WilayahSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('wilayah')->exists()) {
            $this->command?->info('Tabel wilayah sudah terisi — seed dilewati.');
            return;
        }

        $path = database_path('data/wilayah.sql');
        if (!is_file($path)) {
            $this->command?->warn("File data tidak ditemukan: {$path} — seed wilayah dilewati.");
            return;
        }

        // File berisi 38 statement INSERT (satu per provinsi). Dijalankan terpisah
        // agar tiap query jauh di bawah `max_allowed_packet` — mengirim seluruh
        // file (±2.9MB) sebagai satu query memicu "MySQL server has gone away".
        // Nama tempat tidak mengandung ';', jadi aman dipecah per titik-koma.
        $statements = array_filter(
            array_map('trim', explode(';', file_get_contents($path))),
            fn ($s) => $s !== ''
        );

        DB::transaction(function () use ($statements) {
            foreach ($statements as $statement) {
                DB::unprepared($statement);
            }
        });

        $this->command?->info('Seed wilayah selesai: ' . DB::table('wilayah')->count() . ' baris.');
    }
}
