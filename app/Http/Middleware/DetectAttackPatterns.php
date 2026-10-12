<?php

namespace App\Http\Middleware;

use App\Models\SecurityEvent;
use App\Services\LoginSecurityService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Attack-pattern scanner (XSS / SQLi / path traversal / command injection /
 * SSRF / LFI-RFI / open redirect / PHP object injection / scanner-tool
 * User-Agent), log-only by default.
 *
 * EcoSystem is a support ticketing system - legitimate ticket descriptions,
 * pasted stack traces, and AI chat messages routinely contain text that can
 * resemble an attack payload. Rather than trying to exempt those fields by
 * regex tuning, this middleware only ever scans an allowlist of things that
 * should never hold free text: query params, route params, the URI path,
 * and a small config-driven list of POST field names
 * (config('security_center.waf.fields')). Ticket/message/note bodies are
 * structurally out of scope, not pattern-exempted. A couple of individual
 * patterns (bare private IPs - see config('security_center.waf.scoped_patterns'))
 * are further restricted to URL-shaped fields only, since those specific
 * patterns are plausible in legitimate helpdesk search text otherwise.
 */
class DetectAttackPatterns
{
    private const MAX_EVENTS_PER_REQUEST = 5;

    public function handle(Request $request, Closure $next)
    {
        $blocked = false;

        try {
            $blocked = $this->scan($request);
        } catch (\Throwable $e) {
            Log::warning('DetectAttackPatterns: scan failed', ['error' => $e->getMessage()]);
        }

        if ($blocked) {
            return response()->json([
                'success' => false,
                'message' => 'Request blocked by security policy.',
            ], 400);
        }

        return $next($request);
    }

    /** @return bool whether the request should be blocked (waf.mode === 'block' and a match occurred) */
    private function scan(Request $request): bool
    {
        $patterns = config('security_center.waf.patterns', []);
        $matched  = false;
        $eventsLogged = 0;

        $candidates = $this->collectCandidates($request);

        if (!empty($patterns)) {
            foreach ($candidates as $fieldName => $value) {
                if ($eventsLogged >= self::MAX_EVENTS_PER_REQUEST) {
                    break;
                }
                if (!is_string($value) || $value === '') {
                    continue;
                }

                foreach ($patterns as $category => $categoryPatterns) {
                    foreach ($categoryPatterns as $patternName => $regex) {
                        if (!$this->patternAppliesToField($category, $patternName, $fieldName)) {
                            continue;
                        }
                        if (preg_match($regex, $value) === 1) {
                            $this->logEvent($request, $category, $patternName, $fieldName, $value);
                            $eventsLogged++;
                            $matched = true;
                            continue 3; // one match per field is enough, move to the next field
                        }
                    }
                }
            }
        }

        if ($eventsLogged < self::MAX_EVENTS_PER_REQUEST && $this->checkOpenRedirect($request, $candidates)) {
            $matched = true;
        }

        if ($this->checkScannerUserAgent($request)) {
            $matched = true;
        }

        return $matched && config('security_center.waf.mode') === 'block';
    }

    /**
     * Known scanner/pentest-tool User-Agent strings (sqlmap, nikto, nmap, ...).
     * Deliberately does NOT flag bare "curl"/"python-requests"/empty UA -
     * this app receives legitimate automated calls (health checks, Power
     * Automate, MS Graph webhooks) that would make those noisy.
     */
    private function checkScannerUserAgent(Request $request): bool
    {
        $userAgent = strtolower((string) $request->userAgent());
        if ($userAgent === '') {
            return false;
        }

        foreach (config('security_center.waf.scanner_user_agents', []) as $needle) {
            if (!str_contains($userAgent, $needle)) {
                continue;
            }

            $ip = $request->ip();

            $event = SecurityEvent::record([
                'event_type'     => 'scanner_probe',
                'severity'       => 'high',
                'module'         => 'WAF',
                'status'         => 'open',
                'title'          => "Known scanner tool detected: {$needle}",
                'description'    => "User-Agent matched known scanner signature \"{$needle}\" on {$request->method()} {$request->path()}.",
                'target_ip'      => $ip,
                'ip_address'     => $ip,
                'user_agent'     => (string) $request->userAgent(),
                'request_method' => $request->method(),
                'request_path'   => $request->path(),
                'payload'        => ['matched_tool' => $needle],
            ]);

            if ($event) {
                $this->maybeNotify($event, 'scanner_probe', $ip);
            }

            return true;
        }

        return false;
    }

    /** Whether a scoped pattern (config('security_center.waf.scoped_patterns')) allows this field. */
    private function patternAppliesToField(string $category, string $patternName, string $fieldLabel): bool
    {
        $scoped = config("security_center.waf.scoped_patterns.{$category}.{$patternName}");
        if (!$scoped) {
            return true; // unscoped - applies to every field
        }

        $allowlist = config("security_center.waf.{$scoped}", []);
        $baseName  = preg_replace('/^(query|route|field):/', '', $fieldLabel);

        return in_array($baseName, $allowlist, true);
    }

    /**
     * Open redirect: a redirect-shaped field whose value is an absolute URL
     * pointing at a different host than this request. Needs the current
     * request's host, so it can't be a static config regex like the others.
     */
    private function checkOpenRedirect(Request $request, array $candidates): bool
    {
        $redirectFields = config('security_center.waf.redirect_fields', []);
        $currentHost    = strtolower($request->getHost());

        foreach ($candidates as $fieldName => $value) {
            $baseName = preg_replace('/^(query|route|field):/', '', $fieldName);
            if (!in_array($baseName, $redirectFields, true) || !is_string($value) || $value === '') {
                continue;
            }

            $target = parse_url($value);
            if (!$target || empty($target['host'])) {
                continue; // relative path - not an open-redirect target
            }

            if (strtolower($target['host']) === $currentHost) {
                continue; // points back at this app - fine
            }

            $ip = $request->ip();

            $event = SecurityEvent::record([
                'event_type'     => 'open_redirect_probe',
                'severity'       => 'low',
                'module'         => 'WAF',
                'status'         => 'open',
                'title'          => "Open-redirect target in {$fieldName}",
                'description'    => "Field \"{$fieldName}\" pointed to external host \"{$target['host']}\" on {$request->method()} {$request->path()}.",
                'target_ip'      => $ip,
                'ip_address'     => $ip,
                'user_agent'     => (string) $request->userAgent(),
                'request_method' => $request->method(),
                'request_path'   => $request->path(),
                'payload'        => ['field' => $fieldName, 'target_host' => $target['host'], 'excerpt' => Str::limit($value, 500, '')],
            ]);

            if ($event) {
                $this->maybeNotify($event, 'open_redirect_probe', $ip);
            }

            return true;
        }

        return false;
    }

    /** @return array<string, string> field label => value */
    private function collectCandidates(Request $request): array
    {
        $candidates = [];

        foreach ($this->flatten($request->query()) as $key => $value) {
            $candidates["query:{$key}"] = $value;
        }

        $route = $request->route();
        if ($route) {
            foreach ($route->parameters() as $key => $value) {
                if (is_scalar($value)) {
                    $candidates["route:{$key}"] = (string) $value;
                }
            }
        }

        $candidates['path'] = $request->decodedPath();

        // Allowlisted POST field names only - never the full body, and
        // never for multipart/file-upload requests.
        if (!str_starts_with((string) $request->header('Content-Type'), 'multipart/form-data')) {
            foreach (config('security_center.waf.fields', []) as $field) {
                $value = $request->input($field);
                if (is_string($value)) {
                    $candidates["field:{$field}"] = $value;
                }
            }
        }

        return $candidates;
    }

    /** @return array<string, string> */
    private function flatten(array $arr, string $prefix = ''): array
    {
        $result = [];
        foreach ($arr as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : "{$prefix}.{$key}";
            if (is_array($value)) {
                $result += $this->flatten($value, $fullKey);
            } else {
                $result[$fullKey] = (string) $value;
            }
        }
        return $result;
    }

    private function logEvent(Request $request, string $category, string $patternName, string $fieldName, string $value): void
    {
        $eventType = match ($category) {
            'sqli'                  => 'sqli_probe',
            'xss'                   => 'xss_probe',
            'path_traversal'        => 'path_traversal_probe',
            'command_injection'     => 'command_injection_probe',
            'ssrf'                  => 'ssrf_probe',
            'lfi_rfi'               => 'lfi_probe',
            'php_object_injection'  => 'php_object_injection_probe',
            default                 => 'attack_probe',
        };

        // Command injection / SSRF / LFI / deserialization can lead to RCE
        // or internal-network access if the endpoint turns out exploitable -
        // rated above plain XSS/SQLi probing, which is far more commonly noise.
        $severity = match ($category) {
            'command_injection', 'ssrf', 'lfi_rfi', 'php_object_injection' => 'high',
            default                                                       => 'medium',
        };

        $ip = $request->ip();

        $event = SecurityEvent::record([
            'event_type'     => $eventType,
            'severity'       => $severity,
            'module'         => 'WAF',
            'status'         => 'open',
            'title'          => ucfirst(str_replace('_', ' ', $category)) . " pattern detected in {$fieldName}",
            'description'    => "Matched pattern \"{$patternName}\" in field \"{$fieldName}\" on {$request->method()} {$request->path()}.",
            'target_ip'      => $ip,
            'ip_address'     => $ip,
            'user_agent'     => (string) $request->userAgent(),
            'request_method' => $request->method(),
            'request_path'   => $request->path(),
            'payload'        => [
                'field'   => $fieldName,
                'pattern' => $patternName,
                'excerpt' => Str::limit($value, 500, ''),
            ],
        ]);

        if ($event) {
            $this->maybeNotify($event, $eventType, $ip);
        }
    }

    private function maybeNotify(SecurityEvent $event, string $eventType, string $ip): void
    {
        $minutes = (int) config('security_center.waf.notify_debounce_minutes', 10);

        if (Cache::add("waf_notif_{$ip}_{$eventType}", true, now()->addMinutes($minutes))) {
            app(LoginSecurityService::class)->notifyAdmins($event, "{$eventType} pattern detected from {$ip}");
        }
    }
}
