<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a route on one capability of a menu slug — the Create / Edit / Delete
 * boxes of Management → Roles — where `menu:` (CheckMenuAccess) only ever
 * checks View.
 *
 * Usage: ->middleware('menu.can:general.recruitment.candidates,create')
 *
 * It is meant to sit behind a `menu:{slug}` group, which has already
 * established the session and the View right; on its own it still refuses
 * anyone who is not a signed-in employee.
 */
class CheckMenuPermission
{
    private const ACTIONS = ['view', 'create', 'edit', 'delete'];

    public function handle(Request $request, Closure $next, string $menuSlug, string $action): Response
    {
        abort_unless(in_array($action, self::ACTIONS, true), 500, "Unknown menu capability [{$action}].");

        $user = session('user');
        $employee = ($user['type'] ?? null) === 'employee' ? Employee::find($user['id'] ?? null) : null;

        if ($employee && $employee->hasMenuPermission($menuSlug, "can_{$action}")) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Akun Anda tidak memiliki izin untuk melakukan aksi ini. Hubungi administrator.',
            ], 403);
        }

        return redirect()->route('dashboard')
            ->with('warning', 'Akses ditolak. Akun Anda tidak memiliki izin untuk melakukan aksi tersebut. Hubungi administrator jika Anda membutuhkan akses.');
    }
}
