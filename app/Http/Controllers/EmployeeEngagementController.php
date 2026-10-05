<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Services\HrProfile\EngagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Blok Engagement konsultan External (HC-D47, keputusan E3).
 *
 * Izin seksi: rute `employee.section:engagement[,view]`. Aturan tambahan di sini:
 *   - HANYA untuk karyawan External (Internal → tidak berlaku);
 *   - TIDAK pernah untuk diri sendiri (konsultan tidak melihat/mengubah engagement-nya sendiri);
 *   - TARIF + mata uang butuh izin terpisah `employee.section.engagement_rate.view|update`; tanpa izin,
 *     nilai tarif tidak dikirim ke browser dan field tarif ditolak saat simpan.
 * Semua aksi tulis: POST + header X-Requested-With (rute /api tanpa CSRF token; lihat EmployeeHrProfileController).
 */
class EmployeeEngagementController extends Controller
{
    public function __construct(private readonly EngagementService $service)
    {
    }

    public function show(int $employeeId): JsonResponse
    {
        if ($denied = $this->denyIfSelf($employeeId)) {
            return $denied;
        }

        $applicable = $this->isExternal($employeeId);
        if (!$applicable) {
            return response()->json(['success' => true, 'data' => ['applicable' => false]]);
        }

        $canViewRate = $this->can('employee.section.engagement_rate.view');

        return response()->json([
            'success' => true,
            'data'    => ['applicable' => true, 'can_view_rate' => $canViewRate, 'can_edit_rate' => $this->can('employee.section.engagement_rate.update')]
                + $this->service->forEmployee($employeeId, $canViewRate),
        ]);
    }

    public function save(Request $request, int $employeeId): JsonResponse
    {
        if (!$request->ajax()) {
            return response()->json(['success' => false, 'message' => 'Invalid request.'], 400);
        }
        if ($denied = $this->denyIfSelf($employeeId)) {
            return $denied;
        }
        if (!$this->isExternal($employeeId)) {
            return response()->json(['success' => false, 'message' => 'Engagement details apply to External consultants only.'], 422);
        }

        $canEditRate = $this->can('employee.section.engagement_rate.update');
        $input = $request->except(['_token']);

        try {
            $result = $this->service->save($employeeId, is_array($input) ? $input : [], $this->actorId(), $canEditRate);
        } catch (\Throwable $e) {
            Log::error('Engagement: gagal menyimpan', ['employee_id' => $employeeId, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Could not save the engagement details. Please try again.'], 500);
        }

        if (!$result['ok']) {
            $forbidden = !empty($result['rejected']);
            if ($forbidden) {
                Log::warning('Engagement: field terlarang ditolak', ['target' => $employeeId, 'actor' => $this->actorId(), 'fields' => $result['rejected']]);
            }

            return response()->json([
                'success' => false,
                'message' => $result['errors']['_'] ?? 'Please correct the highlighted fields.',
                'errors'  => $result['errors'],
            ], $forbidden ? 403 : 422);
        }

        return response()->json(['success' => true, 'message' => 'Engagement details saved successfully.']);
    }

    private function denyIfSelf(int $employeeId): ?JsonResponse
    {
        return $employeeId === $this->actorId()
            ? response()->json(['success' => false, 'message' => 'Engagement details are managed by HR.'], 403)
            : null;
    }

    private function isExternal(int $employeeId): bool
    {
        return DB::table('employee_basic_data')->where('employee_id', $employeeId)->value('employee_type') === 'External';
    }

    private function can(string $slug): bool
    {
        $viewer = Employee::find($this->actorId());

        return $viewer && in_array($slug, $viewer->allPermissionSlugs(), true);
    }

    private function actorId(): int
    {
        return (int) (session('user')['id'] ?? 0);
    }
}
