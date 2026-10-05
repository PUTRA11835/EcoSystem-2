<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\EmployeeRole;
use App\Services\Permissions\RoleMenuAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Halaman & API "Menu Access" per role (HC-D63/D64). Seluruh rute digerbang `menu:management.roles`
 * (lihat routes/api.php dan routes/web.php). Logika ada di RoleMenuAccessService — pengendali ini hanya
 * validasi bentuk permintaan dan menerjemahkan hasil ke kode HTTP.
 */
class RoleMenuAccessController extends Controller
{
    public function __construct(private readonly RoleMenuAccessService $service)
    {
    }

    /** Halaman penuh (menggantikan modal sempit di daftar Role). */
    public function page(int $id)
    {
        $role = EmployeeRole::withCount('employees')->findOrFail($id);

        return view('management.roles.menu-access', ['role' => $role]);
    }

    public function matrix(int $id): JsonResponse
    {
        EmployeeRole::findOrFail($id);

        return response()->json(['success' => true, 'data' => $this->service->matrix($id)]);
    }

    public function plan(Request $request, int $id): JsonResponse
    {
        EmployeeRole::findOrFail($id);
        $data = $this->validated($request);

        return $this->respond($this->service->plan($id, $data['changes']));
    }

    public function apply(Request $request, int $id): JsonResponse
    {
        EmployeeRole::findOrFail($id);
        $data = $this->validated($request);

        return $this->respond($this->service->apply($id, $data['changes'], (int) session('user.id'), $data['reason'] ?? null));
    }

    public function compare(int $id, int $otherId): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->service->compare($id, $otherId)]);
    }

    public function copyDiff(int $id, int $sourceId): JsonResponse
    {
        EmployeeRole::findOrFail($id);
        EmployeeRole::findOrFail($sourceId);

        return response()->json(['success' => true, 'data' => $this->service->copyDiff($id, $sourceId)]);
    }

    public function history(int $id): JsonResponse
    {
        EmployeeRole::findOrFail($id);

        return response()->json(['success' => true, 'data' => $this->service->history($id)]);
    }

    public function undo(int $id, int $changeId): JsonResponse
    {
        EmployeeRole::findOrFail($id);
        // catatan harus milik role ini — mencegah membatalkan riwayat role lain lewat URL
        abort_unless(DB::table('role_menu_changes')->where('id', $changeId)->where('role_id', $id)->exists(), 404);

        return $this->respond($this->service->undo($changeId, (int) session('user.id')));
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'changes'              => 'required|array|max:2000',
            'changes.*.menu_id'    => 'required|integer|min:1',
            'changes.*.revoke'     => 'sometimes|boolean',
            'changes.*.can_view'   => 'sometimes|boolean',
            'changes.*.can_create' => 'sometimes|boolean',
            'changes.*.can_edit'   => 'sometimes|boolean',
            'changes.*.can_delete' => 'sometimes|boolean',
            'reason'               => 'nullable|string|max:255',
        ]);
    }

    /** ['ok' => bool, 'code' => …] → JSON + kode HTTP yang bermakna. */
    private function respond(array $result): JsonResponse
    {
        if ($result['ok']) {
            return response()->json(['success' => true] + $result);
        }

        $status = match ($result['code']) {
            'not_found' => 404,
            'conflict', 'already_reverted' => 409,
            default => 422,
        };

        return response()->json(['success' => false] + $result, $status);
    }
}
