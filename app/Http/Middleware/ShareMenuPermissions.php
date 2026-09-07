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

            // Cache 60 min — invalidated explicitly when roles/permissions change
            $permSlugs = Cache::remember("perm_slugs_{$userId}", now()->addMinutes(60), function () use ($userId) {
                $employee = Employee::find($userId);
                return $employee ? $employee->allPermissionSlugs() : [];
            });

            $permMatrix = Cache::remember("perm_matrix_{$userId}", now()->addMinutes(60), function () use ($userId) {
                $employee = Employee::find($userId);
                return $employee ? $employee->allPermissionMatrix() : [];
            });
        } else {
            $permSlugs  = [];
            $permMatrix = [];
        }

        View::share('permSlugs', $permSlugs);
        View::share('permMatrix', $permMatrix);
        View::share('can', fn(string $slug) => in_array($slug, $permSlugs));
        View::share('canDo', fn(string $slug, string $action = 'view') => (bool) ($permMatrix[$slug][$action] ?? false));

        return $next($request);
    }
}
