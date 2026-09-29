<?php

namespace App\Support;

use App\Models\AppConfig;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Setting tema musiman yang dikontrol admin, berlaku sebagai DEFAULT untuk
 * seluruh user (dashboard) dan halaman login (pre-auth, lewat AppConfig
 * karena belum ada session). Bukan dipaksakan — tiap user tetap bisa
 * menimpa untuk dirinya sendiri lewat preferensi pribadinya (lihat
 * SeasonalThemeResolver, yang menggabungkan nilai ini dengan preferensi
 * per-user).
 *
 * Ditulis oleh Control Center → Seasonal Theme (SeasonalThemeSettingsController,
 * belum dibuat — kelas ini scaffolding yang aman dipakai lebih dulu: bila
 * belum ada admin yang pernah menyimpan apa pun, active() jatuh ke 'none'
 * sehingga tidak ada perubahan visual yang muncul tiba-tiba untuk siapa pun).
 *
 * Pola sama seperti App\Support\AiModelSettings: baca lewat AppConfig::getJson,
 * selalu disanitasi ulang saat dibaca (bukan hanya saat ditulis) supaya baris
 * app_configs yang diedit manual atau menyebut tema yang sudah dihapus tidak
 * bisa menjatuhkan dashboard/login siapa pun.
 */
final class GlobalSeasonalTheme
{
    public const KEY = 'seasonal_theme.global';

    /** File musik latar harus ada di public/sounds/ — validasi lihat sanitizeSound(). */
    private const SOUND_DIR = 'sounds';

    private const DEFAULTS = [
        'theme' => SeasonalThemes::NONE,
        // null = tidak ada musik latar. BUKAN preferensi per-user — ini murni
        // wewenang admin (lihat App\Http\Controllers\SeasonalThemeSettingsController),
        // dashboard.blade.php cuma boleh mute/unmute LOKAL demi aksesibilitas
        // (WCAG 1.4.2), tidak pernah menyimpan pilihan lagu.
        'sound' => null,
        // 0-100 (persen), disimpan bulat demi UI slider admin. BUKAN preferensi
        // per-user juga — berlaku sama untuk semua user, sama seperti 'sound'.
        // 50 = default kalau admin belum pernah mengatur, supaya begitu admin
        // nyalakan musik untuk pertama kali tidak langsung full volume.
        'volume' => 50,
    ];

    /** Tema aktif yang dipilih admin, sudah divalidasi terhadap SeasonalThemes. */
    public static function active(): string
    {
        return self::all()['theme'];
    }

    /**
     * URL musik latar yang dipasang admin, atau null kalau tidak ada.
     * Dipakai dashboard.blade.php (semua halaman setelah login) — BUKAN
     * halaman login (itu pakai #seasonalJingle terpisah, klip loading screen
     * singkat, konsepnya beda dari musik latar berkelanjutan ini).
     */
    public static function soundUrl(): ?string
    {
        $sound = self::all()['sound'];

        return $sound ? '/' . self::SOUND_DIR . '/' . $sound : null;
    }

    /** Volume musik latar sebagai pecahan 0.0-1.0, siap dipakai langsung `audio.volume = ...` di JS. */
    public static function soundVolume(): float
    {
        return self::all()['volume'] / 100;
    }

    /** @return array{theme: string, sound: ?string, volume: int} */
    public static function all(): array
    {
        $stored = [];

        try {
            $stored = AppConfig::getJson(self::KEY, []);
        } catch (Throwable $e) {
            // Tabel app_configs belum ada / DB bermasalah — jangan jatuhkan
            // dashboard/login siapa pun, cukup jatuh ke default 'none'.
            Log::warning('Global seasonal theme unreadable, falling back to default', [
                'error' => $e->getMessage(),
            ]);
        }

        return self::sanitize(is_array($stored) ? $stored : []);
    }

    /**
     * @param array<string, mixed> $input
     *
     * PENTING: $input harus selalu membawa KETIGA field ('theme', 'sound',
     * 'volume') bersamaan, bukan cuma yang berubah — sanitize() menjatuhkan
     * field yang hilang ke default (null untuk sound, 50 untuk volume), jadi
     * save(['theme'=>'natal']) SAJA akan diam-diam MENGHAPUS musik latar/reset
     * volume yang sudah diset sebelumnya. Form admin
     * (admin/seasonal-theme-settings.blade.php) selalu submit ketiganya.
     */
    public static function save(array $input): void
    {
        $clean = self::sanitize($input);

        AppConfig::setJson(self::KEY, $clean, 'Tema musiman + musik latar default untuk semua user + halaman login (Control Center → Seasonal Theme)');
    }

    /** @param array<string, mixed> $input */
    private static function sanitize(array $input): array
    {
        $theme = (string) ($input['theme'] ?? self::DEFAULTS['theme']);

        return [
            'theme' => SeasonalThemes::isValid($theme) ? $theme : self::DEFAULTS['theme'],
            'sound' => self::sanitizeSound($input['sound'] ?? null),
            'volume' => self::sanitizeVolume($input['volume'] ?? null),
        ];
    }

    /** Dijepit ke 0-100, jatuh ke default (50) kalau bukan angka valid. */
    private static function sanitizeVolume(mixed $volume): int
    {
        if (!is_numeric($volume)) {
            return self::DEFAULTS['volume'];
        }

        return (int) max(0, min(100, round((float) $volume)));
    }

    /**
     * Whitelist KETAT: cuma nama file polos (basename, bukan path) berekstensi
     * audio yang BENAR-BENAR ADA di public/sounds/ — admin memilih dari daftar
     * yang sudah ada (lihat SeasonalThemeSettingsController::index()), bukan
     * mengetik bebas, tapi tetap disanitasi ulang di sini (bukan cuma saat
     * simpan) karena baris app_configs bisa diedit manual/menyebut file yang
     * sudah dihapus dari disk.
     */
    private static function sanitizeSound(mixed $sound): ?string
    {
        if (!is_string($sound) || $sound === '') {
            return null;
        }

        $filename = basename($sound);

        if (!preg_match('/^[\w.\-]+\.(mp3|wav|ogg|aac)$/i', $filename)) {
            return null;
        }

        return file_exists(public_path(self::SOUND_DIR . '/' . $filename)) ? $filename : null;
    }
}
