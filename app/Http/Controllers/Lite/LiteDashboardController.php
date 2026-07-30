<?php

namespace App\Http\Controllers\Lite;

use App\Enums\RoleId;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LiteDashboardController extends Controller
{
    /**
     * Kembalikan data dashboard sesuai role user yang sedang login.
     * Adaptasi dari DashboardController::index() — mengembalikan JSON, bukan view.
     *
     * GET /api/lite/dashboard
     */
    public function index(Request $request)
    {
        try {
            $user = $this->resolveUser($request);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 401);
            }

            // ── Statistik global (semua role) ───────────────────────────────
            $dashboardData = [
                'employee'        => DB::table('employee')->where('is_active', true)->count(),
                'customers'       => DB::table('customer')->where('is_active', true)->where('type', \App\Models\Customer::TYPE_CUSTOMER)->count(),
                'active_projects' => DB::table('delivery_projects')
                    ->whereNotIn('status', ['completed', 'closed', 'cancel'])
                    ->count(),
                'total_tickets'   => DB::table('ticket')->whereNull('deleted_at')->whereNull('is_hidden')->count(),
            ];

            $roleId = (int) ($user['role']['id'] ?? 0);

            // ── EC Administrator & EC User & Delivery Support Head ───────────
            if (in_array($roleId, [
                RoleId::EC_ADMINISTRATOR->value,
                RoleId::EC_USER->value,
                RoleId::DELIVERY_SUPPORT_HEAD->value,
            ], true)) {
                $this->appendFullTicketStats($dashboardData);
                $this->appendTicketChart($dashboardData);
                $this->appendRecentTickets($dashboardData);
                $this->appendTeamLoad($dashboardData);
                $this->appendSlaComplianceSummary($dashboardData);

                if ($roleId === RoleId::EC_ADMINISTRATOR->value) {
                    $dashboardData['staging_pending'] = DB::table('staging_tickets')
                        ->where('status', 'unvalidated')
                        ->count();
                }

                if ($roleId === RoleId::DELIVERY_SUPPORT_HEAD->value) {
                    try {
                        $dashboardData['timesheet_pending'] = DB::table('timesheet')
                            ->where('status', 'submitted')
                            ->count();
                    } catch (\Throwable) {
                        $dashboardData['timesheet_pending'] = 0;
                    }
                }
            }

            // ── Delivery Helpdesk ────────────────────────────────────────────
            if ($roleId === RoleId::DELIVERY_HELPDESK->value) {
                $base = DB::table('ticket')->whereNull('deleted_at')->whereNull('is_hidden');

                $this->appendFullTicketStats($dashboardData);

                $dashboardData['unassigned_count'] = (clone $base)
                    ->whereNull('ticket_lead_id')
                    ->whereNotIn('status', ['closed', 'cancelled'])
                    ->count();

                $dashboardData['staging_pending'] = DB::table('staging_tickets')
                    ->where('status', 'unvalidated')
                    ->count();

                $dashboardData['very_high_count'] = (clone $base)
                    ->where('ticket_priority', 'Very High')
                    ->whereNotIn('status', ['closed', 'cancelled'])
                    ->count();

                $dashboardData['priority_breakdown'] = (clone $base)
                    ->whereNotIn('status', ['closed', 'cancelled'])
                    ->select('ticket_priority', DB::raw('COUNT(*) as cnt'))
                    ->groupBy('ticket_priority')
                    ->pluck('cnt', 'ticket_priority')
                    ->toArray();

                $this->appendTicketChart($dashboardData);
                $this->appendRecentTicketsWithSla($dashboardData);
                $this->appendUrgentTickets($dashboardData);

                try {
                    $dashboardData['sla_breached'] = $this->visibleSla(DB::table('ticket_sla')->whereNotNull('ticket_id'))
                        ->where('resolution_status', 'breached')
                        ->count();

                    $dashboardData['sla_warning'] = $this->visibleSla(DB::table('ticket_sla as ts'), 'ts.ticket_id')
                        ->whereNotNull('ts.ticket_id')
                        ->whereIn('ts.resolution_status', ['pending', 'paused'])
                        ->whereNotNull('ts.resolution_due_at')
                        ->where('ts.resolution_due_at', '>', now())
                        ->where('ts.resolution_due_at', '<=', now()->addHours(4))
                        ->count();

                    $slaMet      = $this->visibleSla(DB::table('ticket_sla'))->where('resolution_status', 'met')->count();
                    $slaBreached = $this->visibleSla(DB::table('ticket_sla'))->where('resolution_status', 'breached')->count();
                    $dashboardData['sla_compliance'] = ($slaMet + $slaBreached) > 0
                        ? round($slaMet / ($slaMet + $slaBreached) * 100, 1)
                        : null;
                } catch (\Throwable) {
                    $dashboardData['sla_breached']   = 0;
                    $dashboardData['sla_warning']    = 0;
                    $dashboardData['sla_compliance'] = null;
                }
            }

            // ── Delivery Support Manager ─────────────────────────────────────
            if ($roleId === RoleId::DELIVERY_SUPPORT_MANAGER->value) {
                $employeeId = $user['id'];

                $managedDeliveryIds = DB::table('delivery_support_managers')
                    ->where('employee_id', $employeeId)
                    ->pluck('delivery_support_id');

                $managedTicketIds = DB::table('delivery_support_activities')
                    ->whereIn('delivery_support_id', $managedDeliveryIds)
                    ->whereNotNull('ticket_id')
                    ->pluck('ticket_id')
                    ->unique()
                    ->values();

                $base = DB::table('ticket')
                    ->whereNull('deleted_at')->whereNull('is_hidden')
                    ->where(function ($q) use ($managedTicketIds) {
                        $q->whereIn('ticket_id', $managedTicketIds)->orWhereNull('ticket_lead_id');
                    });

                $this->appendTicketStatsFromQuery($dashboardData, $base);

                $dashboardData['unassigned_count'] = (clone $base)
                    ->whereNull('ticket_lead_id')
                    ->whereNotIn('status', ['closed', 'cancelled'])
                    ->count();

                $dashboardData['very_high_count'] = (clone $base)
                    ->where('ticket_priority', 'Very High')
                    ->whereNotIn('status', ['closed', 'cancelled'])
                    ->count();

                $dashboardData['priority_breakdown'] = (clone $base)
                    ->whereNotIn('status', ['closed', 'cancelled'])
                    ->select('ticket_priority', DB::raw('COUNT(*) as cnt'))
                    ->groupBy('ticket_priority')
                    ->pluck('cnt', 'ticket_priority')
                    ->toArray();

                $dashboardData['today_new']    = (clone $base)->whereDate('created_at', today())->count();
                $dashboardData['today_closed'] = (clone $base)->where('status', 'closed')->whereDate('updated_at', today())->count();

                $this->appendTicketChartFromQuery($dashboardData, $base);

                $dashboardData['team_load'] = DB::table('ticket as t')
                    ->whereNull('t.deleted_at')->whereNull('t.is_hidden')
                    ->whereIn('t.ticket_id', $managedTicketIds)
                    ->whereNotIn('t.status', ['closed', 'cancelled'])
                    ->join('employee as e', 't.ticket_lead_id', '=', 'e.employee_id')
                    ->leftJoin('employee_basic_data as ebd', 'e.employee_id', '=', 'ebd.employee_id')
                    ->select(
                        'e.employee_id',
                        DB::raw("TRIM(CONCAT(COALESCE(ebd.first_name,''),' ',COALESCE(ebd.last_name,''))) as name"),
                        DB::raw('COUNT(*) as open_count')
                    )
                    ->groupBy('e.employee_id', 'ebd.first_name', 'ebd.last_name')
                    ->orderByDesc('open_count')
                    ->limit(8)
                    ->get();

                $this->appendRecentTicketsWithSlaFromQuery($dashboardData, $managedTicketIds);
                $this->appendUrgentTicketsFromQuery($dashboardData, $managedTicketIds);

                try {
                    $scopeIds    = (clone $base)->pluck('ticket_id');
                    $slaMet      = DB::table('ticket_sla')->whereIn('ticket_id', $scopeIds)->where('resolution_status', 'met')->count();
                    $slaBreached = DB::table('ticket_sla')->whereIn('ticket_id', $scopeIds)->where('resolution_status', 'breached')->count();
                    $dashboardData['sla_summary'] = [
                        'met'             => $slaMet,
                        'breached'        => $slaBreached,
                        'compliance_rate' => ($slaMet + $slaBreached) > 0
                            ? round($slaMet / ($slaMet + $slaBreached) * 100, 1)
                            : null,
                    ];
                } catch (\Throwable) {
                    $dashboardData['sla_summary'] = null;
                }
            }

            // ── Delivery Support User ────────────────────────────────────────
            if ($roleId === RoleId::DELIVERY_SUPPORT_USER->value) {
                $employeeId = $user['id'];

                $picIds    = DB::table('ticket')->whereNull('deleted_at')->whereNull('is_hidden')
                    ->where('ticket_lead_id', $employeeId)->pluck('ticket_id');
                $memberIds = DB::table('ticket_member')->where('employee_id', $employeeId)->pluck('ticket_id');
                $ticketIds = $picIds->merge($memberIds)->unique()->values();

                $base      = DB::table('ticket')->whereNull('deleted_at')->whereNull('is_hidden')->whereIn('ticket_id', $ticketIds);
                $activeIds = (clone $base)->whereNotIn('status', ['closed', 'cancelled'])->pluck('ticket_id');

                $this->appendTicketStatsFromQuery($dashboardData, $base);

                $dashboardData['as_pic_count']    = $picIds->count();
                $dashboardData['active_count']    = $activeIds->count();
                $dashboardData['today_closed']    = (clone $base)->where('status', 'closed')->whereDate('updated_at', today())->count();
                $dashboardData['very_high_count'] = (clone $base)->whereIn('ticket_id', $activeIds)->where('ticket_priority', 'Very High')->count();

                $dashboardData['priority_breakdown'] = (clone $base)
                    ->whereIn('ticket_id', $activeIds)
                    ->select('ticket_priority', DB::raw('COUNT(*) as cnt'))
                    ->groupBy('ticket_priority')
                    ->pluck('cnt', 'ticket_priority')
                    ->toArray();

                $this->appendTicketChartFromQuery($dashboardData, $base);

                $dashboardData['recent_tickets'] = DB::table('ticket as t')
                    ->whereNull('t.deleted_at')->whereNull('t.is_hidden')
                    ->whereIn('t.ticket_id', $ticketIds)
                    ->leftJoin('customer as c', 't.customer_id', '=', 'c.customer_id')
                    ->leftJoin('customer_basic_data as cbd', 'c.customer_id', '=', 'cbd.customer_id')
                    ->leftJoin('employee as e', 't.ticket_lead_id', '=', 'e.employee_id')
                    ->leftJoin('employee_basic_data as ebd', 'e.employee_id', '=', 'ebd.employee_id')
                    ->leftJoin('ticket_sla as ts', 't.ticket_id', '=', 'ts.ticket_id')
                    ->select(
                        't.ticket_id', 't.ticket_number', 't.description',
                        't.status', 't.ticket_priority', 't.updated_at', 't.created_at',
                        'cbd.name_1 as customer_name',
                        DB::raw("COALESCE(TRIM(CONCAT(COALESCE(ebd.first_name,''),' ',COALESCE(ebd.last_name,''))), 'Unassigned') as pic_name"),
                        DB::raw("IF(t.ticket_lead_id = {$employeeId}, 1, 0) as is_pic"),
                        'ts.resolution_status as sla_status',
                        'ts.resolution_due_at as sla_due_at',
                        'ts.ball_holder'
                    )
                    ->orderByDesc('t.updated_at')
                    ->limit(8)
                    ->get();

                $dashboardData['urgent_tickets'] = DB::table('ticket as t')
                    ->whereNull('t.deleted_at')->whereNull('t.is_hidden')
                    ->whereIn('t.ticket_id', $activeIds)
                    ->leftJoin('customer as c', 't.customer_id', '=', 'c.customer_id')
                    ->leftJoin('customer_basic_data as cbd', 'c.customer_id', '=', 'cbd.customer_id')
                    ->leftJoin('ticket_sla as ts', 't.ticket_id', '=', 'ts.ticket_id')
                    ->where(function ($q) {
                        $q->where('t.ticket_priority', 'Very High')
                          ->orWhere('ts.resolution_status', 'breached');
                    })
                    ->select(
                        't.ticket_id', 't.ticket_number', 't.description',
                        't.status', 't.ticket_priority', 't.updated_at',
                        'cbd.name_1 as customer_name',
                        'ts.resolution_status as sla_status',
                        'ts.resolution_due_at as sla_due_at'
                    )
                    ->orderByRaw("FIELD(t.ticket_priority,'Very High','High','Medium','Low')")
                    ->limit(5)
                    ->get();
            }

            return response()->json([
                'success' => true,
                'data'    => $dashboardData,
            ]);

        } catch (\Exception $e) {
            Log::error('LiteDashboard error', [
                'error'    => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard data.',
            ], 500);
        }
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /** Ambil user dari session atau request attribute (Bearer token) */
    private function resolveUser(Request $request): ?array
    {
        return $request->session()->get('user')
            ?? $request->attributes->get('lite_user');
    }

    /** Ticket stats global (semua tiket, tidak di-scope per user) */
    private function appendFullTicketStats(array &$data): void
    {
        $base = DB::table('ticket')->whereNull('deleted_at')->whereNull('is_hidden');

        $data['ticket_stats'] = [
            'total'                   => (clone $base)->count(),
            'open'                    => (clone $base)->where('status', 'open')->count(),
            'inprocess'               => (clone $base)->where('status', 'inprocess')->count(),
            'waiting_on_customer'     => (clone $base)->where('status', 'waiting_on_customer')->count(),
            'waiting_on_3rd_party'    => (clone $base)->where('status', 'waiting_on_3rd_party')->count(),
            'waiting_to_confirmation' => (clone $base)->where('status', 'waiting_to_confirmation')->count(),
            'hold'                    => (clone $base)->where('status', 'hold')->count(),
            'cancelled'               => (clone $base)->where('status', 'cancelled')->count(),
            'closed'                  => (clone $base)->where('status', 'closed')->count(),
        ];
    }

    /** Ticket stats dari query builder tertentu (scoped per user/role) */
    private function appendTicketStatsFromQuery(array &$data, $base): void
    {
        $data['ticket_stats'] = [
            'total'                   => (clone $base)->count(),
            'open'                    => (clone $base)->where('status', 'open')->count(),
            'inprocess'               => (clone $base)->where('status', 'inprocess')->count(),
            'waiting_on_customer'     => (clone $base)->where('status', 'waiting_on_customer')->count(),
            'waiting_on_3rd_party'    => (clone $base)->where('status', 'waiting_on_3rd_party')->count(),
            'waiting_to_confirmation' => (clone $base)->where('status', 'waiting_to_confirmation')->count(),
            'hold'                    => (clone $base)->where('status', 'hold')->count(),
            'cancelled'               => (clone $base)->where('status', 'cancelled')->count(),
            'closed'                  => (clone $base)->where('status', 'closed')->count(),
        ];
    }

    /** Chart trend 30 hari — semua tiket global */
    private function appendTicketChart(array &$data): void
    {
        $start30 = now()->subDays(29)->format('Y-m-d');
        $byDay   = DB::table('ticket')
            ->whereNull('deleted_at')->whereNull('is_hidden')
            ->where('start_date', '>=', $start30)
            ->select(DB::raw('DATE(start_date) as day'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('day')
            ->pluck('cnt', 'day')
            ->toArray();

        $labels = [];
        $values = [];
        for ($i = 29; $i >= 0; $i--) {
            $d        = now()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('d M');
            $values[] = $byDay[$d] ?? 0;
        }

        $data['ticket_chart'] = ['labels' => $labels, 'data' => $values];
    }

    /** Chart trend 30 hari — dari query builder tertentu */
    private function appendTicketChartFromQuery(array &$data, $base): void
    {
        $start30 = now()->subDays(29)->format('Y-m-d');
        $byDay   = (clone $base)
            ->where('start_date', '>=', $start30)
            ->select(DB::raw('DATE(start_date) as day'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('day')
            ->pluck('cnt', 'day')
            ->toArray();

        $labels = [];
        $values = [];
        for ($i = 29; $i >= 0; $i--) {
            $d        = now()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('d M');
            $values[] = $byDay[$d] ?? 0;
        }

        $data['ticket_chart'] = ['labels' => $labels, 'data' => $values];
    }

    /** 8 tiket terbaru — global, tanpa SLA */
    private function appendRecentTickets(array &$data): void
    {
        $data['recent_tickets'] = DB::table('ticket as t')
            ->whereNull('t.deleted_at')->whereNull('t.is_hidden')
            ->leftJoin('customer as c', 't.customer_id', '=', 'c.customer_id')
            ->leftJoin('customer_basic_data as cbd', 'c.customer_id', '=', 'cbd.customer_id')
            ->leftJoin('employee as e', 't.ticket_lead_id', '=', 'e.employee_id')
            ->leftJoin('employee_basic_data as ebd', 'e.employee_id', '=', 'ebd.employee_id')
            ->select(
                't.ticket_id', 't.ticket_number', 't.description',
                't.status', 't.ticket_priority', 't.created_at',
                'cbd.name_1 as customer_name',
                DB::raw("COALESCE(TRIM(CONCAT(COALESCE(ebd.first_name,''),' ',COALESCE(ebd.last_name,''))), 'Unassigned') as pic_name")
            )
            ->orderByDesc('t.created_at')
            ->limit(8)
            ->get();
    }

    /** 8 tiket terbaru — global, dengan SLA */
    private function appendRecentTicketsWithSla(array &$data): void
    {
        $data['recent_tickets'] = DB::table('ticket as t')
            ->whereNull('t.deleted_at')->whereNull('t.is_hidden')
            ->leftJoin('customer as c', 't.customer_id', '=', 'c.customer_id')
            ->leftJoin('customer_basic_data as cbd', 'c.customer_id', '=', 'cbd.customer_id')
            ->leftJoin('employee as e', 't.ticket_lead_id', '=', 'e.employee_id')
            ->leftJoin('employee_basic_data as ebd', 'e.employee_id', '=', 'ebd.employee_id')
            ->leftJoin('ticket_sla as ts', 't.ticket_id', '=', 'ts.ticket_id')
            ->select(
                't.ticket_id', 't.ticket_number', 't.description',
                't.status', 't.ticket_priority', 't.created_at', 't.updated_at',
                'cbd.name_1 as customer_name',
                DB::raw("COALESCE(TRIM(CONCAT(COALESCE(ebd.first_name,''),' ',COALESCE(ebd.last_name,''))), 'Unassigned') as pic_name"),
                'ts.resolution_status as sla_status',
                'ts.resolution_due_at as sla_due_at',
                'ts.ball_holder'
            )
            ->orderByDesc('t.updated_at')
            ->limit(8)
            ->get();
    }

    /** 8 tiket terbaru — scoped berdasarkan managedTicketIds */
    private function appendRecentTicketsWithSlaFromQuery(array &$data, $managedTicketIds): void
    {
        $data['recent_tickets'] = DB::table('ticket as t')
            ->whereNull('t.deleted_at')->whereNull('t.is_hidden')
            ->where(function ($q) use ($managedTicketIds) {
                $q->whereIn('t.ticket_id', $managedTicketIds)->orWhereNull('t.ticket_lead_id');
            })
            ->leftJoin('customer as c', 't.customer_id', '=', 'c.customer_id')
            ->leftJoin('customer_basic_data as cbd', 'c.customer_id', '=', 'cbd.customer_id')
            ->leftJoin('employee as e', 't.ticket_lead_id', '=', 'e.employee_id')
            ->leftJoin('employee_basic_data as ebd', 'e.employee_id', '=', 'ebd.employee_id')
            ->leftJoin('ticket_sla as ts', 't.ticket_id', '=', 'ts.ticket_id')
            ->select(
                't.ticket_id', 't.ticket_number', 't.description',
                't.status', 't.ticket_priority', 't.created_at', 't.updated_at',
                'cbd.name_1 as customer_name',
                DB::raw("COALESCE(TRIM(CONCAT(COALESCE(ebd.first_name,''),' ',COALESCE(ebd.last_name,''))), 'Unassigned') as pic_name"),
                'ts.resolution_status as sla_status',
                'ts.resolution_due_at as sla_due_at'
            )
            ->orderByDesc('t.updated_at')
            ->limit(8)
            ->get();
    }

    /** Team load global */
    private function appendTeamLoad(array &$data): void
    {
        $data['team_load'] = DB::table('ticket as t')
            ->whereNull('t.deleted_at')->whereNull('t.is_hidden')
            ->whereNotIn('t.status', ['closed', 'cancelled'])
            ->join('employee as e', 't.ticket_lead_id', '=', 'e.employee_id')
            ->leftJoin('employee_basic_data as ebd', 'e.employee_id', '=', 'ebd.employee_id')
            ->select(
                'e.employee_id',
                DB::raw("TRIM(CONCAT(COALESCE(ebd.first_name,''),' ',COALESCE(ebd.last_name,''))) as name"),
                DB::raw('COUNT(*) as open_count')
            )
            ->groupBy('e.employee_id', 'ebd.first_name', 'ebd.last_name')
            ->orderByDesc('open_count')
            ->limit(6)
            ->get();
    }

    /** Tiket urgent global (Very High atau SLA breached) */
    private function appendUrgentTickets(array &$data): void
    {
        $data['urgent_tickets'] = DB::table('ticket as t')
            ->whereNull('t.deleted_at')->whereNull('t.is_hidden')
            ->whereNotIn('t.status', ['closed', 'cancelled'])
            ->leftJoin('customer as c', 't.customer_id', '=', 'c.customer_id')
            ->leftJoin('customer_basic_data as cbd', 'c.customer_id', '=', 'cbd.customer_id')
            ->leftJoin('ticket_sla as ts', 't.ticket_id', '=', 'ts.ticket_id')
            ->where(function ($q) {
                $q->where('t.ticket_priority', 'Very High')
                  ->orWhere('ts.resolution_status', 'breached');
            })
            ->select(
                't.ticket_id', 't.ticket_number', 't.description',
                't.status', 't.ticket_priority', 't.updated_at',
                'cbd.name_1 as customer_name',
                'ts.resolution_status as sla_status',
                'ts.resolution_due_at as sla_due_at',
                'ts.ball_holder'
            )
            ->orderByRaw("FIELD(t.ticket_priority,'Very High','High','Medium','Low')")
            ->orderByDesc('t.updated_at')
            ->limit(6)
            ->get();
    }

    /** Tiket urgent scoped per managedTicketIds */
    private function appendUrgentTicketsFromQuery(array &$data, $managedTicketIds): void
    {
        $data['urgent_tickets'] = DB::table('ticket as t')
            ->whereNull('t.deleted_at')->whereNull('t.is_hidden')
            ->whereNotIn('t.status', ['closed', 'cancelled'])
            ->where(function ($q) use ($managedTicketIds) {
                $q->whereIn('t.ticket_id', $managedTicketIds)->orWhereNull('t.ticket_lead_id');
            })
            ->leftJoin('customer as c', 't.customer_id', '=', 'c.customer_id')
            ->leftJoin('customer_basic_data as cbd', 'c.customer_id', '=', 'cbd.customer_id')
            ->leftJoin('ticket_sla as ts', 't.ticket_id', '=', 'ts.ticket_id')
            ->where(function ($q) {
                $q->where('t.ticket_priority', 'Very High')
                  ->orWhere('ts.resolution_status', 'breached');
            })
            ->select(
                't.ticket_id', 't.ticket_number', 't.description',
                't.status', 't.ticket_priority', 't.updated_at',
                'cbd.name_1 as customer_name',
                'ts.resolution_status as sla_status',
                'ts.resolution_due_at as sla_due_at'
            )
            ->orderByRaw("FIELD(t.ticket_priority,'Very High','High','Medium','Low')")
            ->orderByDesc('t.updated_at')
            ->limit(6)
            ->get();
    }

    /** SLA compliance summary global */
    private function appendSlaComplianceSummary(array &$data): void
    {
        try {
            $slaTotal    = $this->visibleSla(DB::table('ticket_sla')->whereNotNull('ticket_id'))->count();
            $slaMet      = $this->visibleSla(DB::table('ticket_sla'))->where('resolution_status', 'met')->count();
            $slaBreached = $this->visibleSla(DB::table('ticket_sla'))->where('resolution_status', 'breached')->count();

            $data['sla_summary'] = [
                'total'           => $slaTotal,
                'met'             => $slaMet,
                'breached'        => $slaBreached,
                'compliance_rate' => ($slaMet + $slaBreached) > 0
                    ? round($slaMet / ($slaMet + $slaBreached) * 100, 1)
                    : null,
            ];
        } catch (\Throwable) {
            $data['sla_summary'] = null;
        }
    }

    /**
     * Batasi query ticket_sla hanya ke tiket yang visible.
     * Disalin dari DashboardController::visibleSla().
     */
    private function visibleSla($query, string $column = 'ticket_id')
    {
        return $query->whereIn($column, function ($q) {
            $q->select('ticket_id')
              ->from('ticket')
              ->whereNull('deleted_at')
              ->whereNull('is_hidden');
        });
    }
}
