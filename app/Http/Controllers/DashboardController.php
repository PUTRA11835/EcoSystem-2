<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{

    public function index(Request $request)
    {
        try {
            $user = session('user');
            $token = session('auth_token');

            Log::info('Dashboard access attempt', [
                'has_user' => $user ? 'yes' : 'no',
                'has_token' => $token ? 'yes' : 'no',
                'ip' => $request->ip()
            ]);

            if (!$user || !$token) {
                Log::warning('Dashboard accessed without valid session', [
                    'ip_address' => $request->ip(),
                ]);
                return redirect()->route('login')->withErrors(['message' => 'Please login first']);
            }

            $totalEmployees = DB::table('employee')
                ->where('is_active', true)
                ->count();
            $totalCustomers = DB::table('customer')
                ->where('is_active', true)
                ->count();
            $activeProjects = DB::table('delivery_projects')
                ->whereNotIn('status', ['completed', 'closed', 'cancel'])
                ->count();

            $totalTickets = DB::table('ticket')->whereNull('deleted_at')->count();

            $dashboardData = [
                'employee' => $totalEmployees,
                'customers' => $totalCustomers,
                'active_projects' => $activeProjects,
                'total_tickets' => $totalTickets,
                'recent_activities' => [],
            ];

            // ── EC Administrator dashboard data ───────────────────────────────
            if (($user['type'] ?? '') === 'employee' && ($user['role']['id'] ?? 0) === RoleId::EC_ADMINISTRATOR->value) {
                $base = DB::table('ticket')->whereNull('deleted_at');

                $dashboardData['ticket_stats'] = [
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

                // Ticket trend last 30 days
                $start30 = now()->subDays(29)->format('Y-m-d');
                $byDay   = DB::table('ticket')
                    ->whereNull('deleted_at')
                    ->where('start_date', '>=', $start30)
                    ->select(DB::raw('DATE(start_date) as day'), DB::raw('COUNT(*) as cnt'))
                    ->groupBy('day')
                    ->pluck('cnt', 'day')
                    ->toArray();

                $chartLabels = [];
                $chartData   = [];
                for ($i = 29; $i >= 0; $i--) {
                    $d             = now()->subDays($i)->format('Y-m-d');
                    $chartLabels[] = now()->subDays($i)->format('d M');
                    $chartData[]   = $byDay[$d] ?? 0;
                }
                $dashboardData['ticket_chart'] = ['labels' => $chartLabels, 'data' => $chartData];

                // Recent 5 tickets
                $dashboardData['recent_tickets'] = DB::table('ticket as t')
                    ->whereNull('t.deleted_at')
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

                // Team load: top 5 agents by active ticket count
                $dashboardData['team_load'] = DB::table('ticket as t')
                    ->whereNull('t.deleted_at')
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

                // Staging tickets pending validation
                $dashboardData['staging_pending'] = DB::table('staging_tickets')
                    ->where('status', 'unvalidated')
                    ->count();

                // SLA compliance summary (if table exists)
                try {
                    $slaTotal    = DB::table('ticket_sla')->whereNotNull('ticket_id')->count();
                    $slaMet      = DB::table('ticket_sla')->where('resolution_status', 'met')->count();
                    $slaBreached = DB::table('ticket_sla')->where('resolution_status', 'breached')->count();
                    $dashboardData['sla_summary'] = [
                        'total'           => $slaTotal,
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

            // ── EC User dashboard data (all tickets — same scope as ticket list) ─
            if (($user['type'] ?? '') === 'employee' && ($user['role']['id'] ?? 0) === RoleId::EC_USER->value) {
                $base = DB::table('ticket')->whereNull('deleted_at');

                $dashboardData['ticket_stats'] = [
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

                $start30 = now()->subDays(29)->format('Y-m-d');
                $byDay   = DB::table('ticket')->whereNull('deleted_at')
                    ->where('start_date', '>=', $start30)
                    ->select(DB::raw('DATE(start_date) as day'), DB::raw('COUNT(*) as cnt'))
                    ->groupBy('day')->pluck('cnt', 'day')->toArray();

                $chartLabels = []; $chartData = [];
                for ($i = 29; $i >= 0; $i--) {
                    $d = now()->subDays($i)->format('Y-m-d');
                    $chartLabels[] = now()->subDays($i)->format('d M');
                    $chartData[]   = $byDay[$d] ?? 0;
                }
                $dashboardData['ticket_chart'] = ['labels' => $chartLabels, 'data' => $chartData];

                $dashboardData['recent_tickets'] = DB::table('ticket as t')
                    ->whereNull('t.deleted_at')
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

                $dashboardData['team_load'] = DB::table('ticket as t')
                    ->whereNull('t.deleted_at')
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

                $dashboardData['staging_pending'] = DB::table('staging_tickets')
                    ->where('status', 'unvalidated')->count();

                try {
                    $slaMet      = DB::table('ticket_sla')->where('resolution_status', 'met')->count();
                    $slaBreached = DB::table('ticket_sla')->where('resolution_status', 'breached')->count();
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

            // Extra data for Delivery Support Head dashboard
            if (($user['type'] ?? '') === 'employee' && ($user['role']['id'] ?? 0) === RoleId::DELIVERY_SUPPORT_HEAD->value) {
                $base = DB::table('ticket')->whereNull('deleted_at');

                $dashboardData['ticket_stats'] = [
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

                // Chart: all tickets by start_date in last 30 days
                $start30 = now()->subDays(29)->format('Y-m-d');
                $byDay = DB::table('ticket')
                    ->whereNull('deleted_at')
                    ->where('start_date', '>=', $start30)
                    ->select(DB::raw('DATE(start_date) as day'), DB::raw('COUNT(*) as cnt'))
                    ->groupBy('day')
                    ->pluck('cnt', 'day')
                    ->toArray();

                $chartLabels = [];
                $chartData   = [];
                for ($i = 29; $i >= 0; $i--) {
                    $d = now()->subDays($i)->format('Y-m-d');
                    $chartLabels[] = now()->subDays($i)->format('d M');
                    $chartData[]   = $byDay[$d] ?? 0;
                }
                $dashboardData['ticket_chart'] = ['labels' => $chartLabels, 'data' => $chartData];

                // Recent 8 tickets (all) — includes priority for dashboard display
                $dashboardData['recent_tickets'] = DB::table('ticket as t')
                    ->whereNull('t.deleted_at')
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

                // Team load: top 6 employees by active ticket count
                $dashboardData['team_load'] = DB::table('ticket as t')
                    ->whereNull('t.deleted_at')
                    ->whereNotIn('t.status', ['closed', 'cancelled'])
                    ->join('employee as e', 't.ticket_lead_id', '=', 'e.employee_id')
                    ->leftJoin('employee_basic_data as ebd', 'e.employee_id', '=', 'ebd.employee_id')
                    ->select('e.employee_id', DB::raw("TRIM(CONCAT(COALESCE(ebd.first_name,''),' ',COALESCE(ebd.last_name,''))) as name"), DB::raw('COUNT(*) as open_count'))
                    ->groupBy('e.employee_id', 'ebd.first_name', 'ebd.last_name')
                    ->orderByDesc('open_count')
                    ->limit(6)
                    ->get();

                // Timesheets pending approval (submitted by team)
                try {
                    $dashboardData['timesheet_pending'] = DB::table('timesheet')
                        ->where('status', 'submitted')
                        ->count();
                } catch (\Throwable) {
                    $dashboardData['timesheet_pending'] = 0;
                }

                // SLA compliance summary
                try {
                    $slaMet      = DB::table('ticket_sla')->where('resolution_status', 'met')->count();
                    $slaBreached = DB::table('ticket_sla')->where('resolution_status', 'breached')->count();
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

            // ── Helpdesk dashboard data ───────────────────────────────────────
            if (($user['type'] ?? '') === 'employee' && ($user['role']['id'] ?? 0) === RoleId::DELIVERY_HELPDESK->value) {
                $base = DB::table('ticket')->whereNull('deleted_at');

                $dashboardData['ticket_stats'] = [
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

                // Unassigned active tickets
                $dashboardData['unassigned_count'] = (clone $base)
                    ->whereNull('ticket_lead_id')
                    ->whereNotIn('status', ['closed', 'cancelled'])
                    ->count();

                // Staging pending validation
                $dashboardData['staging_pending'] = DB::table('staging_tickets')
                    ->where('status', 'unvalidated')
                    ->count();

                // SLA metrics
                try {
                    $dashboardData['sla_breached'] = DB::table('ticket_sla')
                        ->whereNotNull('ticket_id')
                        ->where('resolution_status', 'breached')
                        ->count();

                    $dashboardData['sla_warning'] = DB::table('ticket_sla as ts')
                        ->whereNotNull('ts.ticket_id')
                        ->whereIn('ts.resolution_status', ['pending', 'paused'])
                        ->whereNotNull('ts.resolution_due_at')
                        ->where('ts.resolution_due_at', '>', now())
                        ->where('ts.resolution_due_at', '<=', now()->addHours(4))
                        ->count();

                    $dashboardData['sla_compliance'] = (function () {
                        $met      = DB::table('ticket_sla')->where('resolution_status', 'met')->count();
                        $breached = DB::table('ticket_sla')->where('resolution_status', 'breached')->count();
                        return ($met + $breached) > 0 ? round($met / ($met + $breached) * 100, 1) : null;
                    })();
                } catch (\Throwable) {
                    $dashboardData['sla_breached']    = 0;
                    $dashboardData['sla_warning']     = 0;
                    $dashboardData['sla_compliance']  = null;
                }

                // Very high priority active
                $dashboardData['very_high_count'] = (clone $base)
                    ->where('ticket_priority', 'Very High')
                    ->whereNotIn('status', ['closed', 'cancelled'])
                    ->count();

                // Priority breakdown (active only)
                $dashboardData['priority_breakdown'] = (clone $base)
                    ->whereNotIn('status', ['closed', 'cancelled'])
                    ->select('ticket_priority', DB::raw('COUNT(*) as cnt'))
                    ->groupBy('ticket_priority')
                    ->pluck('cnt', 'ticket_priority')
                    ->toArray();

                // 30-day ticket trend
                $start30 = now()->subDays(29)->format('Y-m-d');
                $byDay   = DB::table('ticket')
                    ->whereNull('deleted_at')
                    ->where('start_date', '>=', $start30)
                    ->select(DB::raw('DATE(start_date) as day'), DB::raw('COUNT(*) as cnt'))
                    ->groupBy('day')
                    ->pluck('cnt', 'day')
                    ->toArray();

                $chartLabels = [];
                $chartData   = [];
                for ($i = 29; $i >= 0; $i--) {
                    $d             = now()->subDays($i)->format('Y-m-d');
                    $chartLabels[] = now()->subDays($i)->format('d M');
                    $chartData[]   = $byDay[$d] ?? 0;
                }
                $dashboardData['ticket_chart'] = ['labels' => $chartLabels, 'data' => $chartData];

                // Recent tickets (8)
                $dashboardData['recent_tickets'] = DB::table('ticket as t')
                    ->whereNull('t.deleted_at')
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

                // Urgent tickets: Very High OR SLA breached, still active
                $dashboardData['urgent_tickets'] = DB::table('ticket as t')
                    ->whereNull('t.deleted_at')
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

            // ── Delivery Support Manager dashboard data ───────────────────────
            // Scope: tiket dari delivery yang dia kelola + tiket belum di-assign
            // (konsisten dengan TicketController::index untuk role ini)
            if (($user['type'] ?? '') === 'employee' && ($user['role']['id'] ?? 0) === RoleId::DELIVERY_SUPPORT_MANAGER->value) {
                $employeeId = $user['id'];

                $managedDeliveryIds = DB::table('delivery_support')
                    ->where('support_manager_id', $employeeId)
                    ->pluck('id');

                $managedTicketIds = DB::table('delivery_support_activities')
                    ->whereIn('delivery_support_id', $managedDeliveryIds)
                    ->whereNotNull('ticket_id')
                    ->pluck('ticket_id')
                    ->unique()
                    ->values();

                // Base: tiket yang dia kelola ATAU belum di-assign (belum closed/cancelled)
                $base = DB::table('ticket')
                    ->whereNull('deleted_at')
                    ->where(function ($q) use ($managedTicketIds) {
                        $q->whereIn('ticket_id', $managedTicketIds)
                          ->orWhereNull('ticket_lead_id');
                    });

                $dashboardData['ticket_stats'] = [
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

                // 30-day ticket trend (dalam scope tiket yang dia kelola + unassigned)
                $start30 = now()->subDays(29)->format('Y-m-d');
                $byDay   = (clone $base)
                    ->where('start_date', '>=', $start30)
                    ->select(DB::raw('DATE(start_date) as day'), DB::raw('COUNT(*) as cnt'))
                    ->groupBy('day')
                    ->pluck('cnt', 'day')
                    ->toArray();

                $chartLabels = [];
                $chartData   = [];
                for ($i = 29; $i >= 0; $i--) {
                    $d             = now()->subDays($i)->format('Y-m-d');
                    $chartLabels[] = now()->subDays($i)->format('d M');
                    $chartData[]   = $byDay[$d] ?? 0;
                }
                $dashboardData['ticket_chart'] = ['labels' => $chartLabels, 'data' => $chartData];

                // Agent workload: hanya agent pada tiket yang dia kelola
                $dashboardData['team_load'] = DB::table('ticket as t')
                    ->whereNull('t.deleted_at')
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

                // SLA metrics: hanya untuk tiket dalam scope
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
                    $dashboardData['sla_warning'] = DB::table('ticket_sla as ts')
                        ->whereIn('ts.ticket_id', $scopeIds)
                        ->whereIn('ts.resolution_status', ['pending', 'paused'])
                        ->whereNotNull('ts.resolution_due_at')
                        ->where('ts.resolution_due_at', '>', now())
                        ->where('ts.resolution_due_at', '<=', now()->addHours(4))
                        ->count();
                } catch (\Throwable) {
                    $dashboardData['sla_summary'] = null;
                    $dashboardData['sla_warning'] = 0;
                }

                // Recent 8 tickets dengan SLA info (dalam scope)
                $dashboardData['recent_tickets'] = DB::table('ticket as t')
                    ->whereNull('t.deleted_at')
                    ->where(function ($q) use ($managedTicketIds) {
                        $q->whereIn('t.ticket_id', $managedTicketIds)
                          ->orWhereNull('t.ticket_lead_id');
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

                // Urgent: Very High atau SLA breached, aktif, dalam scope
                $dashboardData['urgent_tickets'] = DB::table('ticket as t')
                    ->whereNull('t.deleted_at')
                    ->whereNotIn('t.status', ['closed', 'cancelled'])
                    ->where(function ($q) use ($managedTicketIds) {
                        $q->whereIn('t.ticket_id', $managedTicketIds)
                          ->orWhereNull('t.ticket_lead_id');
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

            // ── Delivery Support User dashboard data ──────────────────────────
            if (($user['type'] ?? '') === 'employee' && ($user['role']['id'] ?? 0) === RoleId::DELIVERY_SUPPORT_USER->value) {
                $employeeId = $user['id'];

                $picIds    = DB::table('ticket')->whereNull('deleted_at')->where('ticket_lead_id', $employeeId)->pluck('ticket_id');
                $memberIds = DB::table('ticket_member')->where('employee_id', $employeeId)->pluck('ticket_id');
                $ticketIds = $picIds->merge($memberIds)->unique()->values();

                $base      = DB::table('ticket')->whereNull('deleted_at')->whereIn('ticket_id', $ticketIds);
                $activeIds = (clone $base)->whereNotIn('status', ['closed', 'cancelled'])->pluck('ticket_id');

                $dashboardData['ticket_stats'] = [
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

                // 30-day trend
                $start30 = now()->subDays(29)->format('Y-m-d');
                $byDay   = DB::table('ticket')->whereNull('deleted_at')
                    ->whereIn('ticket_id', $ticketIds)
                    ->where('start_date', '>=', $start30)
                    ->select(DB::raw('DATE(start_date) as day'), DB::raw('COUNT(*) as cnt'))
                    ->groupBy('day')->pluck('cnt', 'day')->toArray();

                $chartLabels = []; $chartData = [];
                for ($i = 29; $i >= 0; $i--) {
                    $d = now()->subDays($i)->format('Y-m-d');
                    $chartLabels[] = now()->subDays($i)->format('d M');
                    $chartData[]   = $byDay[$d] ?? 0;
                }
                $dashboardData['ticket_chart'] = ['labels' => $chartLabels, 'data' => $chartData];

                // Recent tickets with SLA & priority
                $dashboardData['recent_tickets'] = DB::table('ticket as t')
                    ->whereNull('t.deleted_at')
                    ->whereIn('t.ticket_id', $ticketIds)
                    ->leftJoin('customer as c', 't.customer_id', '=', 'c.customer_id')
                    ->leftJoin('customer_basic_data as cbd', 'c.customer_id', '=', 'cbd.customer_id')
                    ->leftJoin('ticket_sla as ts', 't.ticket_id', '=', 'ts.ticket_id')
                    ->select(
                        't.ticket_id', 't.ticket_number', 't.description',
                        't.status', 't.ticket_priority', 't.updated_at', 't.created_at',
                        'cbd.name_1 as customer_name',
                        DB::raw("IF(t.ticket_lead_id = {$employeeId}, 1, 0) as is_pic"),
                        'ts.resolution_status as sla_status',
                        'ts.resolution_due_at as sla_due_at',
                        'ts.ball_holder'
                    )
                    ->orderByDesc('t.updated_at')
                    ->limit(8)
                    ->get();

                // Urgent: Very High or SLA breached, active
                $dashboardData['urgent_tickets'] = DB::table('ticket as t')
                    ->whereNull('t.deleted_at')
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

            Log::info('Dashboard accessed successfully', [
                'user_id' => $user['id'],
                'user_name' => $user['name'] ?? $user['company_name'] ?? 'Unknown',
                'user_type' => $user['type'],
                'ip_address' => $request->ip(),
            ]);

            return view('home.home', [
                'user' => $user,
                'data' => $dashboardData,
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard error', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine(),
                'ip_address' => $request->ip(),
            ]);

            abort(500, 'Dashboard error: ' . $e->getMessage());
        }
    }
}
