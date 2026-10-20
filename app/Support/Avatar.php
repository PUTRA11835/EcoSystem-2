<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Foto profil di pojok kanan atas (topbar): versi foto milik SENDIRI untuk URL `/my-profile/photo?v=…`.
 * Versi = hash nama berkas foto (nama berkas acak, berganti tiap unggah) sehingga peramban boleh menyimpan gambar lama
 * dan otomatis memuat yang baru setelah foto diganti. Null = belum punya foto → topbar tetap memakai inisial.
 * Satu kueri ringan per permintaan (di-memo selama proses berjalan).
 */
class Avatar
{
    /** @var array<int,?string> */
    private static array $memo = [];

    public static function version(int $employeeId): ?string
    {
        if ($employeeId <= 0) {
            return null;
        }
        if (!array_key_exists($employeeId, self::$memo)) {
            try {
                $path = DB::table('employee_hr_profile')->where('employee_id', $employeeId)->value('photo_path');
            } catch (\Throwable) {
                $path = null;   // tabel belum ada / galat → inisial saja; tampilan tidak boleh rusak
            }
            self::$memo[$employeeId] = $path ? substr(md5((string) $path), 0, 12) : null;
        }

        return self::$memo[$employeeId];
    }

    /** Dipakai uji agar memo tidak menahan nilai antar-permintaan dalam satu proses. */
    public static function forget(): void
    {
        self::$memo = [];
    }
}
