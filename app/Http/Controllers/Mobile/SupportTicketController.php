<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\SupportTicketResource;
use App\Models\DeliverySupport;
use Illuminate\Http\Request;

/**
 * Mobile Support Ticket Controller
 *
 * Menangani daftar dan detail Delivery Support untuk SupportListScreen di mobile.
 * Dilindungi oleh middleware `mobile.employee`.
 */
class SupportTicketController extends Controller
{
    // =========================================================================
    // GET /api/mobile/employee/support-tickets
    // =========================================================================

    /**
     * List Delivery Support dengan filter search dan status.
     *
     * Query params:
     *   ?search=  string  — cari berdasarkan nama support atau nama customer
     *   ?status=  string  — All | In Process | Not Started | Closed
     *   ?page=    integer — halaman (default 1, per halaman 15)
     */
    public function index(Request $request)
    {
        $query = DeliverySupport::with([
            'client.basicData',
            'deliveryOwner.basicData',
            'activities.employees.basicData',
        ]);

        // Filter: search (nama support atau nama customer)
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhereHas('client.basicData', fn($cq) =>
                      $cq->where('name_1', 'like', "%{$search}%")
                  );
            });
        }

        // Filter: status (diturunkan dari calculated_progress)
        if ($status = $request->query('status')) {
            $query = $this->applyStatusFilter($query, $status);
        }

        $supports = $query->orderBy('updated_at', 'desc')->paginate(15);

        // Inject team members dari relasi Eloquent yang sudah di-eager load
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
    }

    // =========================================================================
    // GET /api/mobile/employee/support-tickets/{id}
    // =========================================================================

    public function show($id)
    {
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
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Filter query berdasarkan label status mobile → calculated_progress.
     *
     * Mobile label      → kondisi
     * "In Process"      → 0 < calculated_progress < 100
     * "Not Started"     → calculated_progress = 0
     * "Closed"          → calculated_progress >= 100
     * lainnya / "All"   → tidak difilter
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
