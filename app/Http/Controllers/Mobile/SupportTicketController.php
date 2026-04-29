<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\SupportTicketResource;
use App\Models\DeliverySupport;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Mobile Support Ticket Controller
 *
 * Handles Delivery Support list and detail for the SupportListScreen on mobile.
 * Protected by the `mobile.employee` middleware.
 */
class SupportTicketController extends Controller
{
    // =========================================================================
    // GET /api/mobile/employee/support-tickets
    // =========================================================================

    public function index(Request $request)
    {
        try {
            $query = DeliverySupport::with([
                'client.basicData',
                'deliveryOwner.basicData',
                'activities.employees.basicData',
            ]);

            if ($search = $request->query('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhereHas('client.basicData', fn($cq) =>
                          $cq->where('name_1', 'like', "%{$search}%")
                      );
                });
            }

            if ($status = $request->query('status')) {
                $query = $this->applyStatusFilter($query, $status);
            }

            $supports = $query->orderBy('updated_at', 'desc')->paginate(15);

            $items = collect($supports->items())->map(function ($ds) {
                $members = $ds->activities
                    ->flatMap(fn($activity) => $activity->employees)
                    ->unique('employee_id')
                    ->values();

                $ds->setRelation('teamMembersList', $members);
                return $ds;
            });

            return response()->json([
                'success' => true,
                'data'    => SupportTicketResource::collection($items),
                'meta'    => [
                    'current_page' => $supports->currentPage(),
                    'last_page'    => $supports->lastPage(),
                    'total'        => $supports->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile\SupportTicketController@index', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve support ticket list. Please try again.',
            ], 500);
        }
    }

    // =========================================================================
    // GET /api/mobile/employee/support-tickets/{id}
    // =========================================================================

    public function show($id)
    {
        try {
            $support = DeliverySupport::with([
                'client.basicData',
                'deliveryOwner.basicData',
                'activities.employees.basicData',
            ])->findOrFail($id);

            $members = $support->activities
                ->flatMap(fn($activity) => $activity->employees)
                ->unique('employee_id')
                ->values();

            $support->setRelation('teamMembersList', $members);

            return response()->json([
                'success' => true,
                'data'    => new SupportTicketResource($support),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Support ticket #{$id} not found.",
            ], 404);
        } catch (\Exception $e) {
            Log::error('Mobile\SupportTicketController@show', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve support ticket details. Please try again.',
            ], 500);
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Filter query by mobile status label → calculated_progress range.
     *
     * Mobile label    → condition
     * "In Process"    → 0 < calculated_progress < 100
     * "Not Started"   → calculated_progress = 0
     * "Closed"        → calculated_progress >= 100
     * others / "All"  → no filter
     */
    private function applyStatusFilter($query, string $status)
    {
        return match (strtolower(trim($status))) {
            'in process'  => $query->where('calculated_progress', '>', 0)->where('calculated_progress', '<', 100),
            'not started' => $query->where('calculated_progress', 0),
            'closed'      => $query->where('calculated_progress', '>=', 100),
            default       => $query,
        };
    }
}
