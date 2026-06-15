<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ShareMenuPermissions
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = session('user');

        if ($user && ($user['type'] ?? null) === 'employee') {
            $employee  = Employee::find($user['id'] ?? null);
            $permSlugs = $employee ? $employee->allPermissionSlugs() : [];
        } else {
            $permSlugs = [];
        }

        View::share('permSlugs', $permSlugs);
        View::share('can', fn(string $slug) => in_array($slug, $permSlugs));

        return $next($request);
    }
}
