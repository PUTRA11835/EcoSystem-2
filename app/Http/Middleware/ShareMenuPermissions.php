<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ShareMenuPermissions
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = session('user');

        if ($user && ($user['type'] ?? null) === 'employee') {
            $userId    = $user['id'] ?? null;
            $cacheKey  = "perm_slugs_{$userId}";

            // Cache 60 min — invalidated explicitly when roles/permissions change
            $permSlugs = Cache::remember($cacheKey, now()->addMinutes(60), function () use ($userId) {
                $employee = Employee::find($userId);
                return $employee ? $employee->allPermissionSlugs() : [];
            });
        } else {
            $permSlugs = [];
        }

        View::share('permSlugs', $permSlugs);
        View::share('can', fn(string $slug) => in_array($slug, $permSlugs));

        return $next($request);
    }
}
