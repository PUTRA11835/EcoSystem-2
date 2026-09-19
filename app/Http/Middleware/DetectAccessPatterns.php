<?php

namespace App\Http\Middleware;

use App\Models\SecurityEvent;
use App\Services\LoginSecurityService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Response-pattern detection: path scanning (repeated 404s on routes that
 * don't exist at all), ID enumeration (repeated 403/404 on resource-detail
 * routes, e.g. /tickets/{id}), and mass data access (one employee opening
 * many distinct records fast). Needs the RESPONSE status code, so unlike
 * DetectAttackPatterns (request-time, payload-based) this wraps the whole
 * request/response cycle. Counters are rolling windows in cache - no new
 * tables, and every finding here is log-only, same as the rest of Security
 * Center (a busy helpdesk agent legitimately opens many tickets a day -
 * thresholds are tuned to be well above that, but this can still false-
 * positive on an unusually busy day, which is why it's a dashboard flag to
 * review, not a block).
 */
class DetectAccessPatterns
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        try {
            $this->track($request, $response);
        } catch (\Throwable $e) {
            Log::warning('DetectAccessPatterns: tracking failed', ['error' => $e->getMessage()]);
        }

        return $response;
    }

    private function track(Request $request, Response $response): void
    {
        $status = $response->getStatusCode();
        $route  = $request->route();

        if ($route === null) {
            if ($status === 404) {
                $this->trackPathScan($request);
            }
            return;
        }

        $paramValues = $this->routeParamValues($request, $route);
        $numericParam = null;
        foreach ($paramValues as $value) {
            if (is_numeric($value)) {
                $numericParam = $value;
                break;
            }
        }

        if ($numericParam === null) {
            return; // not a resource-detail-shaped route - nothing more to track
        }

        if (in_array($status, [403, 404], true)) {
            $this->trackIdEnumeration($request);
        } elseif ($status === 200 && $request->isMethod('get')) {
            $this->trackMassDataAccess($request, $route, $numericParam);
        }
    }

    /**
     * Raw URL segment values for each {param} in the route's URI pattern,
     * read positionally from the actual request path rather than from
     * Route::parameters() - which, for implicitly-bound routes, holds the
     * resolved Eloquent model by the time $next() has returned, not the
     * raw id. This avoids depending on binding behavior entirely.
     */
    private function routeParamValues(Request $request, $route): array
    {
        $patternSegments = explode('/', trim($route->uri(), '/'));
        $pathSegments    = explode('/', trim($request->path(), '/'));

        if (count($patternSegments) !== count($pathSegments)) {
            return [];
        }

        $values = [];
        foreach ($patternSegments as $i => $segment) {
            if (str_starts_with($segment, '{') && str_ends_with($segment, '}')) {
                $values[] = $pathSegments[$i];
            }
        }

        return $values;
    }

    private function trackPathScan(Request $request): void
    {
        $ip  = $request->ip();
        $cfg = config('security_center.access_patterns.path_scan');
        $key = "access_pathscan_{$ip}";

        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, now()->addMinutes($cfg['window_minutes']));

        if ($count < $cfg['threshold'] || !Cache::add("access_pathscan_flagged_{$ip}", true, now()->addMinutes($cfg['window_minutes']))) {
            return;
        }

        $event = SecurityEvent::record([
            'event_type'  => 'path_scanning_probe',
            'severity'    => 'medium',
            'module'      => 'Recon',
            'status'      => 'open',
            'title'       => "Path scanning detected ({$count} unmatched routes)",
            'description' => "IP {$ip} hit {$count} nonexistent routes within {$cfg['window_minutes']} minutes (last: {$request->method()} {$request->path()}) - consistent with directory/endpoint scanning.",
            'target_ip'   => $ip,
            'ip_address'  => $ip,
            'user_agent'  => (string) $request->userAgent(),
            'payload'     => ['count' => $count, 'last_path' => $request->path()],
        ]);

        if ($event) {
            app(LoginSecurityService::class)->notifyAdmins($event, "Path scanning detected from {$ip}");
        }
    }

    private function trackIdEnumeration(Request $request): void
    {
        $ip  = $request->ip();
        $cfg = config('security_center.access_patterns.id_enumeration');
        $key = "access_idenum_{$ip}";

        $count = (int) Cache::get($key, 0) + 1;
        Cache::put($key, $count, now()->addMinutes($cfg['window_minutes']));

        if ($count < $cfg['threshold'] || !Cache::add("access_idenum_flagged_{$ip}", true, now()->addMinutes($cfg['window_minutes']))) {
            return;
        }

        $event = SecurityEvent::record([
            'event_type'  => 'id_enumeration_probe',
            'severity'    => 'high',
            'module'      => 'Recon',
            'status'      => 'open',
            'title'       => "ID enumeration detected ({$count} denied/missing resources)",
            'description' => "IP {$ip} got {$count} 403/404 responses on resource-detail routes within {$cfg['window_minutes']} minutes (last: {$request->method()} {$request->path()}) - consistent with probing sequential or random record IDs.",
            'target_ip'   => $ip,
            'ip_address'  => $ip,
            'user_agent'  => (string) $request->userAgent(),
            'payload'     => ['count' => $count, 'last_path' => $request->path()],
        ]);

        if ($event) {
            app(LoginSecurityService::class)->notifyAdmins($event, "Possible ID enumeration from {$ip}");
        }
    }

    private function trackMassDataAccess(Request $request, $route, string $resourceId): void
    {
        $user = session('user');
        if (!$user || ($user['type'] ?? null) !== 'employee' || empty($user['id'])) {
            return; // only an authenticated-employee signal - insider risk, not anonymous probing
        }

        $employeeId = $user['id'];
        $routeKey   = $route->getName() ?? $route->uri();
        $cfg        = config('security_center.access_patterns.mass_data_access');
        $cacheKey   = 'access_massread_' . $employeeId . '_' . md5($routeKey);

        $seen = Cache::get($cacheKey, []);
        $seen[$resourceId] = true;
        Cache::put($cacheKey, $seen, now()->addMinutes($cfg['window_minutes']));

        $distinctCount = count($seen);
        if ($distinctCount < $cfg['distinct_ids_threshold'] || !Cache::add("access_massread_flagged_{$employeeId}_" . md5($routeKey), true, now()->addMinutes($cfg['window_minutes']))) {
            return;
        }

        $event = SecurityEvent::record([
            'event_type'          => 'mass_data_access',
            'severity'            => 'high',
            'module'              => 'Export',
            'status'              => 'open',
            'title'               => "Unusual read volume: {$distinctCount} distinct records",
            'description'         => 'Employee "' . ($user['name'] ?? $employeeId) . "\" opened {$distinctCount} distinct records via \"{$routeKey}\" within {$cfg['window_minutes']} minutes - review for possible data scraping (could also be a genuinely busy day; confirm before acting).",
            'target_employee_id'  => $employeeId,
            'target_identifier'   => $user['name'] ?? null,
            'ip_address'          => $request->ip(),
            'payload'             => ['distinct_count' => $distinctCount, 'route' => $routeKey],
        ]);

        if ($event) {
            app(LoginSecurityService::class)->notifyAdmins($event, 'Unusual read volume by ' . ($user['name'] ?? "employee #{$employeeId}"));
        }
    }
}
