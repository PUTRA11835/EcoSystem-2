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
                                round((float)($detail->approved_mandays ?? 0) + (float)($detail->approved_additional ?? 0), 2);
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
                                round((float)($detail->approved_mandays ?? 0) + (float)($detail->approved_additional ?? 0), 2);
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
                    'cmd.approved_mandays',
                    'cmd.additional_mandays',
                    'cmd.notes',
                    'cmd.approved_additional',
                    'cm.status as proposal_status'
                )
                ->orderBy('ticket.ticket_number')
                ->orderBy('employee.eci')
                ->get();

            $exportRows = $rows->map(function ($r) {
                $isApproved = $r->proposal_status === 'approved';
                // Before Head approval there's nothing "approved" yet — fall back to the
                // raw proposed numbers so pending rows keep showing what they always showed,
                // instead of suddenly reading as 0.
                $total = $isApproved
                    ? round((float) ($r->approved_mandays ?? 0) + (float) $r->approved_additional, 2)
                    : round((float) $r->mandays + (float) $r->additional_mandays, 2);

                return [
                    'ticket_number'   => $r->ticket_number,
                    'employee_eci'    => $r->employee_eci,
                    'name'            => trim($r->full_name),
                    'resolution_days' => (float) $r->mandays,
                    'additional_days' => (float) $r->additional_mandays,
                    'note'            => $r->notes ?? '',
                    'approved_days'   => (float) ($r->approved_mandays ?? 0),
                    'approve_add'     => (float) $r->approved_additional,
                    'total'           => $total,
                ];
            });

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

            // Filter opsional berdasarkan Account Executive (nama AE di project).
            $filterAe = trim((string) $request->input('ae', ''));

            $terms = DB::table('delivery_project_payment_terms as pt')
                ->join('delivery_projects as p', 'pt.delivery_projects_id', '=', 'p.id')
                ->leftJoin('customer_basic_data as cbd', 'p.client_id', '=', 'cbd.customer_id')
                ->when($filterAe !== '', fn($q) => $q->where('p.ae_name', $filterAe))
                ->select(
                    'pt.*',
                    'p.name as project_name',
                    'p.io_number as io_number',
                    'p.ae_name as ae_name',
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
                    'ae_name'             => $t->ae_name,
                    'client_name'         => $t->client_name,
                    'term_id'             => (int) $t->id,
                    'term_number'         => (int) $t->term_number,
                    'month_key'           => $key,
                    // Amount = nilai turunan (revenue x % / 100). Dihitung ulang di sini
                    // supaya laporan tidak ikut menampilkan nilai tersimpan yang basi
                    // (term yang dibuat sebelum revenue diisi tersimpan 0).
                    'amount'              => round(((float) $t->project_revenue) * ((float) $t->payment_percentage) / 100, 2),
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

            // Daftar AE untuk dropdown filter (semua AE bernama di delivery_projects,
            // independen dari range bulan agar pilihan tetap stabil).
            $aeOptions = DB::table('delivery_projects')
                ->whereNotNull('ae_name')
                ->where('ae_name', '!=', '')
                ->distinct()
                ->orderBy('ae_name')
                ->pluck('ae_name')
                ->values();

            return response()->json([
                'success'    => true,
                'months'     => $months,
                'rows'       => $rows,
                'ae_options' => $aeOptions,
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

    // ── Web: Collection Outlook — Excel export (list rincian per termin) ──────
    //
    // Satu baris per Term Of Payment dalam range bulan terpilih (placement =
    // estimated_date → paid_date → submit_invoice_date, sama seperti tampilan).
    // Menghormati filter AE yang sedang aktif di halaman.

    public function exportCollectionOutlook(Request $request)
    {
        try {
            $sessionUser = SessionUser::fromSession(session('user'));
            if (!$sessionUser) {
                return redirect()->route('login');
            }

            $employee = \App\Models\Employee::find($sessionUser->id);
            if (!$employee || !$employee->canAccessMenu('reporting.collection-outlook')) {
                abort(403, 'Access denied.');
            }

            $fromMonth = min(max((int) $request->input('from_month', now()->month), 1), 12);
            $fromYear  = (int) $request->input('from_year',  now()->year);
            $toMonth   = min(max((int) $request->input('to_month',   now()->month), 1), 12);
            $toYear    = (int) $request->input('to_year',    now()->year);
            $filterAe  = trim((string) $request->input('ae', ''));

            $start = Carbon::create($fromYear, $fromMonth, 1)->startOfMonth();
            $end   = Carbon::create($toYear,  $toMonth,  1)->startOfMonth();
            if ($start->gt($end)) {
                [$start, $end] = [$end, $start];
            }

            $monthKeys = [];
            $cursor    = $start->copy();
            $guard     = 0;
            while ($cursor->lte($end) && $guard < 36) {
                $monthKeys[] = $cursor->format('Y-m');
                $cursor->addMonth();
                $guard++;
            }

            $terms = DB::table('delivery_project_payment_terms as pt')
                ->join('delivery_projects as p', 'pt.delivery_projects_id', '=', 'p.id')
                ->leftJoin('customer_basic_data as cbd', 'p.client_id', '=', 'cbd.customer_id')
                ->when($filterAe !== '', fn($q) => $q->where('p.ae_name', $filterAe))
                ->select(
                    'pt.*',
                    'p.name as project_name',
                    'p.io_number as io_number',
                    'p.ae_name as ae_name',
                    'p.revenue as project_revenue',
                    DB::raw("COALESCE(cbd.name_1, '') as client_name")
                )
                ->get();

            $rows = [];
            foreach ($terms as $t) {
                $placementRaw = $t->estimated_date ?: $t->paid_date ?: $t->submit_invoice_date;
                if (!$placementRaw) {
                    continue;
                }
                $key = Carbon::parse($placementRaw)->format('Y-m');
                if (!in_array($key, $monthKeys, true)) {
                    continue;
                }

                $rows[] = [
                    'client_name'         => $t->client_name ?: '-',
                    'project_name'        => $t->project_name ?: '-',
                    'io_number'           => $t->io_number ?: '-',
                    'ae_name'             => $t->ae_name ?: '-',
                    'term_number'         => (int) $t->term_number,
                    'payment_term'        => $t->payment_term ?: '-',
                    'payment_percentage'  => (float) $t->payment_percentage,
                    // Amount = nilai turunan (revenue x % / 100). Dihitung ulang di sini
                    // supaya laporan tidak ikut menampilkan nilai tersimpan yang basi
                    // (term yang dibuat sebelum revenue diisi tersimpan 0).
                    'amount'              => round(((float) $t->project_revenue) * ((float) $t->payment_percentage) / 100, 2),
                    'status'              => $t->status,
                    'estimated_date'      => $t->estimated_date ? Carbon::parse($t->estimated_date)->format('d M Y') : '',
                    'submit_invoice_date' => $t->submit_invoice_date ? Carbon::parse($t->submit_invoice_date)->format('d M Y') : '',
                    'invoice_number'      => $t->invoice_number ?: '',
                    'paid_date'           => $t->paid_date ? Carbon::parse($t->paid_date)->format('d M Y') : '',
                ];
            }

            usort($rows, function ($a, $b) {
                $c = strcasecmp($a['project_name'], $b['project_name']);
                return $c !== 0 ? $c : ($a['term_number'] <=> $b['term_number']);
            });

            $filename = 'Collection_Outlook_' . $start->format('Ym') . '-' . $end->format('Ym') . '_' . now()->format('Ymd') . '.xlsx';

            return Excel::download(new \App\Exports\CollectionOutlookExport(collect($rows)), $filename);

        } catch (\Exception $e) {
            Log::error('exportCollectionOutlook error: ' . $e->getMessage());
            abort(500, $e->getMessage());
        }
    }

    // =========================================================================
    // COLLECTION OUTLOOK — DELIVERY SUPPORT
    // Mirror halaman Collection Outlook (project) untuk sumber Term Of Payment
    // milik Delivery Support. Filter memakai "Type" support (bukan Account
    // Executive), dan tidak memanggil ProjectReminderService.
    // =========================================================================

    public function collectionOutlookSupportIndex()
    {
        $sessionUser = SessionUser::fromSession(session('user'));
        if (!$sessionUser) {
            return redirect()->route('login');
        }

        return view('reporting.collection-outlook-support', ['user' => session('user')]);
    }

    public function collectionOutlookSupport(Request $request)
    {
        try {
            $sessionUser = SessionUser::fromSession(session('user'));
            if (!$sessionUser) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $employee = \App\Models\Employee::find($sessionUser->id);
            if (!$employee || !$employee->canAccessMenu('reporting.collection-outlook-support')) {
                return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
            }

            $fromMonth = min(max((int) $request->input('from_month', now()->month), 1), 12);
            $fromYear  = (int) $request->input('from_year',  now()->year);
            $toMonth   = min(max((int) $request->input('to_month',   now()->month), 1), 12);
            $toYear    = (int) $request->input('to_year',    now()->year);

            $start = Carbon::create($fromYear, $fromMonth, 1)->startOfMonth();
            $end   = Carbon::create($toYear,  $toMonth,  1)->startOfMonth();
            if ($start->gt($end)) {
                [$start, $end] = [$end, $start];
            }

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
                    'label'       => $cursor->format('M Y'),
                    'month_label' => $cursor->format('F'),
                ];
                $monthKeys[] = $key;
                $cursor->addMonth();
                $guard++;
            }

            // Filter opsional berdasarkan Type support (analog filter AE di project).
            $filterType = trim((string) $request->input('type', ''));

            $terms = DB::table('delivery_support_payment_terms as pt')
                ->join('delivery_support as s', 'pt.delivery_support_id', '=', 's.id')
                ->leftJoin('customer_basic_data as cbd', 's.client_id', '=', 'cbd.customer_id')
                ->when($filterType !== '', fn($q) => $q->where('s.type', $filterType))
                ->select(
                    'pt.*',
                    's.name as support_name',
                    's.io_number as io_number',
                    's.type as support_type',
                    's.revenue as support_revenue',
                    DB::raw("COALESCE(cbd.name_1, '') as client_name")
                )
                ->get();

            $rows = [];
            foreach ($terms as $t) {
                $placementRaw = $t->estimated_date ?: $t->paid_date ?: $t->submit_invoice_date;
                if (!$placementRaw) {
                    continue;
                }
                $key = Carbon::parse($placementRaw)->format('Y-m');
                if (!in_array($key, $monthKeys, true)) {
                    continue;
                }

                $rows[] = [
                    'support_id'          => (int) $t->delivery_support_id,
                    'support_name'        => $t->support_name ?? '-',
                    'io_number'           => $t->io_number,
                    'support_type'        => $t->support_type,
                    'client_name'         => $t->client_name,
                    'term_id'             => (int) $t->id,
                    'term_number'         => (int) $t->term_number,
                    'month_key'           => $key,
                    // Amount = nilai turunan (revenue x % / 100). Dihitung ulang di sini
                    // supaya laporan tidak ikut menampilkan nilai tersimpan yang basi
                    // (term yang dibuat sebelum revenue diisi tersimpan 0).
                    'amount'              => round(((float) $t->support_revenue) * ((float) $t->payment_percentage) / 100, 2),
                    'status'              => $t->status,
                    'payment_term'        => $t->payment_term,
                    'payment_percentage'  => (float) $t->payment_percentage,
                    'requirements'        => $t->requirements,
                    'estimated_date'      => $t->estimated_date ? Carbon::parse($t->estimated_date)->format('d M Y') : null,
                    'submit_invoice_date' => $t->submit_invoice_date ? Carbon::parse($t->submit_invoice_date)->format('d M Y') : null,
                    'invoice_number'      => $t->invoice_number,
                    'paid_date'           => $t->paid_date ? Carbon::parse($t->paid_date)->format('d M Y') : null,
                    'support_revenue'     => (float) $t->support_revenue,
                    'submit_invoice_date_iso' => $t->submit_invoice_date ? Carbon::parse($t->submit_invoice_date)->format('Y-m-d') : null,
                    'paid_date_iso'           => $t->paid_date ? Carbon::parse($t->paid_date)->format('Y-m-d') : null,
                ];
            }

            usort($rows, function ($a, $b) {
                $c = strcasecmp($a['support_name'], $b['support_name']);
                return $c !== 0 ? $c : ($a['term_number'] <=> $b['term_number']);
            });

            $typeOptions = DB::table('delivery_support')
                ->whereNotNull('type')
                ->where('type', '!=', '')
                ->distinct()
                ->orderBy('type')
                ->pluck('type')
                ->values();

            return response()->json([
                'success'      => true,
                'months'       => $months,
                'rows'         => $rows,
                'type_options' => $typeOptions,
            ]);

        } catch (\Exception $e) {
            Log::error('collectionOutlookSupport error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load collection outlook data. Please try again.'], 500);
        }
    }

    public function collectionOutlookSupportUpdateTerm(Request $request, $term)
    {
        try {
            $sessionUser = SessionUser::fromSession(session('user'));
            if (!$sessionUser) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }

            $paymentTerm = \App\Models\DeliverySupportPaymentTerm::find($term);
            if (!$paymentTerm) {
                return response()->json(['success' => false, 'message' => 'Payment term not found.'], 404);
            }

            $validated = $request->validate([
                'status'              => 'required|string|in:Open,Paid,Delay',
                'paid_date'           => 'nullable|required_if:status,Paid|date',
                'submit_invoice_date' => 'nullable|date',
                'invoice_number'      => 'nullable|required_with:submit_invoice_date|string|max:255',
            ], [
                'paid_date.required_if'        => 'Paid Date is required when Status is Paid.',
                'invoice_number.required_with' => 'Invoice Number is required when Submit Invoice Date is filled.',
            ]);

            if ($validated['status'] !== 'Paid') {
                $validated['paid_date'] = null;
            }

            $paymentTerm->update($validated);

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
            Log::error('collectionOutlookSupportUpdateTerm error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to update payment status. Please try again.'], 500);
        }
    }

    public function exportCollectionOutlookSupport(Request $request)
    {
        try {
            $sessionUser = SessionUser::fromSession(session('user'));
            if (!$sessionUser) {
                return redirect()->route('login');
            }

            $employee = \App\Models\Employee::find($sessionUser->id);
            if (!$employee || !$employee->canAccessMenu('reporting.collection-outlook-support')) {
                abort(403, 'Access denied.');
            }

            $fromMonth = min(max((int) $request->input('from_month', now()->month), 1), 12);
            $fromYear  = (int) $request->input('from_year',  now()->year);
            $toMonth   = min(max((int) $request->input('to_month',   now()->month), 1), 12);
            $toYear    = (int) $request->input('to_year',    now()->year);
            $filterType = trim((string) $request->input('type', ''));

            $start = Carbon::create($fromYear, $fromMonth, 1)->startOfMonth();
            $end   = Carbon::create($toYear,  $toMonth,  1)->startOfMonth();
            if ($start->gt($end)) {
                [$start, $end] = [$end, $start];
            }

            $monthKeys = [];
            $cursor    = $start->copy();
            $guard     = 0;
            while ($cursor->lte($end) && $guard < 36) {
                $monthKeys[] = $cursor->format('Y-m');
                $cursor->addMonth();
                $guard++;
            }

            $terms = DB::table('delivery_support_payment_terms as pt')
                ->join('delivery_support as s', 'pt.delivery_support_id', '=', 's.id')
                ->leftJoin('customer_basic_data as cbd', 's.client_id', '=', 'cbd.customer_id')
                ->when($filterType !== '', fn($q) => $q->where('s.type', $filterType))
                ->select(
                    'pt.*',
                    's.name as support_name',
                    's.io_number as io_number',
                    's.type as support_type',
                    's.revenue as support_revenue',
                    DB::raw("COALESCE(cbd.name_1, '') as client_name")
                )
                ->get();

            $rows = [];
            foreach ($terms as $t) {
                $placementRaw = $t->estimated_date ?: $t->paid_date ?: $t->submit_invoice_date;
                if (!$placementRaw) {
                    continue;
                }
                $key = Carbon::parse($placementRaw)->format('Y-m');
                if (!in_array($key, $monthKeys, true)) {
                    continue;
                }

                $rows[] = [
                    'client_name'         => $t->client_name ?: '-',
                    'support_name'        => $t->support_name ?: '-',
                    'io_number'           => $t->io_number ?: '-',
                    'support_type'        => $t->support_type ?: '-',
                    'term_number'         => (int) $t->term_number,
                    'payment_term'        => $t->payment_term ?: '-',
                    'payment_percentage'  => (float) $t->payment_percentage,
                    // Amount = nilai turunan (revenue x % / 100). Dihitung ulang di sini
                    // supaya laporan tidak ikut menampilkan nilai tersimpan yang basi
                    // (term yang dibuat sebelum revenue diisi tersimpan 0).
                    'amount'              => round(((float) $t->support_revenue) * ((float) $t->payment_percentage) / 100, 2),
                    'status'              => $t->status,
                    'estimated_date'      => $t->estimated_date ? Carbon::parse($t->estimated_date)->format('d M Y') : '',
                    'submit_invoice_date' => $t->submit_invoice_date ? Carbon::parse($t->submit_invoice_date)->format('d M Y') : '',
                    'invoice_number'      => $t->invoice_number ?: '',
                    'paid_date'           => $t->paid_date ? Carbon::parse($t->paid_date)->format('d M Y') : '',
                ];
            }

            usort($rows, function ($a, $b) {
                $c = strcasecmp($a['support_name'], $b['support_name']);
                return $c !== 0 ? $c : ($a['term_number'] <=> $b['term_number']);
            });

            $filename = 'Collection_Outlook_Support_' . $start->format('Ym') . '-' . $end->format('Ym') . '_' . now()->format('Ymd') . '.xlsx';

            return Excel::download(new \App\Exports\CollectionOutlookSupportExport(collect($rows)), $filename);

        } catch (\Exception $e) {
            Log::error('exportCollectionOutlookSupport error: ' . $e->getMessage());
            abort(500, $e->getMessage());
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
                ->with(['messages' => function ($q) {
                    $q->whereNotNull('sla_message')
                        ->where('sla_message', '!=', '')
                        ->with('slaMessageBy.basicData')
                        ->reorder('sla_message_at', 'desc');
                }])
                ->orderByDesc('created_at')
                ->get(['ticket_id', 'ticket_number', 'description', 'created_at']);

            $data = $tickets->map(function (Ticket $ticket) {
                $lastMessage = $ticket->messages->first();
                $lastEditor  = $lastMessage?->slaMessageBy;
                $pic = $lastEditor
                    ? (trim(($lastEditor->basicData->first_name ?? '') . ' ' . ($lastEditor->basicData->last_name ?? '')) ?: ($lastEditor->eci ?? null))
                    : null;

                return [
                    'ticket_id'     => $ticket->ticket_id,
                    'ticket_number' => $ticket->ticket_number,
                    'description'   => $ticket->description,
                    'created_at'    => $ticket->created_at,
                    'pic'           => $pic,
                ];
            })->values();

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

            // Dua pintu masuk ke data yang sama: halaman/klik-kanan Reporting
            // (reporting.log-shifting) dan tombol shortcut di headbar room chat
            // (ticket.shifting-log). Salah satu slug cukup.
            $employee = \App\Models\Employee::find($sessionUser->id);
            $allowed  = $employee && (
                $employee->canAccessMenu('reporting.log-shifting')
                || $employee->canAccessMenu('ticket.shifting-log')
            );
            if (!$allowed) {
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

    // ── Web: Consultant Assignment ──────────────────────────────────────────

    public function consultantAssignmentIndex()
    {
        $sessionUser = SessionUser::fromSession(session('user'));
        if (!$sessionUser) {
            return redirect()->route('login');
        }

        return view('reporting.consultant-assignment', ['user' => session('user')]);
    }

    /**
     * API: daftar consultant yang tergabung di Delivery Project.
     *
     * Satu baris = satu penugasan (satu baris pivot `delivery_project_employee`),
     * jadi orang yang memegang dua peran/modul di project yang sama muncul dua
     * kali — sama seperti tabel Team Members di halaman project.
     */
    public function consultantAssignment(Request $request)
    {
        try {
            $employee = $this->consultantAssignmentGuard();
            if ($employee instanceof \Illuminate\Http\JsonResponse) {
                return $employee;
            }

            [$rows, $stats] = $this->consultantAssignmentRows($request);

            return response()->json([
                'success' => true,
                'data'    => $rows,
                'stats'   => $stats,
            ]);

        } catch (\Exception $e) {
            Log::error('consultantAssignment error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load consultant assignments. Please try again.'], 500);
        }
    }

    /**
     * API: isi dropdown filter (project / customer / module / position).
     * Diambil dari data penugasan yang ada supaya tidak menawarkan opsi kosong.
     */
    public function consultantAssignmentFilterOptions()
    {
        try {
            $employee = $this->consultantAssignmentGuard();
            if ($employee instanceof \Illuminate\Http\JsonResponse) {
                return $employee;
            }

            [$rows] = $this->consultantAssignmentRows(new Request());

            $distinct = function (string $key) use ($rows) {
                return collect($rows)
                    ->pluck($key)
                    ->map(fn ($v) => trim((string) $v))
                    ->filter()
                    ->unique()
                    ->sort(SORT_NATURAL | SORT_FLAG_CASE)
                    ->values()
                    ->all();
            };

            return response()->json([
                'success'    => true,
                'projects'   => collect($rows)
                    ->unique('project_id')
                    ->sortBy('project_name', SORT_NATURAL | SORT_FLAG_CASE)
                    ->map(fn ($r) => ['id' => $r['project_id'], 'name' => $r['project_name']])
                    ->values()
                    ->all(),
                'customers'  => $distinct('customer_name'),
                'modules'    => $distinct('module'),
                'positions'  => $distinct('position'),
                'vendors'    => $distinct('vendor_name'),
            ]);

        } catch (\Exception $e) {
            Log::error('consultantAssignmentFilterOptions error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to load filter options.'], 500);
        }
    }

    /**
     * Web: export Consultant Assignment ke Excel dengan filter yang sedang aktif
     * di halaman (parameter query-nya identik dengan endpoint API-nya).
     */
    public function exportConsultantAssignment(Request $request)
    {
        try {
            $sessionUser = SessionUser::fromSession(session('user'));
            if (!$sessionUser) {
                return redirect()->route('login');
            }

            $employee = \App\Models\Employee::find($sessionUser->id);
            if (!$employee || !$employee->canAccessMenu('reporting.consultant-assignment')) {
                abort(403, 'Access denied.');
            }

            [$rows] = $this->consultantAssignmentRows($request);

            return Excel::download(
                new \App\Exports\ConsultantAssignmentExport(collect($rows)),
                'consultant-assignment-' . now()->format('Ymd-His') . '.xlsx'
            );

        } catch (\Exception $e) {
            Log::error('exportConsultantAssignment error: ' . $e->getMessage());
            abort(500, 'Failed to export consultant assignments.');
        }
    }

    /**
     * Guard bersama untuk endpoint API Consultant Assignment.
     *
     * Izin diresolve lewat Menu Access (`canAccessMenu`), BUKAN daftar RoleId
     * hardcode — supaya keputusan admin di Control Center benar-benar berlaku.
     *
     * @return \App\Models\Employee|\Illuminate\Http\JsonResponse
     */
    private function consultantAssignmentGuard()
    {
        $sessionUser = SessionUser::fromSession(session('user'));
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $employee = \App\Models\Employee::find($sessionUser->id);
        if (!$employee || !$employee->canAccessMenu('reporting.consultant-assignment')) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 403);
        }

        return $employee;
    }

    /**
     * Bangun baris laporan Consultant Assignment + ringkasannya.
     *
     * Seluruh filter dikerjakan di sini (server-side) supaya halaman dan tombol
     * Export memakai hasil yang sama persis — tidak ada logika filter kembar di
     * JavaScript yang bisa menyimpang.
     *
     * `stats` dihitung SEBELUM filter Assignment Status diterapkan, karena kartu
     * ringkasan di halaman itu sendiri yang menjadi kontrol filter tersebut.
     *
     * @return array{0: array<int,array<string,mixed>>, 1: array<string,mixed>}
     */
    private function consultantAssignmentRows(Request $request): array
    {
        $today = Carbon::today();

        // ── Planned MD: total working-day duration dari activity_employee ──────
        $plannedMd = DB::table('activity_employee as ae')
            ->join('delivery_project_activities as a', 'a.id', '=', 'ae.delivery_project_activity_id')
            ->where('ae.is_active', true)
            ->groupBy('a.delivery_projects_id', 'ae.employee_id')
            ->select(
                'a.delivery_projects_id as project_id',
                'ae.employee_id',
                DB::raw('SUM(COALESCE(ae.duration, 0)) as md')
            )
            ->get()
            ->keyBy(fn ($r) => $r->project_id . '|' . $r->employee_id);

        // ── Actual MD: timesheet approved yang dibebankan ke project ini ──────
        $actualMd = DB::table('timesheets')
            ->whereNotNull('delivery_projects_id')
            ->where('status', 'approved')
            ->whereNull('deleted_at')
            ->groupBy('delivery_projects_id', 'employee_id')
            ->select(
                'delivery_projects_id as project_id',
                'employee_id',
                DB::raw('SUM(COALESCE(md_consumed, duration_minutes / 480.0, 0)) as md')
            )
            ->get()
            ->keyBy(fn ($r) => $r->project_id . '|' . $r->employee_id);

        // ── Baris pivot (sumber utama Team Members) ───────────────────────────
        $pivotRows = DB::table('delivery_project_employee as dpe')
            ->join('delivery_projects as p', 'p.id', '=', 'dpe.delivery_projects_id')
            ->leftJoin('employee as e', 'e.employee_id', '=', 'dpe.employee_id')
            ->leftJoin('employee_basic_data as ebd', 'ebd.employee_id', '=', 'dpe.employee_id')
            ->leftJoin('customer_basic_data as cbd', 'cbd.customer_id', '=', 'p.client_id')
            ->select(
                'dpe.id as assignment_id',
                'dpe.delivery_projects_id as project_id',
                'dpe.employee_id',
                'dpe.module',
                'dpe.role',
                'dpe.employee_type',
                'dpe.vendor_name',
                'dpe.start_date',
                'dpe.end_date',
                'dpe.notes',
                'p.name as project_name',
                'p.io_number',
                'p.category as project_category',
                'p.status as project_status',
                'p.phase as project_phase',
                'p.is_closed',
                'p.project_owner',
                'p.project_type',
                'e.eci',
                'e.is_active as employee_is_active',
                'ebd.first_name',
                'ebd.last_name',
                'ebd.position',
                'ebd.division',
                'ebd.department',
                'ebd.home_base',
                DB::raw("COALESCE(cbd.name_1, '') as customer_name")
            )
            ->get();

        $rows     = [];
        $seenKeys = [];

        foreach ($pivotRows as $r) {
            $seenKeys[$r->project_id . '|' . $r->employee_id . '|' . mb_strtolower((string) $r->role)] = true;
            $rows[] = $this->consultantAssignmentRow($r, (string) $r->role, $plannedMd, $actualMd, $today, false);
        }

        // ── Baris FK-fallback: PM / Co PM / Project Admin yang hanya tersimpan
        // di kolom project (project lama tanpa entri pivot). Tanpa ini laporan
        // kehilangan PM dari project-project tersebut.
        $fkProjects = DB::table('delivery_projects as p')
            ->leftJoin('customer_basic_data as cbd', 'cbd.customer_id', '=', 'p.client_id')
            ->where(function ($q) {
                $q->whereNotNull('p.project_manager_id')
                    ->orWhereNotNull('p.co_pm_id')
                    ->orWhereNotNull('p.project_admin_id');
            })
            ->select(
                'p.id as project_id',
                'p.name as project_name',
                'p.io_number',
                'p.category as project_category',
                'p.status as project_status',
                'p.phase as project_phase',
                'p.is_closed',
                'p.project_owner',
                'p.project_type',
                'p.project_manager_id',
                'p.co_pm_id',
                'p.project_admin_id',
                DB::raw("COALESCE(cbd.name_1, '') as customer_name")
            )
            ->get();

        $fkRoleColumns = [
            'project_manager_id' => 'Project Manager',
            'co_pm_id'           => 'Co Project Manager',
            'project_admin_id'   => 'Project Admin',
        ];

        // Kumpulkan employee_id yang perlu dilengkapi datanya, lalu ambil sekali
        // saja (hindari query per baris).
        $fkEmployeeIds = collect();
        foreach ($fkProjects as $p) {
            foreach (array_keys($fkRoleColumns) as $col) {
                if ($p->$col) {
                    $fkEmployeeIds->push($p->$col);
                }
            }
        }

        $fkEmployees = $fkEmployeeIds->isEmpty()
            ? collect()
            : DB::table('employee as e')
                ->leftJoin('employee_basic_data as ebd', 'ebd.employee_id', '=', 'e.employee_id')
                ->whereIn('e.employee_id', $fkEmployeeIds->unique()->values())
                ->select(
                    'e.employee_id',
                    'e.eci',
                    'e.is_active as employee_is_active',
                    'ebd.first_name',
                    'ebd.last_name',
                    'ebd.position',
                    'ebd.division',
                    'ebd.department',
                    'ebd.home_base'
                )
                ->get()
                ->keyBy('employee_id');

        foreach ($fkProjects as $p) {
            foreach ($fkRoleColumns as $col => $roleLabel) {
                $empId = $p->$col;
                if (!$empId) {
                    continue;
                }

                $key = $p->project_id . '|' . $empId . '|' . mb_strtolower($roleLabel);
                if (isset($seenKeys[$key])) {
                    continue; // sudah ada baris pivot dengan peran yang sama
                }
                $seenKeys[$key] = true;

                $emp = $fkEmployees->get($empId);

                $merged = (object) array_merge((array) $p, [
                    'assignment_id'      => null,
                    'employee_id'        => $empId,
                    'module'             => null,
                    'role'               => $roleLabel,
                    'employee_type'      => 'Internal',
                    'vendor_name'        => null,
                    'start_date'         => null,
                    'end_date'           => null,
                    'notes'              => null,
                    'eci'                => $emp->eci ?? null,
                    'employee_is_active' => $emp->employee_is_active ?? null,
                    'first_name'         => $emp->first_name ?? null,
                    'last_name'          => $emp->last_name ?? null,
                    'position'           => $emp->position ?? null,
                    'division'           => $emp->division ?? null,
                    'department'         => $emp->department ?? null,
                    'home_base'          => $emp->home_base ?? null,
                ]);

                $rows[] = $this->consultantAssignmentRow($merged, $roleLabel, $plannedMd, $actualMd, $today, true);
            }
        }

        // ── Filter (server-side) ──────────────────────────────────────────────
        $search      = mb_strtolower(trim((string) $request->input('search', '')));
        $consultant  = mb_strtolower(trim((string) $request->input('consultant', '')));
        $projectId   = trim((string) $request->input('project_id', ''));
        $customer    = trim((string) $request->input('customer', ''));
        $module      = trim((string) $request->input('module', ''));
        $position    = trim((string) $request->input('position', ''));
        $roleList    = $this->csvFilter($request->input('role', ''));
        $typeList    = $this->csvFilter($request->input('employee_type', ''));
        $categoryList = $this->csvFilter($request->input('project_category', ''));
        $periodFrom  = trim((string) $request->input('period_from', ''));
        $periodTo    = trim((string) $request->input('period_to', ''));

        $rows = array_values(array_filter($rows, function (array $r) use (
            $search, $consultant, $projectId, $customer, $module, $position,
            $roleList, $typeList, $categoryList, $periodFrom, $periodTo
        ) {
            if ($search !== '') {
                $haystack = mb_strtolower(implode(' ', [
                    $r['consultant_name'], $r['eci'], $r['project_name'], $r['io_number'],
                    $r['customer_name'], $r['module'], $r['role'], $r['position'],
                    $r['vendor_name'], $r['notes'],
                ]));
                if (!str_contains($haystack, $search)) {
                    return false;
                }
            }
            if ($consultant !== '' && !str_contains(mb_strtolower($r['consultant_name']), $consultant)) {
                return false;
            }
            if ($projectId !== '' && (string) $r['project_id'] !== $projectId) {
                return false;
            }
            if ($customer !== '' && $r['customer_name'] !== $customer) {
                return false;
            }
            if ($module !== '') {
                if ($module === '__none__') {
                    if ($r['module'] !== '') {
                        return false;
                    }
                } elseif ($r['module'] !== $module) {
                    return false;
                }
            }
            if ($position !== '' && $r['position'] !== $position) {
                return false;
            }
            if ($roleList && !in_array(mb_strtolower($r['role']), $roleList, true)) {
                return false;
            }
            if ($typeList && !in_array(mb_strtolower($r['employee_type']), $typeList, true)) {
                return false;
            }
            if ($categoryList && !in_array(mb_strtolower($r['project_category']), $categoryList, true)) {
                return false;
            }

            // Rentang periode: penugasan lolos bila periodenya BERSINGGUNGAN
            // dengan rentang yang diminta (bukan harus termuat seluruhnya).
            // Penugasan tanpa tanggal tidak bisa dinilai → selalu lolos, supaya
            // baris FK-fallback tidak hilang diam-diam saat filter dipakai.
            if (($periodFrom !== '' || $periodTo !== '') && $r['start_date']) {
                $start = $r['start_date'];
                $end   = $r['end_date'] ?: '9999-12-31';
                if ($periodTo !== '' && $start > $periodTo) {
                    return false;
                }
                if ($periodFrom !== '' && $end < $periodFrom) {
                    return false;
                }
            }

            return true;
        }));

        // ── Ringkasan (dihitung sebelum filter Assignment Status) ─────────────
        $collection = collect($rows);
        $stats = [
            'assignments'  => $collection->count(),
            'consultants'  => $collection->pluck('employee_id')->unique()->count(),
            'projects'     => $collection->pluck('project_id')->unique()->count(),
            'active'       => $collection->where('assignment_status', 'Active')->count(),
            'upcoming'     => $collection->where('assignment_status', 'Upcoming')->count(),
            'ended'        => $collection->where('assignment_status', 'Ended')->count(),
            'undated'      => $collection->where('assignment_status', 'No Period')->count(),
            'internal'     => $collection->where('employee_type', 'Internal')->count(),
            'external'     => $collection->whereIn('employee_type', ['External', 'Vendor'])->count(),
            'planned_md'   => round((float) $collection->sum('planned_md'), 2),
            'actual_md'    => round((float) $collection->sum('actual_md'), 2),
        ];

        // ── Filter Assignment Status (kartu ringkasan) ────────────────────────
        $statusList = $this->csvFilter($request->input('assignment_status', ''));
        if ($statusList) {
            $rows = array_values(array_filter(
                $rows,
                fn (array $r) => in_array(mb_strtolower($r['assignment_status']), $statusList, true)
            ));
        }

        // ── Urutan default: consultant → project → mulai penugasan ───────────
        usort($rows, function (array $a, array $b) {
            return [$a['consultant_name'], $a['project_name'], $a['start_date'] ?? '']
                <=> [$b['consultant_name'], $b['project_name'], $b['start_date'] ?? ''];
        });

        return [$rows, $stats];
    }

    /** Normalisasi filter multi-pilih ("a,b,c") menjadi array lowercase. */
    private function csvFilter($raw): array
    {
        return collect(explode(',', (string) $raw))
            ->map(fn ($v) => mb_strtolower(trim($v)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Bentuk satu baris laporan dari row hasil query.
     *
     * @param  \Illuminate\Support\Collection  $plannedMd
     * @param  \Illuminate\Support\Collection  $actualMd
     */
    private function consultantAssignmentRow(
        object $r,
        string $role,
        $plannedMd,
        $actualMd,
        Carbon $today,
        bool $isFkFallback
    ): array {
        $name = trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? ''));
        $key  = $r->project_id . '|' . $r->employee_id;

        $plannedRow = $plannedMd->get($key);
        $actualRow   = $actualMd->get($key);
        $planned = (float) ($plannedRow->md ?? 0);
        $actual  = (float) ($actualRow->md ?? 0);

        $start = $r->start_date ? Carbon::parse($r->start_date)->format('Y-m-d') : null;
        $end   = $r->end_date   ? Carbon::parse($r->end_date)->format('Y-m-d')   : null;

        // Status penugasan murni turunan tanggal — tidak dipersist, jadi tidak
        // bisa jadi basi. Tanpa start_date tidak ada yang bisa disimpulkan.
        if (!$start) {
            $assignmentStatus = 'No Period';
        } elseif ($start > $today->format('Y-m-d')) {
            $assignmentStatus = 'Upcoming';
        } elseif ($end && $end < $today->format('Y-m-d')) {
            $assignmentStatus = 'Ended';
        } else {
            $assignmentStatus = 'Active';
        }

        $durationDays = ($start && $end)
            ? Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1
            : null;

        return [
            'assignment_id'     => $r->assignment_id !== null ? (int) $r->assignment_id : null,
            'is_fk_fallback'    => $isFkFallback,
            'employee_id'       => (int) $r->employee_id,
            'consultant_name'   => $name !== '' ? $name : ('Employee #' . $r->employee_id),
            'eci'               => (string) ($r->eci ?? ''),
            'position'          => (string) ($r->position ?? ''),
            'division'          => (string) ($r->division ?? ''),
            'department'        => (string) ($r->department ?? ''),
            'home_base'         => (string) ($r->home_base ?? ''),
            'employee_active'   => (bool) ($r->employee_is_active ?? false),
            'project_id'        => (int) $r->project_id,
            'project_name'      => (string) ($r->project_name ?? '-'),
            'io_number'         => (string) ($r->io_number ?? ''),
            'project_type'      => (string) ($r->project_type ?? ''),
            'project_owner'     => (string) ($r->project_owner ?? ''),
            'project_category'  => (string) ($r->project_category ?? ''),
            'project_status'    => (string) ($r->project_status ?? ''),
            'project_phase'     => (string) ($r->project_phase ?? ''),
            'project_closed'    => (bool) ($r->is_closed ?? false),
            'customer_name'     => (string) ($r->customer_name ?? ''),
            'module'            => (string) ($r->module ?? ''),
            'role'              => $role !== '' ? $role : 'Member',
            'employee_type'     => (string) ($r->employee_type ?? 'Internal'),
            'vendor_name'       => (string) ($r->vendor_name ?? ''),
            'start_date'        => $start,
            'end_date'          => $end,
            'duration_days'     => $durationDays,
            'assignment_status' => $assignmentStatus,
            'planned_md'        => round($planned, 2),
            'actual_md'         => round($actual, 2),
            'utilization'       => $planned > 0 ? round($actual / $planned * 100, 1) : null,
            'notes'             => (string) ($r->notes ?? ''),
        ];
    }

    // ── Helper ────────────────────────────────────────────────────────────

    private function monthName(int $month): string
    {
        return Carbon::create(null, $month, 1)->format('F');
    }
}
