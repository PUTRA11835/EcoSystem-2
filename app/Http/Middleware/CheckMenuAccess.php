<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckMenuAccess
{
    public function handle(Request $request, Closure $next, string $menuSlug): Response
    {
        $user = session('user');

        if (!$user || ($user['type'] ?? null) !== 'employee') {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login');
        }

        $employee = Employee::find($user['id'] ?? null);
        $canAccess = $employee && $employee->canAccessMenu($menuSlug);

        // TEMP DIAGNOSTIC — remove after the 2026-09-29 menu-access investigation.
        Log::info('CheckMenuAccess diagnostic', [
            'menu_slug'        => $menuSlug,
            'session_user_id'  => $user['id'] ?? null,
            'employee_found'   => (bool) $employee,
            'employee_role_ids'=> $employee ? $employee->roles()->pluck('employee_role.id')->all() : null,
            'can_access'       => $canAccess,
            'path'             => $request->path(),
        ]);

        if (!$canAccess) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akses ditolak. Akun Anda tidak memiliki izin untuk mengakses halaman ini. Hubungi administrator.',
                ], 403);
            }

            // Jangan redirect ke dashboard jika route saat ini IS dashboard (akan looping)
            if ($request->routeIs('dashboard')) {
                abort(403, 'Akun Anda tidak memiliki izin untuk mengakses dashboard. Hubungi administrator.');
            }

            return redirect()->route('dashboard')
                ->with('warning', 'Akses ditolak. Akun Anda tidak memiliki izin untuk mengakses halaman tersebut. Hubungi administrator jika Anda membutuhkan akses.');
        }

        return $next($request);
    }
}
