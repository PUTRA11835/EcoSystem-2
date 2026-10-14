<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Services\Permissions\MenuRoleAccessService;
use App\Services\Permissions\PermissionChangeException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Menu List → "Siapa yang punya akses": API sisi-menu untuk Menu Access. Membaca: izin `management.roles` ATAU
 * `management.permissions`; mengubah (plan/apply/undo): `management.roles` — sama dengan Menu Access per role
 * (lihat routes/api.php). Logika di MenuRoleAccessService; pengendali ini hanya validasi bentuk dan kode HTTP.
 */
class MenuRoleAccessController extends Controller
{
    public function __construct(private readonly MenuRoleAccessService $service)
    {
    }

    public function show(int $menuId): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->service->access($menuId)]);
        } catch (PermissionChangeException $e) {
            return response()->json(['success' => false, 'code' => $e->codeName, 'message' => $e->getMessage()], 404);
        }
    }

    public function history(int $menuId): JsonResponse
    {
        try {
            return response()->json(['success' => true, 'data' => $this->service->history($menuId)]);
        } catch (PermissionChangeException $e) {
            return response()->json(['success' => false, 'code' => $e->codeName, 'message' => $e->getMessage()], 404);
        }
    }

    public function plan(Request $request, int $menuId): JsonResponse
    {
        $d = $this->validated($request);

        return $this->respond($this->service->plan($menuId, $d['scope'] ?? 'self', $d['changes']));
    }

    public function apply(Request $request, int $menuId): JsonResponse
    {
        $d = $this->validated($request);

        return $this->respond($this->service->apply($menuId, $d['scope'] ?? 'self', $d['changes'], (int) session('user.id'), $d['reason'] ?? null));
    }

    public function undo(Request $request, int $menuId): JsonResponse
    {
        $d = $request->validate(['change_ids' => 'required|array|min:1|max:200', 'change_ids.*' => 'integer|min:1']);

        return $this->respond($this->service->undo($menuId, $d['change_ids'], (int) session('user.id')));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'scope'                  => 'nullable|in:self,subtree',
            'changes'                => 'required|array|min:1|max:500',
            'changes.*.role_id'      => 'required|integer|min:1',
            'changes.*.action'       => 'required|in:set,revoke',
            'changes.*.can_create'   => 'sometimes|boolean',
            'changes.*.can_edit'     => 'sometimes|boolean',
            'changes.*.can_delete'   => 'sometimes|boolean',
            'reason'                 => 'nullable|string|max:255',
        ]);
    }

    private function respond(array $result): JsonResponse
    {
        if (!empty($result['ok'])) {
            return response()->json(['success' => true] + $result);
        }
        $status = match ($result['code'] ?? '') {
            'not_found' => 404,
            'conflict', 'already_reverted' => 409,
            default => 422,
        };

        return response()->json(['success' => false] + $result, $status);
    }
}
