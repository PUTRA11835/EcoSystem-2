<?php

namespace App\Http\Middleware;

use App\Models\DeliveryProject;
use App\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Varian CheckMenuAccess yang ADITIF: izin lolos bila employee punya slug menu
 * TERSEBUT, ATAU bila ia terdaftar sebagai Project Owner pada project yang
 * sedang diakses.
 *
 * Latar: "Project Owner" bukan role yang menempel di tabel employee — ia hanya
 * sebuah kolom pada masing-masing project. Karena itu izinnya tidak bisa
 * diwakili slug menu (yang selalu lintas project); grant dari kepemilikan ini
 * berlaku HANYA pada project tempat ia jadi owner.
 *
 * Slug menu yang sudah ada tetap berlaku seperti biasa — middleware ini tidak
 * pernah mencabut akses, hanya menambah.
 *
 * Pemakaian: ->middleware('menu.owner:delivery-project.risk.manage')
 */
class CheckMenuOrProjectOwner
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

        if ($employee) {
            // Jalur 1 — izin berbasis role (perilaku lama, lintas project).
            if ($employee->canAccessMenu($menuSlug)) {
                return $next($request);
            }

            // Jalur 2 — Project Owner pada project ini saja.
            $project = $this->resolveProject($request);
            if ($project && $project->isOwnedByEmployee($employee->employee_id)) {
                return $next($request);
            }
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Akun Anda tidak memiliki izin untuk melakukan aksi ini. Hubungi administrator.',
            ], 403);
        }

        return redirect()->route('dashboard')
            ->with('warning', 'Akses ditolak. Akun Anda tidak memiliki izin untuk mengakses halaman tersebut. Hubungi administrator jika Anda membutuhkan akses.');
    }

    private function resolveProject(Request $request): ?DeliveryProject
    {
        $param = $request->route('project');

        if ($param instanceof DeliveryProject) {
            return $param;
        }

        return empty($param) ? null : DeliveryProject::find($param);
    }
}
