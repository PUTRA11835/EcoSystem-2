<?php

namespace App\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP-based 2FA. Stateless/functional - callers do their own
 * DB::table('auth_users') reads/writes, matching the existing raw-query
 * convention in AuthController/LiteAuthController (unlike the Eloquent-based
 * SettingsController). See the plan at time of writing for the full design
 * rationale (remember-me interaction, TOTP replay protection, recovery-code
 * locking) - this class only implements the primitives.
 */
class TwoFactorAuthService
{
    private static function engine(): Google2FA
    {
        return new Google2FA();
    }

    public static function generateSecretKey(): string
    {
        return self::engine()->generateSecretKey();
    }

    /**
     * The standard otpauth://totp/ enrollment URI (RFC - Google Authenticator
     * Key URI Format). pragmarx/google2fa's core package doesn't build this
     * itself (that's the separate google2fa-qrcode package, not installed
     * here), so it's constructed directly - it's a fixed, well-known format.
     */
    public static function getOtpAuthUri(string $accountLabel, string $secret): string
    {
        $issuer = 'EcoSystem';
        $label = rawurlencode("{$issuer}:{$accountLabel}");

        return "otpauth://totp/{$label}?secret={$secret}&issuer=" . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
    }

    /** Inline SVG QR code for the otpauth:// enrollment URI. */
    public static function getQrCodeSvg(string $accountLabel, string $secret): string
    {
        $uri = self::getOtpAuthUri($accountLabel, $secret);

        $renderer = new ImageRenderer(
            new RendererStyle(200, 1),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString($uri);
    }

    /**
     * Verifies a 6-digit TOTP code with replay protection: a timestep at or
     * before $lastUsedTimestamp is rejected even if the code is otherwise
     * correct. Caller must persist the returned timestamp into
     * auth_users.two_factor_last_used_at on success.
     *
     * @return array{valid: bool, timestamp: ?int}
     */
    public static function verifyCode(string $secret, string $code, ?int $lastUsedTimestamp): array
    {
        // pragmarx/google2fa's findValidOTP() only returns the actual matched
        // timestep when $oldTimestamp is non-null - pass null through and it
        // returns boolean `true` on success instead, giving nothing usable to
        // persist for the next replay check. 0 is a safe "no prior value"
        // sentinel: a real timestep (current time / 30s) is always far larger
        // than 0, so it can never itself cause a false "replay" rejection.
        $oldTimestamp = $lastUsedTimestamp ?? 0;
        $result = self::engine()->verifyKeyNewer($secret, $code, $oldTimestamp);

        if ($result === false) {
            return ['valid' => false, 'timestamp' => null];
        }

        return ['valid' => true, 'timestamp' => (int) $result];
    }

    /** @return string[] plain recovery codes, e.g. "A1B2C3D4-E5F6G7H8" */
    public static function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = strtoupper(Str::random(4)) . '-' . strtoupper(Str::random(4));
        }
        return $codes;
    }

    /** @param string[] $codes @return string[] bcrypt hashes */
    public static function hashRecoveryCodes(array $codes): array
    {
        return array_map(fn (string $code) => bcrypt($code), $codes);
    }

    /**
     * Pure function - does no I/O. Returns the remaining hash array with the
     * matched entry removed, or null if $submitted matches none of them.
     * Caller is responsible for wrapping the read-check-write in a
     * transaction with row locking (recovery codes are a read-modify-write
     * on a JSON array - a flat overwrite is not safe under concurrent use).
     *
     * @param string[] $hashedCodes
     * @return string[]|null
     */
    public static function findAndConsumeRecoveryCode(array $hashedCodes, string $submitted): ?array
    {
        foreach ($hashedCodes as $index => $hash) {
            if (password_verify($submitted, $hash)) {
                unset($hashedCodes[$index]);
                return array_values($hashedCodes);
            }
        }
        return null;
    }

    /**
     * Short-lived, tamper-evident challenge issued after password
     * verification for a 2FA-enabled account, before any session/cookie is
     * created. Laravel's encrypt() is authenticated AES-256-CBC (HMAC
     * verified before decrypt), so this is safe to hand to the client.
     */
    public static function issueChallengeToken(int $authUserId, bool $remember): string
    {
        $ttlMinutes = (int) config('security_center.two_factor.challenge_ttl_minutes', 5);

        return encrypt([
            'auth_user_id' => $authUserId,
            'remember'     => $remember,
            'jti'          => Str::random(16),
            'expires_at'   => now()->addMinutes($ttlMinutes)->timestamp,
        ]);
    }

    /**
     * @return array{auth_user_id: int, remember: bool, jti: string}|null
     *         null on any failure (malformed, tampered, or expired) - the
     *         caller should show one generic error regardless of which.
     */
    public static function resolveChallengeToken(string $token): ?array
    {
        try {
            $payload = decrypt($token);
        } catch (\Throwable $e) {
            return null;
        }

        if (!is_array($payload) || empty($payload['auth_user_id']) || empty($payload['expires_at'])) {
            return null;
        }

        if ($payload['expires_at'] < now()->timestamp) {
            return null;
        }

        return [
            'auth_user_id' => (int) $payload['auth_user_id'],
            'remember'     => (bool) ($payload['remember'] ?? false),
            'jti'          => (string) ($payload['jti'] ?? ''),
        ];
    }

    public static function isEnabled(object $authUser): bool
    {
        return !empty($authUser->two_factor_secret) && !empty($authUser->two_factor_confirmed_at);
    }

    public static function encryptSecret(string $secret): string
    {
        return Crypt::encryptString($secret);
    }

    public static function decryptSecret(string $encrypted): ?string
    {
        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable $e) {
            return null;
        }
    }

    // ── Verify-attempt rate limiting (mirrors the Cache-counter idiom used
    //    throughout LoginSecurityService/DetectAccessPatterns) ───────────────

    private static function attemptCacheKey(int $authUserId): string
    {
        return "2fa_failed_attempts_{$authUserId}";
    }

    /** Keyed by auth_user_id (not the challenge token) - must persist across
     *  re-logins, since an attacker who already has the password can mint a
     *  fresh token cheaply. */
    public static function recordFailedChallenge(int $authUserId): int
    {
        $key = self::attemptCacheKey($authUserId);
        $minutes = (int) config('security_center.two_factor.lockout_minutes', 30);

        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, now()->addMinutes($minutes));

        return $count;
    }

    public static function isChallengeLocked(int $authUserId): bool
    {
        $count = (int) Cache::get(self::attemptCacheKey($authUserId), 0);
        return $count >= (int) config('security_center.two_factor.max_verify_attempts', 5);
    }

    public static function clearFailedChallenge(int $authUserId): void
    {
        Cache::forget(self::attemptCacheKey($authUserId));
    }
}
