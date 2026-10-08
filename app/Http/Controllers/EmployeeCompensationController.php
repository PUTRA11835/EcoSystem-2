<?php

namespace App\Http\Controllers;

use App\Services\Payroll\CompensationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Seksi "Compensation" karyawan: penanda PTKP/BPJS untuk payroll + ringkasan gaji (baca saja). Komponen gaji diedit di kotak
 * Salary Components (Master Employee → Contract; EmployeeSalaryComponentController).
 *
 * IZIN: rute memakai `employee.section:compensation[,view]` → `employee.section.compensation.{view|update}`
 * (hanya halaman Master > Employee; tidak ada padanan My Profile, jadi target diri sendiri ikut ditolak
 * kecuali pemegang slug). Seluruh aksi tulis POST + wajib header X-Requested-With, seperti HR Profile.
 */
class EmployeeCompensationController extends Controller
{
    public function __construct(private readonly CompensationService $service)
    {
    }

    public function show(int $employeeId): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->forEmployee($employeeId)]);
    }

    public function saveTax(Request $request, int $employeeId): JsonResponse
    {
        if ($denied = $this->requireAjax($request)) {
            return $denied;
        }

        $result = $this->service->saveTax($employeeId, $request->only([
            'ptkp_code', 'bpjs_health_active', 'bpjs_employment_active', 'bpjs_dependents_count', 'payroll_activated',
        ]), $this->actorId());

        return $this->respond($result, 'Tax & BPJS settings saved.');
    }

    private function respond(array $result, string $okMessage): JsonResponse
    {
        if (!$result['ok']) {
            return response()->json([
                'success' => false,
                'message' => $result['errors']['_'] ?? 'Please correct the highlighted fields.',
                'errors'  => $result['errors'],
            ], 422);
        }

        return response()->json(['success' => true, 'message' => $okMessage] + (isset($result['id']) ? ['id' => $result['id']] : []));
    }

    private function requireAjax(Request $request): ?JsonResponse
    {
        return $request->ajax()
            ? null
            : response()->json(['success' => false, 'message' => 'Invalid request.'], 400);
    }

    private function actorId(): ?int
    {
        $id = session('user.id');

        return $id ? (int) $id : null;
    }
}
