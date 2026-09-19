<?php

namespace App\Http\Middleware;

use App\Services\TwoFactorAuthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mandatory 2FA for config('security_center.two_factor.enforce_for_role_ids')
 * (EC_ADMINISTRATOR by default). Re-checks live on every request (not just
 * at login), so an admin already logged in when this ships is caught on
 * their very next request — no gap from pre-existing sessions. Everything
 * is allowlisted except the Settings page itself and the 2FA endpoints, so
 * the affected admin can always reach the one place that lets them finish
 * setup — this restricts, it never truly locks anyone out.
 */
class EnforceTwoFactorForAdmins
{
    /** Route names an unconfigured admin may still reach. */
    private const ALLOWED_ROUTE_PREFIXES = [
        'settings',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = session('user');

        if (!$user || ($user['type'] ?? null) !== 'employee' || empty($user['id'])) {
            return $next($request);
        }

        $roleIds = $user['role_ids'] ?? array_filter([$user['role']['id'] ?? null]);
        $enforcedRoleIds = config('security_center.two_factor.enforce_for_role_ids', []);

        if (empty(array_intersect($roleIds, $enforcedRoleIds))) {
            return $next($request);
        }

        if ($this->isAllowedRoute($request)) {
            return $next($request);
        }

        $authUser = DB::table('auth_users')->where('employee_id', $user['id'])->first();

        if (!$authUser || TwoFactorAuthService::isEnabled($authUser)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success'              => false,
                'message'              => 'Two-factor authentication setup is required for your role before you can continue.',
                'requires_2fa_setup'   => true,
            ], 403);
        }

        return redirect()->route('settings.index')->with('force_2fa_setup', true);
    }

    private function isAllowedRoute(Request $request): bool
    {
        $routeName = $request->route()?->getName();

        if (!$routeName) {
            return false;
        }

        foreach (self::ALLOWED_ROUTE_PREFIXES as $prefix) {
            if ($routeName === $prefix || str_starts_with($routeName, "{$prefix}.")) {
                return true;
            }
        }

        return false;
    }
}
