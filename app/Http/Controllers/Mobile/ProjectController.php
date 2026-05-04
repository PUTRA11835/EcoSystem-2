<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\ProjectDetailResource;
use App\Http\Resources\Mobile\ProjectListResource;
use App\Models\DeliveryProject;
use App\Models\DeliveryProjectUpdate;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Mobile Project Controller
 *
 * All endpoints are protected by the `mobile.employee` middleware.
 * Authenticated user: $request->user() → AuthUser instance.
 */
class ProjectController extends Controller
{
    // =========================================================================
    // GET /api/mobile/employee/projects
    // =========================================================================

    public function index(Request $request)
    {
        try {
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
        } catch (\Exception $e) {
            Log::error('Mobile\ProjectController@index', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve project list. Please try again.',
            ], 500);
        }
    }

    // =========================================================================
    // GET /api/mobile/employee/projects/{id}
    // =========================================================================

    public function show($id)
    {
        try {
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
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Project #{$id} not found.",
            ], 404);
        } catch (\Exception $e) {
            Log::error('Mobile\ProjectController@show', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve project details. Please try again.',
            ], 500);
        }
    }

    // =========================================================================
    // POST /api/mobile/employee/projects/{id}/updates
    // =========================================================================

    public function storeUpdate(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'note' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Project update note is required.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $project = DeliveryProject::findOrFail($id);

            $authUser   = $request->user();
            $employee   = $authUser->employee;
            $bd         = $employee?->basicData;
            $authorName = $bd
                ? trim($bd->first_name . ' ' . ($bd->last_name ?? ''))
                : ($authUser->email ?? null);

            $update = DeliveryProjectUpdate::create([
                'delivery_projects_id' => $project->id,
                'notes'                => $request->note,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Project update added successfully.',
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
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Project #{$id} not found.",
            ], 404);
        } catch (\Exception $e) {
            Log::error('Mobile\ProjectController@storeUpdate', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to add project update. Please try again.',
            ], 500);
        }
    }
}
