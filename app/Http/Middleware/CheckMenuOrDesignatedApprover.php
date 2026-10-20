<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Models\PurchaseOrder\PurchaseOrder;
use App\Services\Approval\ApprovalEngine;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pintu halaman peninjauan yang terbuka bagi DUA jenis orang:
 *
 *  1. pemegang salah satu slug menu yang disebut, atau
 *  2. karyawan yang DITUNJUK sebagai penyetuju pada alur persetujuan modul itu
 *     (berdasarkan peran, nama, atasan, atau pemimpin proyek pada langkahnya).
 *
 * Alasannya: alur persetujuan sudah menyebut siapa yang berwenang. Memaksa admin
 * mencentang slug menu yang sama sekali terpisah untuk orang-orang itu hanya
 * membuka peluang dua konfigurasi yang saling bertentangan — penyetuju yang
 * ditunjuk tetapi tidak bisa membuka halamannya, atau sebaliknya. Orang di luar
 * HR (kepala departemen, direktur, finance) cukup ditunjuk pada alur; mereka
 * tidak perlu menu HR / umum apa pun.
 *
 * Pemakaian: `menu.approver:overtime,general.overtime,general.overtime.approve`
 * (modul pertama, sisanya slug). Modul yang dikenal ApprovalEngine: overtime,
 * reimbursement, purchase-request, cash-advance, cash-advance-report; `purchase-order`
 * memakai approver_id dokumennya; `any` = penyetuju di modul mana pun (Approval Inbox).
 *
 * Penyetuju tanpa slug HANYA boleh membuka dokumen yang ia ikuti (pemohon atau
 * tercantum pada langkahnya) — rute berparameter dokumen diperiksa di sini, jadi
 * "ditunjuk" tidak berarti "boleh membaca seluruh daftar". Hanya MEMBUKA pintu;
 * siapa yang boleh bertindak pada sebuah dokumen tetap diputuskan service-nya.
 */
class CheckMenuOrDesignatedApprover
{
    public function __construct(private readonly ApprovalEngine $engine)
    {
    }

    public function handle(Request $request, Closure $next, string $module, string ...$slugs): Response
    {
        $user = session('user');

        if (!$user || ($user['type'] ?? null) !== 'employee') {
            return redirect()->route('login');
        }

        $employee = Employee::find($user['id'] ?? null);

        $holdsSlug = $employee && collect($slugs)->contains(fn (string $slug) => $employee->canAccessMenu($slug));

        $allowed = $holdsSlug
            || ($employee && $this->isApprover($module, (int) $employee->employee_id) && $this->mayOpenDocument($request, $module, (int) $employee->employee_id));

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
            'purchase-order' => PurchaseOrder::where('approver_id', $employeeId)->exists(),
            'any'            => $this->engine->isApproverAnywhere($employeeId),
            default          => $this->engine->isDesignatedApprover($module, $employeeId),
        };
    }

    /** A designated approver without the slug opens only the document they take part in; list pages carry no document. */
    private function mayOpenDocument(Request $request, string $module, int $employeeId): bool
    {
        $param = ApprovalEngine::MODULES[$module]['param'] ?? null;
        $document = $param ? $request->route($param) : null;

        return !($document instanceof Model) || $this->engine->participates($module, $document, $employeeId);
    }
}
