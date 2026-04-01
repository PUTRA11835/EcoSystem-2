<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\ProjectDetailResource;
use App\Http\Resources\Mobile\ProjectListResource;
use App\Models\DeliveryProject;
use App\Models\DeliveryProjectUpdate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Mobile Project Controller
 *
 * Semua endpoint dilindungi middleware `mobile.employee`.
 * Auth user didapat dari $request->user() → instance AuthUser.
 */
class ProjectController extends Controller
{
    // =========================================================================
    // GET /api/mobile/employee/projects
    // =========================================================================

    /**
     * List project dengan pagination & filter.
     *
     * Query params:
     *   ?search=  string  — cari nama customer atau description
     *   ?status=  string  — Open | In Process | Closed
     *   ?page=    integer — halaman (default 1, per halaman 15)
     */
    public function index(Request $request)
    {
        $query = DeliveryProject::with([
            'client.basicData',
            'deliveryOwner.basicData',
            'teamMembers.basicData',
        ]);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhereHas('client.basicData', fn ($cq) =>
                      $cq->where('name_1', 'like', "%{$search}%")
                  );
            });
        }

        if ($status = $request->query('status')) {
            $query->where('category', $status);
        }

        $projects = $query->orderBy('updated_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'data'    => ProjectListResource::collection($projects->items()),
            'meta'    => [
                'current_page' => $projects->currentPage(),
                'last_page'    => $projects->lastPage(),
                'total'        => $projects->total(),
            ],
        ]);
    }

    // =========================================================================
    // GET /api/mobile/employee/projects/{id}
    // =========================================================================

    /**
     * Detail satu project, termasuk phases, team, dan updates.
     */
    public function show($id)
    {
        $project = DeliveryProject::with([
            'client.basicData',
            'deliveryOwner.basicData',
            'teamMembers.basicData',
            'phases.plannings',
            'updates',
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data'    => new ProjectDetailResource($project),
        ]);
    }

    // =========================================================================
    // POST /api/mobile/employee/projects/{id}/updates
    // =========================================================================

    /**
     * Tambah catatan update ke project.
     *
     * Request body:
     *   { "note": "string" }
     */
    public function storeUpdate(Request $request, $id)
    {
        $project = DeliveryProject::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'note' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $authUser = $request->user();
        $employee = $authUser->employee;
        $bd = $employee?->basicData;
        $authorName = $bd
            ? trim($bd->first_name . ' ' . ($bd->last_name ?? ''))
            : ($authUser->email ?? null);

        $update = DeliveryProjectUpdate::create([
            'delivery_projects_id' => $project->id,
            'notes'                => $request->note,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Update berhasil ditambahkan.',
            'data'    => [
                'id'         => $update->id,
                'author'     => [
                    'id'   => $employee?->employee_id,
                    'name' => $authorName,
                ],
                'note'       => $update->notes,
                'created_at' => $update->created_at?->toDateString(),
            ],
        ], 201);
    }
}
