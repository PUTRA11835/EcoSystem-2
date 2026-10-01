<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Helper "URL tujuan" (intended URL).
 *
 * Dipakai saat user membuka deep link (mis. tombol "Buka di EcoSystem" pada kartu
 * Teams → /ticket/123) dalam kondisi belum login. Middleware CheckAuthToken
 * menyimpan tujuan itu, halaman login membawanya, lalu setelah login berhasil
 * browser diarahkan ke sana — bukan ke /dashboard.
 *
 * SEMUA nilai yang datang dari luar (query string ?redirect=) WAJIB lewat
 * sanitize() supaya tidak jadi lubang open-redirect.
 */
class IntendedUrl
{
    /** Key session tempat URL tujuan disimpan (konvensi Laravel). */
    public const SESSION_KEY = 'url.intended';

    /** Nama query string pada URL login. */
    public const QUERY_KEY = 'redirect';

    /** Tujuan default bila tidak ada / tidak valid. */
    public const FALLBACK = '/dashboard';

    /**
     * Path yang tidak masuk akal dijadikan tujuan setelah login
     * (halaman auth sendiri, endpoint API, aset).
     */
    private const BLOCKED_PREFIXES = [
        'api/',
        'auth/login',
        'logout',
        'verify-email',
        'password',
        'reset-password',
        'set-password',
        'storage/',
        'livewire/',
    ];

    /**
     * Normalisasi URL tujuan menjadi path relatif yang aman ("/ticket/123?tab=x"),
     * atau null bila tidak layak dipakai.
     *
     * Aturan: hanya path pada host aplikasi sendiri. URL absolut ke host lain,
     * protocol-relative ("//evil.com"), dan skema aneh ("javascript:") ditolak.
     */
    public static function sanitize(?string $url, ?Request $request = null): ?string
    {
        $url = trim((string) $url);

        if ($url === '' || strlen($url) > 2048) {
            return null;
        }

        // Tolak karakter kontrol / newline (header & JS injection).
        if (preg_match('/[\x00-\x1F\x7F]/', $url)) {
            return null;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return null;
        }

        // URL absolut: hanya http/https dan host-nya harus host aplikasi.
        if (isset($parts['host'])) {
            $scheme = strtolower($parts['scheme'] ?? '');
            if ($scheme !== '' && !in_array($scheme, ['http', 'https'], true)) {
                return null;
            }
            if (!self::isAppHost($parts['host'], $request)) {
                return null;
            }
        } elseif (isset($parts['scheme'])) {
            // "javascript:...", "data:...", dst.
            return null;
        } elseif (str_starts_with($url, '//')) {
            // protocol-relative → dianggap host lain.
            return null;
        } elseif (!str_starts_with($url, '/')) {
            // path relatif tanpa slash awal, ambigu — tolak.
            return null;
        }

        $path = $parts['path'] ?? '/';
        if ($path === '' || $path[0] !== '/') {
            $path = '/' . $path;
        }

        $clean = ltrim($path, '/');
        foreach (self::BLOCKED_PREFIXES as $prefix) {
            if ($clean === rtrim($prefix, '/') || str_starts_with($clean, $prefix)) {
                return null;
            }
        }

        $target = $path;
        if (!empty($parts['query'])) {
            $target .= '?' . $parts['query'];
        }
        if (!empty($parts['fragment'])) {
            $target .= '#' . $parts['fragment'];
        }

        return $target;
    }

    /**
     * Ambil tujuan dari request login (query/body) lalu dari session; fallback dashboard.
     * Nilai di session sekalian dibuang supaya tidak "nyangkut" ke login berikutnya.
     */
    public static function pull(Request $request): string
    {
        $fromRequest = self::sanitize($request->input(self::QUERY_KEY), $request);
        $fromSession = self::sanitize(session()->pull(self::SESSION_KEY), $request);

        return $fromRequest ?? $fromSession ?? self::FALLBACK;
    }

    /** Simpan URL tujuan ke session (dipanggil middleware sebelum lempar ke login). */
    public static function remember(string $url, ?Request $request = null): ?string
    {
        $safe = self::sanitize($url, $request);

        if ($safe !== null) {
            session()->put(self::SESSION_KEY, $safe);
        }

        return $safe;
    }

    private static function isAppHost(string $host, ?Request $request): bool
    {
        $host = strtolower($host);

        $hosts = [];

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if ($appHost) {
            $hosts[] = strtolower($appHost);
        }

        if ($request) {
            $hosts[] = strtolower($request->getHost());
        }

        return in_array($host, array_filter($hosts), true);
    }
}
