<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Models\PurchaseOrder\PurchaseOrder;
use App\Services\Overtime\OvertimeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pintu halaman peninjauan yang terbuka bagi DUA jenis orang:
 *
 *  1. pemegang salah satu slug menu yang disebut, atau
 *  2. karyawan yang DITUNJUK sebagai penyetuju pada alur persetujuan modul itu
 *     (berdasarkan peran atau nama pada langkahnya).
 *
 * Alasannya: alur persetujuan sudah menyebut siapa yang berwenang. Memaksa admin
 * mencentang slug menu yang sama sekali terpisah untuk orang-orang itu hanya
 * membuka peluang dua konfigurasi yang saling bertentangan — penyetuju yang
 * ditunjuk tetapi tidak bisa membuka halamannya, atau sebaliknya.
 *
 * Pemakaian: `menu.approver:overtime,general.overtime,general.overtime.approve`
 * (modul pertama, sisanya slug). Hanya MEMBUKA pintu; siapa yang boleh bertindak
 * pada sebuah dokumen tetap diputuskan service-nya.
 */
class CheckMenuOrDesignatedApprover
{
    public function handle(Request $request, Closure $next, string $module, string ...$slugs): Response
    {
        $user = session('user');

        if (!$user || ($user['type'] ?? null) !== 'employee') {
            return redirect()->route('login');
        }

        $employee = Employee::find($user['id'] ?? null);

        $allowed = $employee && (
            collect($slugs)->contains(fn (string $slug) => $employee->canAccessMenu($slug))
            || $this->isApprover($module, (int) $employee->employee_id)
        );

        if (!$allowed) {
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => 'Akses ditolak.'], 403);
            }

            return redirect()->route('dashboard')
                ->with('warning', 'Akses ditolak. Akun Anda tidak memiliki izin untuk mengakses halaman tersebut. Hubungi administrator jika Anda membutuhkan akses.');
        }

        return $next($request);
    }

    private function isApprover(string $module, int $employeeId): bool
    {
        return match ($module) {
            'overtime'       => app(OvertimeService::class)->isDesignatedApprover($employeeId),
            'purchase-order' => PurchaseOrder::where('approver_id', $employeeId)->exists(),
            default    => false,
        };
    }
}
