<?php

namespace App\Support;

/**
 * Menggabungkan preferensi tema musiman per-user dengan setting global admin
 * menjadi SATU key tema yang benar-benar dirender — titik tunggal yang
 * dipanggil dashboard.blade.php dan (nanti) auth/login.blade.php, supaya
 * logika "default ikut admin, tapi user boleh menimpa" tidak ditulis ulang
 * di kedua tempat.
 *
 * Aturan:
 *   - preferensi user 'default' → ikut GlobalSeasonalTheme::active().
 *   - preferensi user 'none'    → dimatikan user sendiri, menang atas admin.
 *   - preferensi user tema lain (mis. 'natal') → dipilih user sendiri,
 *     menang atas admin (termasuk saat admin belum/tidak mengaktifkan apa pun).
 *   - nilai tak dikenal apa pun (data lama/rusak) → 'none', tidak pernah
 *     merender tema yang tidak valid.
 */
final class SeasonalThemeResolver
{
    public static function effectiveFor(string $userPreference): string
    {
        $theme = $userPreference === 'default'
            ? GlobalSeasonalTheme::active()
            : $userPreference;

        return SeasonalThemes::isValid($theme) ? $theme : SeasonalThemes::NONE;
    }
}
