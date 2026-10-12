<?php

namespace App\Http\Middleware;

use App\Services\TwoFactorAuthService;
use App\Support\TwoFactorEnforcementSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mandatory 2FA for whichever roles are configured via Control Center →
 * Two-Factor Enforcement (App\Support\TwoFactorEnforcementSettings —
 * EC_ADMINISTRATOR by default, admin-editable without a code deploy).
 * Re-checks live on every request (not just at login), so an admin already
 * logged in when this ships is caught on their very next request — no gap
 * from pre-existing sessions. Everything is allowlisted except the Settings
 * page itself and the 2FA endpoints, so the affected admin can always reach
 * the one place that lets them finish setup — this restricts, it never truly
 * locks anyone out.
 */
class EnforceTwoFactorForAdmins
{
    /**
     * Route names an unconfigured admin may still reach. Must include the
     * Two-Factor Enforcement settings page itself (admin.two-factor-
     * enforcement) — otherwise an admin caught by this middleware can never
     * navigate there to turn the requirement off again, since this same
     * middleware would redirect them straight back to Settings on the way.
     *
     * Logging out is NOT handled via this list — see the path check at the
     * top of isAllowedRoute() instead. Confirmed by direct Route::getRoutes()
     * inspection (reproducible even with opcache fully disabled) that
     * `/api/auth/logout` — the endpoint the dashboard's logout button actually
     * calls — never keeps the ->name('api.auth.logout') set on it in
     * routes/api.php, for reasons not tracked down; name-matching it here
     * would silently never match. Path-matching sidesteps that entirely and
     * is the right check anyway for something as safety-critical as always
     * being able to log out.
     */
    private const ALLOWED_ROUTE_PREFIXES = [
        'settings',
        'admin.two-factor-enforcement',
    ];

    /** Request paths (no leading slash, Request::is() wildcard syntax) an unconfigured admin may always reach. */
    private const ALLOWED_PATHS = [
        'logout',
        'api/auth/logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = session('user');

        if (!$user || ($user['type'] ?? null) !== 'employee' || empty($user['id'])) {
            return $next($request);
        }

        $roleIds = $user['role_ids'] ?? array_filter([$user['role']['id'] ?? null]);
        $enforcedRoleIds = TwoFactorEnforcementSettings::roleIds();

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
        foreach (self::ALLOWED_PATHS as $path) {
            if ($request->is($path)) {
                return true;
            }
        }

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
