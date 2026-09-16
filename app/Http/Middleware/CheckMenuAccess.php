<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckMenuAccess
{
    /**
     * 🔴 D180 — Menerima SATU ATAU LEBIH slug (`menu:a,b`), diperiksa sebagai
     * GABUNGAN (OR): lolos bila karyawan memegang SALAH SATU. Dibutuhkan oleh
     * rute langkah persetujuan Cash Advance, yang SATU set rutenya melayani
     * DUA modul sekaligus (CA dan CAR, D136) dengan slug terpisah masing-
     * masing — pemisahan yang LEBIH SEMPIT (modul mana persisnya) tetap
     * diperiksa ULANG di dalam controller, bukan di sini. Rute dengan satu
     * slug (`menu:x`, mayoritas rute di aplikasi ini) berperilaku identik
     * seperti sebelumnya — variadic ini backward compatible.
     */
    public function handle(Request $request, Closure $next, string ...$menuSlugs): Response
    {
        $user = session('user');

        if (!$user || ($user['type'] ?? null) !== 'employee') {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login');
        }

        $employee = Employee::find($user['id'] ?? null);

        $allowed = $employee && collect($menuSlugs)->contains(fn (string $slug) => $employee->canAccessMenu($slug));

        if (!$allowed) {
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
