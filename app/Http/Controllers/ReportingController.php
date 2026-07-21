<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use App\Exports\MdRecapExport;
use App\Exports\ResolutionDaysExport;
use App\Exports\TicketByModuleExport;
use App\Exports\TimesheetReportExport;
use App\Models\ConsultantMandays;
use App\Models\ConsultantMandaysDetail;
use App\Models\CustomerMandays;
use App\Models\ReportingPeriod;
use App\Models\Ticket;
use App\Services\PeriodService;
use App\Support\SessionUser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ReportingController extends Controller
{
    // ── Web: Reporting page ───────────────────────────────────────────────

    public function index()
    {
        $user = SessionUser::fromSession(session('user'));
        if (!$user) {
            return redirect()->route('login');
        }

        return view('reporting.reporting', ['user' => $user]);
    }

    // ── API: Current period info ──────────────────────────────────────────

    public function currentPeriod()
    {
        try {
            // The globally-open period (as RPMO's period management sees it) — not
            // just "whatever month today's date falls in". RPMO can reopen an older
            // period (e.g. for late corrections) while the calendar-current month has
            // never been opened; the badge must reflect the former, not the latter.
            $active = ReportingPeriod::getActive();

            if (!$active) {
                return response()->json([
                    'success' => true,
                    'data'    => [
                        'year'       => null,
                        'month'      => null,
                        'status'     => 'not_open',
                        'is_closed'  => false,
                        'closed_at'  => null,
                        'start_date' => null,
                        'end_date'   => null,
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'year'       => $active->year,
                    'month'      => $active->month,
                    'status'     => 'open',
                    'is_closed'  => false,
                    'closed_at'  => null,
                    'start_date' => $active->start_date?->format('Y-m-d'),
                    'end_date'   => $active->end_date?->format('Y-m-d'),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('currentPeriod error');
            return response()->json(['success' => false, 'message' => 'Failed to retrieve current period data. Please try again.'], 500);
        }
    }

    // ── API: Close period (legacy endpoint — delegates to PeriodService) ─────

    public function closePeriod(Request $request)
    {
        try {
            $sessionUser = SessionUser::fromSession(session('user'));
            $employeeId  = $sessionUser->id;

            // Only RPMO can close globally via the new system
            if (!$sessionUser->hasRole(RoleId::DELIVERY_RPMO_HEAD->value)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Period closing is now managed from the Period Management page (RPMO only).',
                ], 403);
            }

            $period = ReportingPeriod::getActive();
            if (!$period) {
                return response()->json(['success' => false, 'message' => 'No active period found.'], 422);
            }

            /** @var PeriodService $svc */
            $svc = app(PeriodService::class);
            $svc->closeGlobal($period, $employeeId, RoleId::DELIVERY_RPMO_HEAD->value);

            return response()->json([
                'success' => true,
                'message' => 'Period ' . $period->getLabel() . ' has been closed.',
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('closePeriod error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to close the period. Please try again.'], 500);
        }
    }

    // ── API: Timesheet support report ─────────────────────────────────────

    public function timesheetSupport(Request $request)
    {
        try {
            $sessionUser       = SessionUser::fromSession(session('user'));
            $currentEmployeeId = $sessionUser->id;

            // One row per individual timesheet entry (no GROUP BY)
            $query = DB::table('timesheets')
                ->join('ticket',              'timesheets.ticket_id',   '=', 'ticket.ticket_id')
                ->join('employee',            'timesheets.employee_id', '=', 'employee.employee_id')
                ->join('employee_basic_data', 'employee.employee_id',   '=', 'employee_basic_data.employee_id')
                ->leftJoin('customer',            'ticket.customer_id',   '=', 'customer.customer_id')
                ->leftJoin('customer_basic_data', 'customer.customer_id', '=', 'customer_basic_data.customer_id')
                ->whereIn('timesheets.status', ['draft', 'submitted', 'approved'])
                ->whereNotNull('timesheets.ticket_id')
                ->whereNull('timesheets.deleted_at')
                ->select(
                    'timesheets.id',
                    'timesheets.employee_id',
                    'timesheets.status',
                    DB::raw("TRIM(CONCAT(COALESCE(employee_basic_data.first_name,''), ' ', COALESCE(employee_basic_data.last_name,''))) as employee_name"),
                    'timesheets.ticket_id',
                    'ticket.ticket_number',
                    DB::raw("COALESCE(customer_basic_data.name_1, '') as customer_name"),
                    'timesheets.date',
                    DB::raw('COALESCE(timesheets.md_consumed, 0) as md_consumed')
                );

            $allowed = [RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_SUPPORT_HEAD->value];
            if (!$sessionUser->hasAnyRole($allowed)) {
                $query->where('timesheets.employee_id', $currentEmployeeId);
            }

            if ($request->filled('start_date') && $request->filled('end_date')) {
                $query->whereBetween('timesheets.date', [$request->start_date, $request->end_date]);
            }

            // Sort ASC for correct running total accumulation, then reverse for display (newest first)
            $rows = $query->orderBy('timesheets.date')->orderBy('timesheets.id')->get();

            // Per-employee quota from the latest approved consultant mandays detail
            $ticketIds = $rows->pluck('ticket_id')->unique()->values();
            $jatahMap  = []; // keyed as "ticketId_employeeId"
            if ($ticketIds->isNotEmpty()) {
                $latestCMs = ConsultantMandays::whereIn('ticket_id', $ticketIds)
                    ->where('status', 'approved')
                    ->orderBy('approved_at', 'desc')
                    ->get()
                    ->groupBy('ticket_id')
                    ->map(fn($g) => $g->first());

                $cmIdToTicketId = $latestCMs->mapWithKeys(fn($cm) => [$cm->id => $cm->ticket_id]);

                ConsultantMandaysDetail::whereIn('consultant_mandays_id', $cmIdToTicketId->keys())
                    ->get()
                    ->each(function ($detail) use (&$jatahMap, $cmIdToTicketId) {
                        $ticketId = $cmIdToTicketId[$detail->consultant_mandays_id] ?? null;
                        if ($ticketId) {
                            $jatahMap[$ticketId . '_' . $detail->employee_id] =
                                round((float)$detail->mandays + (float)($detail->approved_additional ?? 0), 2);
                        }
                    });
            }

            // Pre-load consumed from BEFORE the date range so the running total carries over
            // from previous periods (cumulative quota, not per-period reset).
            $preBase = [];
            if ($request->filled('start_date') && $rows->isNotEmpty()) {
                $ticketEmpPairs = $rows->map(fn($r) => ['t' => $r->ticket_id, 'e' => $r->employee_id])->unique()->values();
                $ticketIds2     = $ticketEmpPairs->pluck('t')->unique()->toArray();
                $empIds2        = $ticketEmpPairs->pluck('e')->unique()->toArray();

                DB::table('timesheets')
                    ->whereIn('ticket_id', $ticketIds2)
                    ->whereIn('employee_id', $empIds2)
                    ->whereIn('status', ['draft', 'submitted', 'approved'])
                    ->whereNull('deleted_at')
                    ->where('date', '<', $request->start_date)
                    ->select('ticket_id', 'employee_id', DB::raw('SUM(md_consumed) as base'))
                    ->groupBy('ticket_id', 'employee_id')
                    ->get()
                    ->each(function ($r) use (&$preBase) {
                        $preBase[$r->ticket_id . '_' . $r->employee_id] = (float) $r->base;
                    });
            }

            // Running cumulative MD consumed per employee per ticket (rows sorted asc for correct accumulation)
            $runningTotals = $preBase;
            $data = $rows->map(function ($row) use ($jatahMap, &$runningTotals) {
                $jatahMd    = $jatahMap[$row->ticket_id . '_' . $row->employee_id] ?? null;
                $mdConsumed = (float) $row->md_consumed;

                $key = $row->ticket_id . '_' . $row->employee_id;
                $runningTotals[$key] = ($runningTotals[$key] ?? 0) + $mdConsumed;
                $cumulative = $runningTotals[$key];

                $remain = $jatahMd !== null ? round($jatahMd - $cumulative, 2) : null;

                if ($jatahMd === null)           $mdStatus = null;
                elseif ($cumulative == $jatahMd) $mdStatus = 'Match';
                elseif ($cumulative > $jatahMd)  $mdStatus = 'Over';
                else                             $mdStatus = 'Less';

                return [
                    'id'            => $row->id,
                    'employee_id'   => $row->employee_id,
                    'employee_name' => trim($row->employee_name),
                    'ticket_id'     => $row->ticket_id,
                    'ticket_number' => $row->ticket_number,
                    'customer_name' => $row->customer_name,
                    'date'          => $row->date,
                    'timesheet_status' => $row->status,
                    'md_consumed'   => $mdConsumed,
                    'jatah_md'      => $jatahMd,
                    'remain'        => $remain,
                    'status'        => $mdStatus,
                ];
            })->reverse()->values(); // newest first for display

            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {
            Log::error('Reporting timesheetSupport');
            return response()->json(['success' => false, 'message' => 'Failed to retrieve the timesheet support report. Please try again.'], 500);
        }
    }

    // ── Web: Export Excel ─────────────────────────────────────────────────

    public function exportExcel(Request $request)
    {
        try {
            $sessionUser = SessionUser::fromSession(session('user'));
            $allowed     = [RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_SUPPORT_HEAD->value];

            if (!$sessionUser->hasAnyRole($allowed)) {
                abort(403, 'Access denied. Only Admins and Head of Support can export reports.');
            }

            // Column filters passed from the browser view
            $filterEmployee = trim($request->input('employee', ''));
            $filterTicket   = trim($request->input('ticket',   ''));
            $filterCustomer = trim($request->input('customer', ''));
            $filterApproval = trim($request->input('approval', ''));
            $filterMdStatus = trim($request->input('md_status', ''));

            $query = DB::table('timesheets')
                ->join('ticket',              'timesheets.ticket_id',   '=', 'ticket.ticket_id')
                ->join('employee',            'timesheets.employee_id', '=', 'employee.employee_id')
                ->join('employee_basic_data', 'employee.employee_id',   '=', 'employee_basic_data.employee_id')
                ->leftJoin('customer',            'ticket.customer_id',   '=', 'customer.customer_id')
                ->leftJoin('customer_basic_data', 'customer.customer_id', '=', 'customer_basic_data.customer_id')
                ->whereIn('timesheets.status', ['draft', 'submitted', 'approved'])
                ->whereNotNull('timesheets.ticket_id')
                ->whereNull('timesheets.deleted_at');

            if ($filterEmployee !== '') {
                $query->whereRaw(
                    "LOWER(TRIM(CONCAT(COALESCE(employee_basic_data.first_name,''), ' ', COALESCE(employee_basic_data.last_name,'')))) LIKE ?",
                    ['%' . strtolower($filterEmployee) . '%']
                );
            }
            if ($filterTicket !== '') {
                $query->where('ticket.ticket_number', 'LIKE', '%' . $filterTicket . '%');
            }
            if ($filterCustomer !== '') {
                $query->where('customer_basic_data.name_1', 'LIKE', '%' . $filterCustomer . '%');
            }
            if ($filterApproval !== '') {
                $query->where('timesheets.status', $filterApproval);
            }

            $rows = $query
                ->select(
                    'timesheets.id',
                    'timesheets.employee_id',
                    'timesheets.status as timesheet_status',
                    DB::raw("TRIM(CONCAT(COALESCE(employee_basic_data.first_name,''), ' ', COALESCE(employee_basic_data.last_name,''))) as employee_name"),
                    'timesheets.ticket_id',
                    'ticket.ticket_number',
                    DB::raw("COALESCE(customer_basic_data.name_1, '') as customer_name"),
                    'timesheets.date',
                    'timesheets.md_consumed',
                    'timesheets.period_year',
                    'timesheets.period_month'
                )
                ->orderBy('timesheets.date')
                ->orderBy('timesheets.id')
                ->get();

            // Batch-fetch per-employee quota from latest approved consultant mandays detail
            $ticketIds = $rows->pluck('ticket_id')->unique()->values();
            $jatahMap  = []; // keyed as "ticketId_employeeId"
            if ($ticketIds->isNotEmpty()) {
                $latestCMs = ConsultantMandays::whereIn('ticket_id', $ticketIds)
                    ->where('status', 'approved')
                    ->orderBy('approved_at', 'desc')
                    ->get()
                    ->groupBy('ticket_id')
                    ->map(fn($g) => $g->first());

                $cmIdToTicketId = $latestCMs->mapWithKeys(fn($cm) => [$cm->id => $cm->ticket_id]);

                ConsultantMandaysDetail::whereIn('consultant_mandays_id', $cmIdToTicketId->keys())
                    ->get()
                    ->each(function ($detail) use (&$jatahMap, $cmIdToTicketId) {
                        $ticketId = $cmIdToTicketId[$detail->consultant_mandays_id] ?? null;
                        if ($ticketId) {
                            $jatahMap[$ticketId . '_' . $detail->employee_id] =
                                round((float)$detail->mandays + (float)($detail->approved_additional ?? 0), 2);
                        }
                    });
            }

            // Calculate running totals ASC (chronological), then reverse to newest-first
            $runningTotals = [];
            $exportRows = $rows->map(function ($r) use ($jatahMap, &$runningTotals) {
                $jatahMd    = $jatahMap[$r->ticket_id . '_' . $r->employee_id] ?? null;
                $mdConsumed = (float) ($r->md_consumed ?? 0);

                $key = $r->ticket_id . '_' . $r->employee_id;
                $runningTotals[$key] = ($runningTotals[$key] ?? 0) + $mdConsumed;
                $cumulative = $runningTotals[$key];

                if ($jatahMd === null)           $mdStatus = null;
                elseif ($cumulative == $jatahMd) $mdStatus = 'Match';
                elseif ($cumulative > $jatahMd)  $mdStatus = 'Over';
                else                             $mdStatus = 'Less';

                // Period: use stored override if set, else compute from date
                if ($r->period_year && $r->period_month) {
                    $pYear  = $r->period_year;
                    $pMonth = $r->period_month;
                } else {
                    $p      = ReportingPeriod::periodFor(\Carbon\Carbon::parse($r->date));
                    $pYear  = $p['year'];
                    $pMonth = $p['month'];
                }

                return [
                    'ticket_number' => $r->ticket_number,
                    'employee_name' => trim($r->employee_name),
                    'period_month'  => $pMonth,
                    'period_year'   => $pYear,
                    'jatah_md'      => $jatahMd,
                    'md_consumed'   => $mdConsumed,
                    'status'        => $mdStatus,
                ];
            });

            // Apply md_status filter (computed field — must filter after running totals)
            if ($filterMdStatus !== '') {
                $exportRows = $exportRows->filter(fn($r) => $r['status'] === $filterMdStatus);
            }

            $exportRows = $exportRows->reverse()->values(); // newest first, matching view order

            $filename = 'MD_Validation_Export_' . now()->format('Y-m-d') . '.xlsx';

            return Excel::download(new TimesheetReportExport(collect($exportRows)), $filename);

        } catch (\Exception $e) {
            Log::error('exportExcel error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to generate the Excel export. Please try again.'], 500);
        }
    }

    // ── Web: MD Recap page ────────────────────────────────────────────────

    public function mdRecapIndex()
    {
        $sessionUser = session('user');
        if (!$sessionUser) return redirect()->route('login');

        $roleIds = array_map('intval', $sessionUser['role_ids'] ?? [$sessionUser['role']['id'] ?? 0]);
        $allowed = [RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_SUPPORT_HEAD->value];
        if (empty(array_intersect($roleIds, $allowed))) {
            abort(403, 'Access denied. Only Admins and Head of Support can view the MD recap.');
        }

        return view('reporting.md-recap', ['user' => $sessionUser]);
    }

    // ── API: MD Recap data ────────────────────────────────────────────────

    public function mdRecap(Request $request)
    {
        try {
            $sessionUser   = session('user');
            $currentRoleIds = array_map('intval', $sessionUser['role_ids'] ?? [$sessionUser['role']['id'] ?? 0]);
            $allowed        = [RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_SUPPORT_HEAD->value];

            if (empty(array_intersect($currentRoleIds, $allowed))) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $query = DB::table('timesheets')
                ->join('employee',            'timesheets.employee_id', '=', 'employee.employee_id')
                ->join('employee_basic_data', 'employee.employee_id',   '=', 'employee_basic_data.employee_id')
                ->where('timesheets.status', 'approved')
                ->whereNull('timesheets.deleted_at');

            $fMonth = (int) $request->input('month', 0);
            $fYear  = (int) $request->input('year',  0);
            if ($fMonth && $fYear) {
                $range = ReportingPeriod::dateRange($fYear, $fMonth);
                $query->whereBetween('timesheets.date', [
                    $range['start']->format('Y-m-d'),
                    $range['end']->format('Y-m-d'),
                ]);
            } elseif ($fMonth) {
                $query->whereMonth('timesheets.date', $fMonth);
            } elseif ($fYear) {
                $query->whereYear('timesheets.date', $fYear);
            }

            $rows = $query
                ->select(
                    'timesheets.id',
                    'timesheets.date',
                    DB::raw("TRIM(CONCAT(COALESCE(employee_basic_data.first_name,''), ' ', COALESCE(employee_basic_data.last_name,''))) as employee_name"),
                    DB::raw("CASE WHEN LOWER(timesheets.presence) = 'onsite' THEN 'OnSite' ELSE 'Remote' END as mode"),
                    DB::raw('COALESCE(timesheets.md_consumed, timesheets.duration_minutes / 480.0, 0) as mandays')
                )
                ->orderByRaw('employee_name')
                ->orderBy('timesheets.date')
                ->get();

            $data = $rows->map(fn($r) => [
                'id'      => $r->id,
                'name'    => trim($r->employee_name),
                'date'    => $r->date,
                'mode'    => $r->mode,
                'mandays' => round((float) $r->mandays, 2),
            ]);

            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {
            Log::error('mdRecap error');
            return response()->json(['success' => false, 'message' => 'Failed to retrieve MD recap data. Please try again.'], 500);
        }
    }

    // ── Web: MD Recap export ──────────────────────────────────────────────

    public function exportMdRecap(Request $request)
    {
        try {
            $sessionUser   = session('user');
            $currentRoleIds = array_map('intval', $sessionUser['role_ids'] ?? [$sessionUser['role']['id'] ?? 0]);
            $allowed        = [RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_SUPPORT_HEAD->value];

            if (empty(array_intersect($currentRoleIds, $allowed))) {
                abort(403, 'Access denied. Only Admins and Head of Support can export the MD recap.');
            }

            $filterName  = trim($request->input('name', ''));
            $filterMode  = trim($request->input('mode', ''));
            $filterMonth = (int) $request->input('month', 0);
            $filterYear  = (int) $request->input('year',  0);

            $query = DB::table('timesheets')
                ->join('employee',            'timesheets.employee_id', '=', 'employee.employee_id')
                ->join('employee_basic_data', 'employee.employee_id',   '=', 'employee_basic_data.employee_id')
                ->where('timesheets.status', 'approved')
                ->whereNull('timesheets.deleted_at');

            if ($filterMonth && $filterYear) {
                $range = ReportingPeriod::dateRange($filterYear, $filterMonth);
                $query->whereBetween('timesheets.date', [
                    $range['start']->format('Y-m-d'),
                    $range['end']->format('Y-m-d'),
                ]);
            } elseif ($filterMonth) {
                $query->whereMonth('timesheets.date', $filterMonth);
            } elseif ($filterYear) {
                $query->whereYear('timesheets.date', $filterYear);
            }
            if ($filterName !== '') {
                $query->whereRaw(
                    "LOWER(TRIM(CONCAT(COALESCE(employee_basic_data.first_name,''), ' ', COALESCE(employee_basic_data.last_name,'')))) LIKE ?",
                    ['%' . strtolower($filterName) . '%']
                );
            }
            if ($filterMode !== '') {
                $query->whereRaw("CASE WHEN LOWER(timesheets.presence) = 'onsite' THEN 'OnSite' ELSE 'Remote' END = ?", [$filterMode]);
            }

            $rows = $query
                ->select(
                    'timesheets.date',
                    DB::raw("TRIM(CONCAT(COALESCE(employee_basic_data.first_name,''), ' ', COALESCE(employee_basic_data.last_name,''))) as employee_name"),
                    DB::raw("CASE WHEN LOWER(timesheets.presence) = 'onsite' THEN 'OnSite' ELSE 'Remote' END as mode"),
                    DB::raw('COALESCE(timesheets.md_consumed, timesheets.duration_minutes / 480.0, 0) as mandays')
                )
                ->orderByRaw('employee_name')
                ->orderBy('timesheets.date')
                ->get();

            // Aggregate: same employee + same mode → one merged row
            $exportRows = $rows
                ->groupBy(fn($r) => trim($r->employee_name) . '||' . $r->mode)
                ->map(fn($group) => [
                    'name'    => trim($group->first()->employee_name),
                    'mode'    => $group->first()->mode,
                    'entries' => $group->count(),
                    'mandays' => round((float) $group->sum(fn($r) => (float) $r->mandays), 2),
                ])
                ->sortBy([['name', 'asc'], ['mode', 'asc']])
                ->values();

            $periodSuffix = ($filterMonth && $filterYear)
                ? '_' . $filterYear . '-' . str_pad($filterMonth, 2, '0', STR_PAD_LEFT)
                : '_' . now()->format('Y-m-d');
            $filename = 'MD_Recap_Export' . $periodSuffix . '.xlsx';

            return Excel::download(new MdRecapExport(collect($exportRows)), $filename);

        } catch (\Exception $e) {
            Log::error('exportMdRecap error');
            abort(500, $e->getMessage());
        }
    }

    // ── Export Resolution Days ────────────────────────────────────────────

    public function exportResolutionDays(Request $request)
    {
        try {
            $sessionUser   = session('user');
            $currentRoleId = isset($sessionUser['role']['id']) ? (int) $sessionUser['role']['id'] : null;

            if (!in_array($currentRoleId, [RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_SUPPORT_HEAD->value])) {
                abort(403, 'Access denied.');
            }

            $filterMonth = (int) $request->input('month', 0);
            $filterYear  = (int) $request->input('year',  0);

            $query = DB::table('consultant_mandays_detail as cmd')
                ->join('consultant_mandays as cm',       'cmd.consultant_mandays_id', '=', 'cm.id')
                ->join('ticket',                         'cm.ticket_id',              '=', 'ticket.ticket_id')
                ->join('employee',                       'cmd.employee_id',           '=', 'employee.employee_id')
                ->leftJoin('employee_basic_data as ebd', 'employee.employee_id',      '=', 'ebd.employee_id')
                ->whereNull('ticket.deleted_at');

            if ($filterMonth || $filterYear) {
                $tsQuery = DB::table('timesheets')
                    ->where('status', 'approved')
                    ->whereNull('deleted_at')
                    ->whereNotNull('ticket_id');
                if ($filterMonth && $filterYear) {
                    $range = ReportingPeriod::dateRange($filterYear, $filterMonth);
                    $tsQuery->whereBetween('date', [
                        $range['start']->format('Y-m-d'),
                        $range['end']->format('Y-m-d'),
                    ]);
                } elseif ($filterMonth) {
                    $tsQuery->whereMonth('date', $filterMonth);
                } elseif ($filterYear) {
                    $tsQuery->whereYear('date', $filterYear);
                }
                $query->whereIn('cm.ticket_id', $tsQuery->pluck('ticket_id'));
            }

            $rows = $query->select(
                    'ticket.ticket_number',
                    'employee.eci as employee_eci',
                    DB::raw("TRIM(CONCAT(COALESCE(ebd.first_name,''), ' ', COALESCE(ebd.last_name,''))) as full_name"),
                    'cmd.mandays',
                    'cmd.additional_mandays',
                    'cmd.notes',
                    'cmd.approved_additional'
                )
                ->orderBy('ticket.ticket_number')
                ->orderBy('employee.eci')
                ->get();

            $exportRows = $rows->map(fn($r) => [
                'ticket_number'   => $r->ticket_number,
                'employee_eci'    => $r->employee_eci,
                'name'            => trim($r->full_name),
                'resolution_days' => (float) $r->mandays,
                'additional_days' => (float) $r->additional_mandays,
                'note'            => $r->notes ?? '',
                'approve_add'     => (float) $r->approved_additional,
                'total'           => round((float) $r->mandays + (float) $r->approved_additional, 2),
            ]);

            if ($filterMonth && $filterYear) {
                $periodSuffix = '_' . $filterYear . '-' . str_pad($filterMonth, 2, '0', STR_PAD_LEFT);
            } elseif ($filterMonth) {
                $periodSuffix = '_month-' . str_pad($filterMonth, 2, '0', STR_PAD_LEFT);
            } elseif ($filterYear) {
                $periodSuffix = '_' . $filterYear;
            } else {
                $periodSuffix = '_' . now()->format('Y-m-d');
            }
            $filename = 'Resolution_Days_Export' . $periodSuffix . '.xlsx';

            return Excel::download(new ResolutionDaysExport($exportRows), $filename);

        } catch (\Exception $e) {
            Log::error('exportResolutionDays error', ['msg' => $e->getMessage()]);
            abort(500, $e->getMessage());
        }
    }

    // ── Web: Resolution Days (unapproved) page ─────────────────────────────

    public function resolutionDaysIndex()
    {
        $sessionUser = session('user');
        if (!$sessionUser) return redirect()->route('login');

        $roleIds = array_map('intval', $sessionUser['role_ids'] ?? [$sessionUser['role']['id'] ?? 0]);
        $allowed = [RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_SUPPORT_HEAD->value];
        if (empty(array_intersect($roleIds, $allowed))) {
            abort(403, 'Access denied. Only Admins and Head of Support can view Resolution Days.');
        }

        return view('reporting.resolution-days', ['user' => $sessionUser]);
    }

    // ── API: Resolution Days (unapproved) list ─────────────────────────────
    // Tickets whose resolution_days_status is 'none' (never proposed) or
    // 'pending_head' (submitted, awaiting Head approval) — i.e. everything
    // that isn't approved/rejected/draft yet, across all tickets company-wide.

    public function resolutionDays(Request $request)
    {
        try {
            $sessionUser = session('user');
            $roleIds     = array_map('intval', $sessionUser['role_ids'] ?? [$sessionUser['role']['id'] ?? 0]);
            $allowed     = [RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_SUPPORT_HEAD->value];

            if (empty(array_intersect($roleIds, $allowed))) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $tickets = Ticket::with(['customer.basicData', 'ticketLead.basicData'])
                ->whereNull('is_hidden')
                ->whereIn('resolution_days_status', ['none', 'pending_head'])
                ->get(['ticket_id', 'ticket_number', 'description', 'customer_id', 'ticket_lead_id', 'resolution_days_status', 'created_at']);

            $ticketIds = $tickets->pluck('ticket_id');

            // Latest pending-approval proposal per ticket (only relevant for pending_head rows)
            $pendingProposals = ConsultantMandays::whereIn('ticket_id', $ticketIds)
                ->where('status', 'pending_approval')
                ->with('proposedByAgent.basicData')
                ->orderBy('proposed_at', 'desc')
                ->get()
                ->unique('ticket_id')
                ->keyBy('ticket_id');

            // Pending Head first (newest-submitted proposal on top), then None
            // (oldest un-proposed ticket on top, so the longest-neglected ones surface first).
            $tickets = $tickets->sortBy(function ($t) use ($pendingProposals) {
                if ($t->resolution_days_status === 'pending_head') {
                    $proposedAt = $pendingProposals->get($t->ticket_id)?->proposed_at;
                    return [0, $proposedAt ? -$proposedAt->timestamp : 0];
                }
                return [1, $t->created_at?->timestamp ?? 0];
            })->values();

            $data = $tickets->map(function ($t) use ($pendingProposals) {
                $proposal = $pendingProposals->get($t->ticket_id);
                $agent    = $proposal?->proposedByAgent;

                return [
                    'ticket_id'              => $t->ticket_id,
                    'ticket_number'          => $t->ticket_number,
                    'description'            => $t->description,
                    'customer_name'          => $t->customer?->basicData?->name_1,
                    'pic_name'               => $t->ticketLead
                        ? trim(($t->ticketLead->basicData?->first_name ?? '') . ' ' . ($t->ticketLead->basicData?->last_name ?? ''))
                        : null,
                    'resolution_days_status' => $t->resolution_days_status,
                    'proposed_total_mandays' => $proposal ? round((float) $proposal->total_mandays, 2) : null,
                    'proposed_at'            => $proposal?->proposed_at?->format('Y-m-d H:i'),
                    'proposed_by'            => $agent
                        ? trim(($agent->basicData?->first_name ?? '') . ' ' . ($agent->basicData?->last_name ?? ''))
                        : null,
                ];
            });

            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {
            Log::error('resolutionDays error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to retrieve resolution days data.'], 500);
        }
    }

    // ── Web: Collection Outlook page ──────────────────────────────────────

    public function collectionOutlookIndex()
    {
        $sessionUser = SessionUser::fromSession(session('user'));
        if (!$sessionUser) {
            return redirect()->route('login');
        }

        return view('reporting.collection-outlook', ['user' => session('user')]);
    }

    // ── API: Collection Outlook data ──────────────────────────────────────
    //
    // Menampilkan outlook penagihan (Term Of Payment) per project × termin,
    // ditata ke dalam kolom bulan sesuai range (bulan+tahun) yang dipilih.
    // Placement bulan = estimated_date (tanggal rencana penagihan); fallback
    // ke paid_date lalu submit_invoice_date bila estimasi kosong.

    public function collectionOutlook(Request $request)
    {
        try {
            $sessionUser = SessionUser::fromSession(session('user'));
            if (!$sessionUser) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $employee = \App\Models\Employee::find($sessionUser->id);
            if (!$employee || !$employee->canAccessMenu('reporting.collection-outlook')) {
                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
            }

            $fromMonth = (int) $request->input('from_month', now()->month);
            $fromYear  = (int) $request->input('from_year',  now()->year);
            $toMonth   = (int) $request->input('to_month',   now()->month);
            $toYear    = (int) $request->input('to_year',    now()->year);

            // Guard bulan agar valid 1..12
            $fromMonth = min(max($fromMonth, 1), 12);
            $toMonth   = min(max($toMonth, 1), 12);

            $start = Carbon::create($fromYear, $fromMonth, 1)->startOfMonth();
            $end   = Carbon::create($toYear,  $toMonth,  1)->startOfMonth();
            if ($start->gt($end)) {
                [$start, $end] = [$end, $start];
            }

            // Bangun daftar kolom bulan (batasi maks 36 bulan agar tabel wajar)
            $months    = [];
            $monthKeys = [];
            $cursor    = $start->copy();
            $guard     = 0;
            while ($cursor->lte($end) && $guard < 36) {
                $key         = $cursor->format('Y-m');
                $months[]    = [
                    'key'         => $key,
                    'year'        => (int) $cursor->format('Y'),
                    'month'       => (int) $cursor->format('n'),
                    'label'       => $cursor->format('M Y'),   // e.g. Jan 2026
                    'month_label' => $cursor->format('F'),     // e.g. January
                ];
                $monthKeys[] = $key;
                $cursor->addMonth();
                $guard++;
            }

            $terms = DB::table('delivery_project_payment_terms as pt')
                ->join('delivery_projects as p', 'pt.delivery_projects_id', '=', 'p.id')
                ->leftJoin('customer_basic_data as cbd', 'p.client_id', '=', 'cbd.customer_id')
                ->select(
                    'pt.*',
                    'p.name as project_name',
                    'p.io_number as io_number',
                    'p.revenue as project_revenue',
                    DB::raw("COALESCE(cbd.name_1, '') as client_name")
                )
                ->get();

            $rows = [];
            foreach ($terms as $t) {
                $placementRaw = $t->estimated_date ?: $t->paid_date ?: $t->submit_invoice_date;
                if (!$placementRaw) {
                    continue; // tanpa tanggal → tidak bisa ditempatkan di kolom bulan
                }

                $key = Carbon::parse($placementRaw)->format('Y-m');
                if (!in_array($key, $monthKeys, true)) {
                    continue; // di luar range bulan yang dipilih
                }

                $rows[] = [
                    'project_id'          => (int) $t->delivery_projects_id,
                    'project_name'        => $t->project_name ?? '-',
                    'io_number'           => $t->io_number,
                    'client_name'         => $t->client_name,
                    'term_id'             => (int) $t->id,
                    'term_number'         => (int) $t->term_number,
                    'month_key'           => $key,
                    'amount'              => (float) $t->amount,
                    'status'              => $t->status,
                    'payment_term'        => $t->payment_term,
                    'payment_percentage'  => (float) $t->payment_percentage,
                    'requirements'        => $t->requirements,
                    'estimated_date'      => $t->estimated_date ? Carbon::parse($t->estimated_date)->format('d M Y') : null,
                    'submit_invoice_date' => $t->submit_invoice_date ? Carbon::parse($t->submit_invoice_date)->format('d M Y') : null,
                    'invoice_number'      => $t->invoice_number,
                    'paid_date'           => $t->paid_date ? Carbon::parse($t->paid_date)->format('d M Y') : null,
                    'project_revenue'     => (float) $t->project_revenue,
                    // Bentuk ISO dipakai form edit status di modal detail.
                    'submit_invoice_date_iso' => $t->submit_invoice_date ? Carbon::parse($t->submit_invoice_date)->format('Y-m-d') : null,
                    'paid_date_iso'           => $t->paid_date ? Carbon::parse($t->paid_date)->format('Y-m-d') : null,
                ];
            }

            // Urut: nama project (A→Z) lalu nomor termin
            usort($rows, function ($a, $b) {
                $c = strcasecmp($a['project_name'], $b['project_name']);
                return $c !== 0 ? $c : ($a['term_number'] <=> $b['term_number']);
            });

            return response()->json([
                'success' => true,
                'months'  => $months,
                'rows'    => $rows,
            ]);

        } catch (\Exception $e) {
            Log::error('collectionOutlook error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load collection outlook data. Please try again.'], 500);
        }
    }

    // ── API: Collection Outlook — ubah status penagihan sebuah TOP ────────
    //
    // Hanya menyentuh field pelunasan (status, paid date, submit invoice date,
    // invoice number). Nominal/percentage tetap dikelola dari Delivery Info
    // project agar total termin tidak bisa melampaui revenue lewat jalur ini.
    // Otorisasi ditangani middleware `menu:reporting.collection-outlook.edit`.

    public function collectionOutlookUpdateTerm(Request $request, $term)
    {
        try {
            $sessionUser = SessionUser::fromSession(session('user'));
            if (!$sessionUser) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $paymentTerm = \App\Models\DeliveryProjectPaymentTerm::find($term);
            if (!$paymentTerm) {
                return response()->json(['success' => false, 'message' => 'Payment term not found.'], 404);
            }

            $validated = $request->validate([
                'status'              => 'required|string|in:Open,Paid,Delay',
                // Konsisten dengan TOP Plan di Delivery Info: Paid wajib punya tanggal.
                'paid_date'           => 'nullable|required_if:status,Paid|date',
                'submit_invoice_date' => 'nullable|date',
                'invoice_number'      => 'nullable|required_with:submit_invoice_date|string|max:255',
            ], [
                'paid_date.required_if'        => 'Paid Date is required when Status is Paid.',
                'invoice_number.required_with' => 'Invoice Number is required when Submit Invoice Date is filled.',
            ]);

            // Status non-Paid tidak boleh menyisakan paid_date yatim.
            if ($validated['status'] !== 'Paid') {
                $validated['paid_date'] = null;
            }

            $paymentTerm->update($validated);

            // Tanggal invoice berubah → reminder penagihan harus dievaluasi ulang.
            app(\App\Services\ProjectReminderService::class)->syncAllQuietly();

            return response()->json([
                'success' => true,
                'message' => 'Payment status updated successfully.',
                'term'    => [
                    'term_id'             => $paymentTerm->id,
                    'status'              => $paymentTerm->status,
                    'paid_date'           => $paymentTerm->paid_date?->format('d M Y'),
                    'submit_invoice_date' => $paymentTerm->submit_invoice_date?->format('d M Y'),
                    'invoice_number'      => $paymentTerm->invoice_number,
                ],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Invalid data.',
                'errors'  => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('collectionOutlookUpdateTerm error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update payment status. Please try again.'], 500);
        }
    }

    // ── Web: Ticketing Overview page ────────────────────────────────────────

    public function ticketingOverviewIndex()
    {
        $sessionUser = SessionUser::fromSession(session('user'));
        if (!$sessionUser) {
            return redirect()->route('login');
        }

        return view('reporting.ticketing-overview', ['user' => session('user')]);
    }

    // ── API: Ticketing Overview data ────────────────────────────────────────
    //
    // Menampilkan jumlah tiket per customer, dikelompokkan ke dalam status:
    // Open, In Process, Close, Wait Close (menunggu konfirmasi customer), dan
    // Other (status lain: menunggu customer/pihak ketiga, hold, cancelled).

    public function ticketingOverview(Request $request)
    {
        try {
            $sessionUser = SessionUser::fromSession(session('user'));
            if (!$sessionUser) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $employee = \App\Models\Employee::find($sessionUser->id);
            if (!$employee || !$employee->canAccessMenu('reporting.ticketing-overview')) {
                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
            }

            // Total mandays per customer = sum of total_mandays across all its delivery projects
            $mandaysByCustomer = DB::table('delivery_projects')
                ->select('client_id', DB::raw('SUM(COALESCE(total_mandays, 0)) as total_mandays'))
                ->groupBy('client_id')
                ->pluck('total_mandays', 'client_id');

            $rows = DB::table('ticket')
                ->join('customer', 'ticket.customer_id', '=', 'customer.customer_id')
                ->leftJoin('customer_basic_data', 'customer.customer_id', '=', 'customer_basic_data.customer_id')
                ->whereNull('ticket.deleted_at')
                ->whereNull('ticket.is_hidden')
                ->where(function ($query) {
                    $query->whereNull('ticket.ticket_type')
                        ->orWhere('ticket.ticket_type', '!=', 'EWA');
                })
                ->groupBy('ticket.customer_id', 'customer_basic_data.name_1')
                ->select(
                    'ticket.customer_id',
                    DB::raw("COALESCE(customer_basic_data.name_1, '') as customer_name"),
                    DB::raw("SUM(CASE WHEN ticket.status = 'open' THEN 1 ELSE 0 END) as open_tickets"),
                    DB::raw("SUM(CASE WHEN ticket.status = 'inprocess' THEN 1 ELSE 0 END) as inprocess_tickets"),
                    DB::raw("SUM(CASE WHEN ticket.status = 'closed' THEN 1 ELSE 0 END) as close_tickets"),
                    DB::raw("SUM(CASE WHEN ticket.status = 'waiting_to_confirmation' THEN 1 ELSE 0 END) as wait_close_tickets"),
                    DB::raw("SUM(CASE WHEN ticket.status IN ('waiting_on_customer','waiting_on_3rd_party','hold','cancelled') THEN 1 ELSE 0 END) as other_tickets")
                )
                ->orderByRaw('customer_name')
                ->get();

            $data = $rows->map(fn($r) => [
                'customer_id'        => $r->customer_id,
                'customer_name'      => $r->customer_name ?: '—',
                'total_mandays'      => (int) ($mandaysByCustomer[$r->customer_id] ?? 0),
                'open_tickets'       => (int) $r->open_tickets,
                'inprocess_tickets'  => (int) $r->inprocess_tickets,
                'close_tickets'      => (int) $r->close_tickets,
                'other_tickets'      => (int) $r->other_tickets,
                'wait_close_tickets' => (int) $r->wait_close_tickets,
            ]);

            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {
            Log::error('ticketingOverview error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load ticketing overview data. Please try again.'], 500);
        }
    }

    // ── API: Ticketing Overview — tickets for one customer ─────────────────

    public function ticketingOverviewDetail(Request $request, $customerId)
    {
        try {
            $sessionUser = SessionUser::fromSession(session('user'));
            if (!$sessionUser) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $employee = \App\Models\Employee::find($sessionUser->id);
            if (!$employee || !$employee->canAccessMenu('reporting.ticketing-overview')) {
                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
            }

            $tickets = Ticket::with('ticketLead.basicData')
                ->where('customer_id', $customerId)
                ->whereNull('deleted_at')
                ->whereNull('is_hidden')
                ->where('status', '!=', 'closed')
                ->where(function ($query) {
                    $query->whereNull('ticket_type')
                        ->orWhere('ticket_type', '!=', 'EWA');
                })
                ->orderByDesc('created_at')
                ->get()
                ->map(fn (Ticket $ticket) => [
                    'ticket_id'     => $ticket->ticket_id,
                    'ticket_number' => $ticket->ticket_number,
                    'description'   => $ticket->description,
                    'status'        => $ticket->status,
                    'status_label'  => $ticket->status_label,
                    'lead_name'     => $ticket->ticketLead
                        ? ($ticket->ticketLead->basicData->nick_name ?? $ticket->ticketLead->basicData->first_name ?? 'Unknown')
                        : null,
                    'created_at'    => $ticket->created_at,
                ])
                ->values();

            return response()->json(['success' => true, 'tickets' => $tickets]);

        } catch (\Exception $e) {
            Log::error('ticketingOverviewDetail error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load customer detail. Please try again.'], 500);
        }
    }

    // ── Web: Ticket by Modul page ───────────────────────────────────────────

    public function ticketByModuleIndex()
    {
        $sessionUser = SessionUser::fromSession(session('user'));
        if (!$sessionUser) {
            return redirect()->route('login');
        }

        return view('reporting.ticket-by-module', ['user' => session('user')]);
    }

    // ── API: Ticket by Modul data ───────────────────────────────────────────
    //
    // Mengelompokkan tiket berdasarkan modul (module_id -> modules master,
    // fallback ke kolom legacy `module`). Tiket tanpa modul dikumpulkan ke
    // grup "No Modul Assign" yang selalu tampil paling akhir.

    public function ticketByModule(Request $request)
    {
        try {
            $sessionUser = SessionUser::fromSession(session('user'));
            if (!$sessionUser) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $employee = \App\Models\Employee::find($sessionUser->id);
            if (!$employee || !$employee->canAccessMenu('reporting.ticket-by-module')) {
                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
            }

            $tickets = Ticket::with(['ticketLead.basicData', 'moduleMaster'])
                ->whereNull('deleted_at')
                ->whereNull('is_hidden')
                ->orderByDesc('created_at')
                ->get();

            $groups = $tickets->groupBy(fn (Ticket $ticket) => $ticket->module_name ?: 'No Modul Assign');

            $data = $groups->map(function ($groupTickets, $moduleName) {
                return [
                    'module_name' => $moduleName,
                    'tickets' => $groupTickets->map(fn (Ticket $ticket) => [
                        'ticket_id'     => $ticket->ticket_id,
                        'ticket_number' => $ticket->ticket_number,
                        'description'   => $ticket->description,
                        'status'        => $ticket->status,
                        'status_label'  => $ticket->status_label,
                        'lead_name'     => $ticket->ticketLead
                            ? ($ticket->ticketLead->basicData->nick_name ?? $ticket->ticketLead->basicData->first_name ?? 'Unknown')
                            : null,
                        'created_at'    => $ticket->created_at,
                    ])->values(),
                ];
            })->values()
                ->sortBy(fn ($group) => $group['module_name'] === 'No Modul Assign' ? 1 : 0)
                ->values();

            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {
            Log::error('ticketByModule error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load ticket by modul data. Please try again.'], 500);
        }
    }

    // ── Web: Ticket by Modul export ─────────────────────────────────────────

    public function exportTicketByModule(Request $request)
    {
        try {
            $sessionUser = SessionUser::fromSession(session('user'));
            if (!$sessionUser) {
                return redirect()->route('login');
            }

            $employee = \App\Models\Employee::find($sessionUser->id);
            if (!$employee || !$employee->canAccessMenu('reporting.ticket-by-module')) {
                abort(403, 'Access denied.');
            }

            $tickets = Ticket::with(['ticketLead.basicData', 'moduleMaster'])
                ->whereNull('deleted_at')
                ->whereNull('is_hidden')
                ->orderByDesc('created_at')
                ->get();

            $groups = $tickets->groupBy(fn (Ticket $ticket) => $ticket->module_name ?: 'No Modul Assign')
                ->sortBy(fn ($groupTickets, $moduleName) => $moduleName === 'No Modul Assign' ? 1 : 0);

            $exportGroups = collect();
            foreach ($groups as $moduleName => $groupTickets) {
                $rows = collect();
                foreach ($groupTickets as $ticket) {
                    $createdAt = $ticket->created_at;
                    $rows->push([
                        'ticket_number' => $ticket->ticket_number,
                        'description'   => $ticket->description,
                        'lead_name'     => $ticket->ticketLead
                            ? ($ticket->ticketLead->basicData->nick_name ?? $ticket->ticketLead->basicData->first_name ?? 'Unknown')
                            : 'Unassigned',
                        'created_at'    => $createdAt ? $createdAt->timezone('Asia/Jakarta')->format('d/m/Y') : '',
                        'day_on_close'  => $createdAt ? (int) ceil($createdAt->diffInDays(now())) : '',
                    ]);
                }
                $exportGroups->put($moduleName, $rows);
            }

            $filename = 'Ticket_by_Modul_Export_' . now()->timezone('Asia/Jakarta')->format('dmY') . '.xlsx';

            return Excel::download(new TicketByModuleExport($exportGroups), $filename);

        } catch (\Exception $e) {
            Log::error('exportTicketByModule error: ' . $e->getMessage());
            abort(500, $e->getMessage());
        }
    }

    // ── Web: Log Shifting ───────────────────────────────────────────────────

    public function logShiftingIndex()
    {
        $sessionUser = SessionUser::fromSession(session('user'));
        if (!$sessionUser) {
            return redirect()->route('login');
        }

        return view('reporting.log-shifting', ['user' => session('user')]);
    }

    // ── API: Log Shifting — tickets that have at least one SLA message ─────

    public function logShifting(Request $request)
    {
        try {
            $sessionUser = SessionUser::fromSession(session('user'));
            if (!$sessionUser) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $employee = \App\Models\Employee::find($sessionUser->id);
            if (!$employee || !$employee->canAccessMenu('reporting.log-shifting')) {
                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
            }

            $tickets = Ticket::whereNull('deleted_at')
                ->whereNull('is_hidden')
                ->whereHas('messages', function ($q) {
                    $q->whereNotNull('sla_message')->where('sla_message', '!=', '');
                })
                ->orderByDesc('created_at')
                ->get(['ticket_id', 'ticket_number', 'description', 'created_at']);

            $data = $tickets->map(fn (Ticket $ticket) => [
                'ticket_id'     => $ticket->ticket_id,
                'ticket_number' => $ticket->ticket_number,
                'description'   => $ticket->description,
                'created_at'    => $ticket->created_at,
            ])->values();

            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {
            Log::error('logShifting error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load log shifting data. Please try again.'], 500);
        }
    }

    // ── API: Log Shifting — SLA message detail rows for one ticket ─────────

    public function logShiftingDetail($ticketId)
    {
        try {
            $sessionUser = SessionUser::fromSession(session('user'));
            if (!$sessionUser) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $employee = \App\Models\Employee::find($sessionUser->id);
            if (!$employee || !$employee->canAccessMenu('reporting.log-shifting')) {
                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
            }

            $ticket = Ticket::whereNull('deleted_at')->findOrFail($ticketId);

            $messages = $ticket->messages()
                ->whereNotNull('sla_message')
                ->where('sla_message', '!=', '')
                ->with('slaMessageBy.basicData')
                ->orderBy('created_at')
                ->get();

            $rows = $messages->map(function (\App\Models\TicketMessage $msg) {
                $byName = $msg->slaMessageBy
                    ? trim(($msg->slaMessageBy->basicData->first_name ?? '') . ' ' . ($msg->slaMessageBy->basicData->last_name ?? '')) ?: ($msg->slaMessageBy->eci ?? 'Unknown')
                    : null;

                return [
                    'message_id'      => $msg->id,
                    'bubble_date'     => $msg->created_at,
                    'sla_message'     => $msg->sla_message,
                    'sla_message_by'  => $byName,
                    'sla_message_at'  => $msg->sla_message_at,
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data'    => [
                    'ticket' => [
                        'ticket_id'     => $ticket->ticket_id,
                        'ticket_number' => $ticket->ticket_number,
                        'description'   => $ticket->description,
                    ],
                    'messages' => $rows,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('logShiftingDetail error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load SLA message detail. Please try again.'], 500);
        }
    }

    // ── Helper ────────────────────────────────────────────────────────────

    private function monthName(int $month): string
    {
        return Carbon::create(null, $month, 1)->format('F');
    }
}
