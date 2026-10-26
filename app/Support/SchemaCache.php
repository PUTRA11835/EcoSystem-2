<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Pengganti Schema::hasTable() untuk jalur panas (halaman Master Employee, My Profile, dsb.).
 *
 * Schema::hasTable() menjalankan kueri ke information_schema pada SETIAP panggilan; di hosting bersama kueri itu termasuk yang paling lambat.
 * Tabel yang sudah ada tidak akan hilang, jadi jawaban "ada" disimpan (per proses + cache aplikasi). Jawaban "belum ada" TIDAK disimpan,
 * sehingga tabel yang baru dibuat migrasi langsung terdeteksi (perilaku sama dengan Schema::hasTable).
 */
final class SchemaCache
{
    /** @var array<string, bool> */
    private static array $known = [];

    public static function hasTable(string $table): bool
    {
        if (isset(self::$known[$table])) {
            return true;
        }

        $key = 'schema.has_table.' . $table;
        try {
            if (Cache::get($key) === true) {
                return self::$known[$table] = true;
            }
        } catch (\Throwable $e) {
            // cache tak tersedia → langsung periksa skema
        }

        $has = Schema::hasTable($table);
        if ($has) {
            self::$known[$table] = true;
            try {
                Cache::forever($key, true);
            } catch (\Throwable $e) {
                // gagal menyimpan bukan masalah; pemeriksaan berikutnya jatuh ke skema lagi
            }
        }

        return $has;
    }
}
