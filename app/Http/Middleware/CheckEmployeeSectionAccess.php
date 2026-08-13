<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate untuk endpoint section employee (basic-data, address, family, dst).
 *
 * Endpoint /api/employees/{id}/<section> dipakai oleh DUA halaman yang
 * merender view yang sama (master.employee.show):
 *
 *   - Master > Employee > detail  -> mengubah data ORANG LAIN
 *   - My Profile (/profile)       -> mengubah data DIRI SENDIRI
 *
 * Karena itu gate-nya tidak boleh satu slug saja: memakai
 * master.employee.* akan mencabut kemampuan orang mengedit profilnya
 * sendiri, memakai my-profile.* akan membocorkan data orang lain.
 * Target request yang menentukan slug mana yang dipakai:
 *
 *   target == diri sendiri -> my-profile.section.{key}.{view|update}
 *   target == orang lain   -> employee.section.{key}.{view|update}
 *
 * Pemakaian di route:
 *
 *     ->middleware('employee.section:family,update')
 *
 * Argumen kedua opsional, default 'update' (semua endpoint tulis).
 */
class CheckEmployeeSectionAccess
{
    public function handle(Request $request, Closure $next, string $sectionKey, string $ability = 'update'): Response
    {
        $user = session('user');

        if (!$user || ($user['type'] ?? null) !== 'employee') {
            return $this->deny($request, 'Unauthenticated.', 401);
        }

        $selfId   = (int) ($user['id'] ?? 0);
        $targetId = (int) ($request->route('employeeId') ?? $request->route('id') ?? 0);

        $slug = $targetId === $selfId && $targetId !== 0
            ? "my-profile.section.{$sectionKey}.{$ability}"
            : "employee.section.{$sectionKey}.{$ability}";

        if (!$this->allows($selfId, $slug)) {
            return $this->deny(
                $request,
                'Akses ditolak. Akun Anda tidak memiliki izin untuk mengubah data ini. Hubungi administrator.',
                403
            );
        }

        return $next($request);
    }

    /**
     * Pakai daftar slug yang sudah di-cache ShareMenuPermissions supaya
     * satu request tidak memicu query menu berulang kali.
     */
    private function allows(int $employeeId, string $slug): bool
    {
        $slugs = Cache::get("perm_slugs_{$employeeId}");

        if ($slugs === null) {
            $employee = Employee::find($employeeId);
            $slugs    = $employee ? $employee->allPermissionSlugs() : [];
        }

        return in_array($slug, $slugs, true);
    }

    private function deny(Request $request, string $message, int $status): Response
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['success' => false, 'message' => $message], $status);
        }

        return redirect()->route('dashboard')->with('warning', $message);
    }
}
