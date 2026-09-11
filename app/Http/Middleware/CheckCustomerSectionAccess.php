<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate untuk endpoint section customer / business partner
 * (basic-data, address, contact, identification, bank, credential,
 * history, attachment).
 *
 * Tidak ada konsep "diri sendiri" di sisi customer — employee selalu
 * mengubah data pihak lain — jadi slug-nya tunggal:
 *
 *     customer.section.{key}.{view|update}
 *
 * Pemakaian di route:
 *
 *     ->middleware('customer.section:contact,update')
 *
 * Argumen kedua opsional, default 'update'.
 *
 * Lihat CheckEmployeeSectionAccess untuk padanannya di sisi employee
 * (yang perlu percabangan self/orang lain karena view-nya dipakai
 * bersama halaman My Profile).
 */
class CheckCustomerSectionAccess
{
    public function handle(Request $request, Closure $next, string $sectionKey, string $ability = 'update'): Response
    {
        $user = session('user');

        if (!$user || ($user['type'] ?? null) !== 'employee') {
            return $this->deny($request, 'Unauthenticated.', 401);
        }

        $slug = "customer.section.{$sectionKey}.{$ability}";

        if (!$this->allows((int) ($user['id'] ?? 0), $slug)) {
            return $this->deny(
                $request,
                'Akses ditolak. Akun Anda tidak memiliki izin untuk mengubah data ini. Hubungi administrator.',
                403
            );
        }

        return $next($request);
    }

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
