<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\SupportTicketResource;
use App\Models\DeliverySupport;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $query = DeliverySupport::with(['client.basicData', 'deliveryOwner.basicData']);

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

        // Ambil team members per support ticket secara batch
        $supportIds  = collect($supports->items())->pluck('id');
        $membersByDs = $this->fetchTeamMembersBySupport($supportIds->toArray());

        // Inject ke tiap item agar bisa diakses di Resource
        $items = collect($supports->items())->map(function ($ds) use ($membersByDs) {
            $ds->setRelation('teamMembersList', $membersByDs->get($ds->id, collect()));
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
        ])->findOrFail($id);

        $members = $this->fetchTeamMembersBySupport([$id])->get($id, collect());
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
     * Ambil team members dari activities untuk beberapa delivery support sekaligus.
     * Menghindari N+1 query.
     *
     * @param int[] $supportIds
     * @return \Illuminate\Support\Collection  keyed by delivery_support_id
     */
    private function fetchTeamMembersBySupport(array $supportIds): \Illuminate\Support\Collection
    {
        if (empty($supportIds)) {
            return collect();
        }

        $rows = DB::table('delivery_support_activities as dsa')
            ->join('delivery_support_activity_employees as dsae', 'dsa.id', '=', 'dsae.delivery_support_activity_id')
            ->join('employee as e', 'dsae.employee_id', '=', 'e.employee_id')
            ->join('employee_basic_data as ebd', 'e.employee_id', '=', 'ebd.employee_id')
            ->whereIn('dsa.delivery_support_id', $supportIds)
            ->select(
                'dsa.delivery_support_id',
                'e.employee_id',
                DB::raw("TRIM(CONCAT(ebd.first_name, ' ', COALESCE(ebd.last_name, ''))) as full_name")
            )
            ->get();

        // Group by delivery_support_id, deduplicate by employee_id
        return $rows->groupBy('delivery_support_id')->map(fn($group) =>
            $group->unique('employee_id')->map(fn($r) => (object) [
                'employee_id' => $r->employee_id,
                'basicData'   => (object) ['first_name' => $r->full_name, 'last_name' => null],
            ])
        );
    }

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
            default       => $query,  // "All" atau status tidak dikenal → no filter
        };
    }
}
