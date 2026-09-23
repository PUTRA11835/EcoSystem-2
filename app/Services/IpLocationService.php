<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Resolves a rough city/country location for an IP address via ip-api.com
 * (free, no key required). Results are cached per IP for 24h since the same
 * address shows up repeatedly across login attempts, active sessions, and
 * security events, and this is otherwise a live external HTTP call on every
 * lookup. Originally inlined in AuthController::resolveIpLocation() for the
 * login flow only; extracted here so Active Sessions and Security Center can
 * show a location instead of a bare IP address too.
 */
class IpLocationService
{
    private const CACHE_TTL_HOURS = 24;

    /** @return array{city: ?string, country: ?string} */
    public static function resolve(?string $ip): array
    {
        if (!$ip || self::isPrivateOrReserved($ip)) {
            // 'Local' on both keys matches AuthController::recordActivity()'s
            // pre-existing convention (also relied on by
            // DetectSecurityAnomalies's "new country" check, which skips
            // this exact sentinel) - kept as-is rather than introducing a
            // second value for the same condition.
            return ['city' => 'Local', 'country' => 'Local'];
        }

        return Cache::remember("ip_location_{$ip}", now()->addHours(self::CACHE_TTL_HOURS), function () use ($ip) {
            try {
                $res = Http::timeout(1)->get("http://ip-api.com/json/{$ip}?fields=status,city,country");
                if ($res->successful()) {
                    $data = $res->json();
                    if (($data['status'] ?? '') === 'success') {
                        return ['city' => $data['city'] ?? null, 'country' => $data['country'] ?? null];
                    }
                }
            } catch (\Throwable) {
                // Geolocation failure is non-fatal - the caller falls back to showing the IP alone.
            }

            return ['city' => null, 'country' => null];
        });
    }

    /** "City, Country", "Local network", or "Unknown location" when nothing resolved. */
    public static function format(?string $ip): string
    {
        if (!$ip) {
            return 'Unknown location';
        }

        $location = self::resolve($ip);

        if ($location['city'] === 'Local') {
            return 'Local network';
        }

        $parts = array_filter([$location['city'], $location['country']]);

        return $parts ? implode(', ', $parts) : 'Unknown location';
    }

    /**
     * Resolve many IPs in one pass. Per-IP caching still applies underneath,
     * so a table with many rows only makes fresh HTTP calls for genuinely
     * new, uncached IPs - typically just one or two per page, since the same
     * employee or attacker IP tends to repeat across rows.
     *
     * @param string[] $ips
     * @return array<string, string> ip => formatted location
     */
    public static function formatMany(array $ips): array
    {
        $result = [];
        foreach (array_unique(array_filter($ips)) as $ip) {
            $result[$ip] = self::format($ip);
        }
        return $result;
    }

    private static function isPrivateOrReserved(string $ip): bool
    {
        if (in_array($ip, ['127.0.0.1', '::1'], true)) {
            return true;
        }

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
