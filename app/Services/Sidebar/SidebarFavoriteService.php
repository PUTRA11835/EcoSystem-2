<?php

namespace App\Services\Sidebar;

use App\Models\UserMenuFavorite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * Menu Favorit sidebar (HC-D25).
 *
 * Yang disimpan hanyalah JALUR URL. Kebenaran izin tetap di server: sidebar
 * (yang sudah disaring `$can(...)`) menjadi satu-satunya sumber tautan yang
 * boleh tampil, dan favorit hanya dicocokkan terhadapnya di browser. Layanan ini
 * cukup memastikan bahwa yang tersimpan berbentuk jalur yang wajar dan benar-benar
 * ada sebagai rute GET — bukan URL luar, bukan skema berbahaya, bukan API.
 */
class SidebarFavoriteService
{
    /** Batas jumlah favorit per karyawan (keputusan S2). Ubah di sini, tanpa migrasi. */
    public const MAX = 6;

    /** Jalur yang tak boleh dijadikan favorit (aksi, bukan halaman). */
    private const FORBIDDEN_PREFIXES = ['/api/', '/logout', '/auth/', '/sidebar/'];

    /**
     * Bersihkan daftar jalur — MURNI, tanpa database maupun router.
     *
     * Aturan: harus string; hanya bagian jalur (query & fragmen dibuang); diawali
     * satu `/`; hanya karakter [A-Za-z0-9-_./]; tanpa `..` dan `//`; maks 191
     * karakter; bukan jalur terlarang; unik (urutan pertama menang); maksimal MAX.
     *
     * @param  array<int,mixed>  $paths
     * @return string[]
     */
    public static function normalize(array $paths): array
    {
        $clean = [];

        foreach ($paths as $raw) {
            if (!is_string($raw)) {
                continue;
            }

            $path = trim($raw);
            // Buang query & fragmen; skema/host akan gagal pada pemeriksaan awalan di bawah.
            $path = preg_split('/[?#]/', $path, 2)[0] ?? '';

            if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
                continue;
            }
            // \z (bukan $): `$` menoleransi satu newline di akhir teks.
            if (strlen($path) > 191 || !preg_match('#^[A-Za-z0-9\-_./]+\z#', $path)) {
                continue;
            }
            if (str_contains($path, '..') || str_contains($path, '//')) {
                continue;
            }

            // Buang garis miring penutup agar '/x' dan '/x/' tidak dihitung dua kali.
            $path = $path === '/' ? '/' : rtrim($path, '/');

            foreach (self::FORBIDDEN_PREFIXES as $forbidden) {
                if ($path === rtrim($forbidden, '/') || str_starts_with($path . '/', $forbidden)) {
                    continue 2;
                }
            }

            if (!in_array($path, $clean, true)) {
                $clean[] = $path;
            }
            if (count($clean) >= self::MAX) {
                break;
            }
        }

        return $clean;
    }

    /**
     * Favorit milik satu karyawan, berurutan. Tidak pernah melempar galat: bila
     * tabel belum ada (kode dirilis sebelum migrasi) atau kueri gagal, kembalikan
     * daftar kosong agar sidebar — yang tampil di SETIAP halaman — tidak ikut rusak.
     *
     * @return string[]
     */
    public function forEmployee(int $employeeId): array
    {
        try {
            return UserMenuFavorite::query()
                ->where('employee_id', $employeeId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('menu_path')
                ->all();
        } catch (\Throwable $e) {
            Log::error('Sidebar favorites: gagal membaca', ['employee_id' => $employeeId, 'error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Ganti SELURUH daftar favorit karyawan (semantik "replace": idempoten, tidak ada
     * kondisi balapan antara dua tab). Hanya baris milik karyawan itu yang disentuh.
     *
     * @param  array<int,mixed>  $paths
     * @return string[]  daftar yang benar-benar tersimpan
     */
    public function replace(int $employeeId, array $paths): array
    {
        $paths = array_values(array_filter(
            self::normalize($paths),
            fn (string $p) => $this->routeExists($p)
        ));

        DB::transaction(function () use ($employeeId, $paths) {
            UserMenuFavorite::query()->where('employee_id', $employeeId)->delete();

            foreach ($paths as $order => $path) {
                UserMenuFavorite::create([
                    'employee_id' => $employeeId,
                    'menu_path'   => $path,
                    'sort_order'  => $order,
                ]);
            }
        });

        return $paths;
    }

    /** Apakah jalur ini benar-benar rute GET yang terdaftar (bukan sekadar teks). */
    private function routeExists(string $path): bool
    {
        try {
            Route::getRoutes()->match(Request::create($path, 'GET'));
            return true;
        } catch (\Throwable) {
            // NotFoundHttpException / MethodNotAllowedHttpException / galat router lain.
            return false;
        }
    }
}
