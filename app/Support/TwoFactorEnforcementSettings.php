<?php

namespace App\Support;

use App\Models\AppConfig;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Override admin-configurable untuk role mana yang WAJIB mengaktifkan 2FA —
 * dibaca App\Http\Middleware\EnforceTwoFactorForAdmins di setiap request.
 * Ditulis lewat Control Center → Two-Factor Enforcement
 * (TwoFactorEnforcementSettingsController), supaya admin bisa
 * menyalakan/mematikan kewajiban 2FA per role tanpa perlu deploy kode baru.
 *
 * Pola sama persis dengan App\Support\GlobalSeasonalTheme/AiModelSettings:
 * baca lewat AppConfig::getJson, disanitasi ulang tiap baca (bukan cuma saat
 * simpan) supaya baris app_configs yang diedit manual tidak bisa menjatuhkan
 * middleware ini, dan try/catch supaya masalah DB tidak menjatuhkan
 * request sama sekali.
 *
 * config('security_center.two_factor.enforce_for_role_ids') tetap ada sebagai
 * SEED DEFAULT — dipakai persis sekali, selama admin belum pernah menyimpan
 * apapun lewat halaman Control Center ini (row app_configs belum ada).
 */
final class TwoFactorEnforcementSettings
{
    public const KEY = 'security.two_factor_enforcement';

    /** @return int[] role_id yang wajib 2FA saat ini. Kosong = tidak ada yang wajib. */
    public static function roleIds(): array
    {
        try {
            $stored = AppConfig::getJson(self::KEY, null);
        } catch (Throwable $e) {
            Log::warning('Two-factor enforcement setting unreadable, falling back to config seed default', [
                'error' => $e->getMessage(),
            ]);
            return self::seedDefault();
        }

        // null = admin belum pernah menyimpan apapun lewat halaman ini —
        // jatuh ke nilai seed dari config file (perilaku sebelum fitur ini ada).
        if ($stored === null) {
            return self::seedDefault();
        }

        return self::sanitize($stored);
    }

    public static function isEnabled(): bool
    {
        return !empty(self::roleIds());
    }

    /** @param array<int, mixed> $roleIds */
    public static function save(array $roleIds): void
    {
        AppConfig::setJson(
            self::KEY,
            self::sanitize($roleIds),
            'Role yang wajib mengaktifkan 2FA (Control Center → Two-Factor Enforcement)'
        );
    }

    private static function seedDefault(): array
    {
        return self::sanitize(config('security_center.two_factor.enforce_for_role_ids', []));
    }

    private static function sanitize(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }

        return array_values(array_unique(array_map(
            fn ($v) => (int) $v,
            array_filter($input, fn ($v) => is_numeric($v))
        )));
    }
}
