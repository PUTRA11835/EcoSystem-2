<?php

namespace App\Support;

/**
 * Katalog tema musiman (Natal, lalu Tahun Baru/Lebaran menyusul) — sumber
 * kebenaran TUNGGAL untuk visual apa saja yang tersedia. Ini murni katalog
 * tema yang bisa dirender (none/natal/...); bukan tempat konsep "ikut admin"
 * ('default') — itu ditangani SettingsController::ALLOWED_VALUES dan
 * App\Support\SeasonalThemeResolver, supaya kelas ini tetap hanya berisi
 * tema yang benar-benar bisa dirender oleh partial.
 *
 * Dipakai oleh dua jalur konfigurasi independen yang keduanya di-resolve ke
 * satu key di katalog ini sebelum dirender:
 *
 *   - Per-user: SettingsController (auth_users.preferences.seasonal_theme).
 *   - Global admin: App\Support\GlobalSeasonalTheme (app_configs), berlaku
 *     sebagai default untuk semua user (dashboard) + halaman login, diatur
 *     lewat Control Center. User tetap bisa menimpa pilihan admin ini di
 *     preferensi mereka sendiri (lihat SeasonalThemeResolver).
 *
 * Menambah tema baru (Tahun Baru, Lebaran) berarti menambah satu entri di
 * CATALOG dan satu literal di SettingsController::ALLOWED_VALUES['seasonal_theme']
 * — bukan menulis ulang controller/route/JS. Field `decoration` adalah tag
 * yang dibaca partial resources/views/partials/seasonal-theme.blade.php lewat
 * @switch untuk memilih efek animasi jatuhnya (mis. 'snow' untuk Natal).
 * Field `accents` adalah emoji dekorasi statis (bukan animasi) yang
 * ditampilkan berbarengan — dipakai Unicode emoji, bukan nama ikon Font
 * Awesome, karena Font Awesome Free 6.4 tidak punya ikon Santa/pohon-natal
 * yang bisa dipastikan tersedia tanpa cek manual; emoji tidak butuh aset
 * atau library tambahan dan tidak pernah render "kotak kosong" bila salah nama.
 */
final class SeasonalThemes
{
    public const NONE = 'none';
    public const NATAL = 'natal';

    private const CATALOG = [
        self::NONE => [
            'label' => 'Off',
            'icon' => 'fa-ban',
            'accent' => '#9ca3af',
            'decoration' => null,
            'accents' => [],
        ],
        self::NATAL => [
            'label' => 'Natal',
            'icon' => 'fa-tree',
            'accent' => '#b91c1c',
            'decoration' => 'snow',
            'accents' => ['🎄', '🎅', '🎁', '⛄', '🔔'],
        ],
    ];

    /** Semua key tema yang dikenal, termasuk 'none'. */
    public static function keys(): array
    {
        return array_keys(self::CATALOG);
    }

    /** Katalog lengkap, dipakai untuk render picker (Settings & Control Center). */
    public static function catalog(): array
    {
        return self::CATALOG;
    }

    /** Metadata satu tema, atau null bila key tidak dikenal. */
    public static function get(string $key): ?array
    {
        return self::CATALOG[$key] ?? null;
    }

    public static function isValid(string $key): bool
    {
        return array_key_exists($key, self::CATALOG);
    }
}
