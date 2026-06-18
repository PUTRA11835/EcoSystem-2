<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use App\Exports\MdRecapExport;
use App\Exports\TimesheetReportExport;
use App\Models\ConsultantMandays;
use App\Models\ConsultantMandaysDetail;
use App\Models\CustomerMandays;
use App\Models\ReportingPeriod;
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
            $current = ReportingPeriod::current();
            $range   = ReportingPeriod::dateRange($current['year'], $current['month']);

            return response()->json([
                'success' => true,
                'data'    => [
                    'year'       => $current['year'],
                    'month'      => $current['month'],
                    'is_closed'  => $current['is_closed'],
                    'closed_at'  => $current['closed_at'],
                    'start_date' => $range['start']->format('Y-m-d'),
                    'end_date'   => $range['end']->format('Y-m-d'),
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
            $sessionUser   = session('user');
            $currentRoleId = isset($sessionUser['role']['id']) ? (int) $sessionUser['role']['id'] : null;
            $employeeId    = (int) ($sessionUser['id'] ?? 0);

            // Only RPMO can close globally via the new system
            if ($currentRoleId !== RoleId::DELIVERY_RPMO_HEAD->value) {
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
            $svc->closeGlobal($period, $employeeId, $currentRoleId);

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
            $sessionUser       = session('user');
            $currentEmployeeId = $sessionUser['id'] ?? null;
            $currentRoleId     = isset($sessionUser['role']['id']) ? (int) $sessionUser['role']['id'] : null;

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

            if ($currentRoleId !== RoleId::EC_ADMINISTRATOR->value && $currentRoleId !== RoleId::DELIVERY_SUPPORT_HEAD->value) {
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
            $sessionUser       = session('user');
            $currentEmployeeId = $sessionUser['id'] ?? null;
            $currentRoleId     = isset($sessionUser['role']['id']) ? (int) $sessionUser['role']['id'] : null;

            if (!in_array($currentRoleId, [RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_SUPPORT_HEAD->value])) {
                abort(403, 'Access denied. Only Admins and Head of Support can export reports.');
            }

            // Determine period for export
            if ($request->filled('year') && $request->filled('month')) {
                $periodYear  = (int) $request->year;
                $periodMonth = (int) $request->month;
            } else {
                $current     = ReportingPeriod::current();
                $periodYear  = $current['year'];
                $periodMonth = $current['month'];
            }

            $range = ReportingPeriod::dateRange($periodYear, $periodMonth);

            // Fetch individual approved support timesheets (one row per timesheet)
            $rows = DB::table('timesheets')
                ->join('ticket',              'timesheets.ticket_id',   '=', 'ticket.ticket_id')
                ->join('employee',            'timesheets.employee_id', '=', 'employee.employee_id')
                ->join('employee_basic_data', 'employee.employee_id',   '=', 'employee_basic_data.employee_id')
                ->leftJoin('customer',            'ticket.customer_id',   '=', 'customer.customer_id')
                ->leftJoin('customer_basic_data', 'customer.customer_id', '=', 'customer_basic_data.customer_id')
                ->where('timesheets.status', 'approved')
                ->whereNotNull('timesheets.ticket_id')
                ->whereNull('timesheets.deleted_at')
                ->whereBetween('timesheets.date', [
                    $range['start']->format('Y-m-d'),
                    $range['end']->format('Y-m-d'),
                ])
                ->select(
                    'timesheets.id',
                    'timesheets.employee_id',
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
            $exportRows = $rows->map(function ($r) use ($jatahMap, $periodYear, $periodMonth, &$runningTotals) {
                $jatahMd    = $jatahMap[$r->ticket_id . '_' . $r->employee_id] ?? null;
                $mdConsumed = (float) ($r->md_consumed ?? 0);

                $key = $r->ticket_id . '_' . $r->employee_id;
                $runningTotals[$key] = ($runningTotals[$key] ?? 0) + $mdConsumed;
                $cumulative = $runningTotals[$key];

                if ($jatahMd === null)           $status = null;
                elseif ($cumulative == $jatahMd) $status = 'Match';
                elseif ($cumulative > $jatahMd)  $status = 'Over';
                else                             $status = 'Less';

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
                    'status'        => $status,
                ];
            })->reverse()->values(); // newest first, matching view order

            $monthName = $this->monthName($periodMonth);
            $filename  = "Timesheet_Report_{$monthName}_{$periodYear}.xlsx";

            return Excel::download(
                new TimesheetReportExport(collect($exportRows), $periodYear, $periodMonth),
                $filename
            );

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

        $roleId = (int) ($sessionUser['role']['id'] ?? 0);
        if (!in_array($roleId, [RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_SUPPORT_HEAD->value])) {
            abort(403, 'Access denied. Only Admins and Head of Support can view the MD recap.');
        }

        return view('reporting.md-recap', ['user' => $sessionUser]);
    }

    // ── API: MD Recap data ────────────────────────────────────────────────

    public function mdRecap(Request $request)
    {
        try {
            $sessionUser   = session('user');
            $currentRoleId = isset($sessionUser['role']['id']) ? (int) $sessionUser['role']['id'] : null;

            if (!in_array($currentRoleId, [RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_SUPPORT_HEAD->value])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $month = $request->filled('month') ? (int) $request->month : now()->month;
            $year  = $request->filled('year')  ? (int) $request->year  : now()->year;

            $range = ReportingPeriod::dateRange($year, $month);

            $rows = DB::table('timesheets')
                ->join('employee',            'timesheets.employee_id', '=', 'employee.employee_id')
                ->join('employee_basic_data', 'employee.employee_id',   '=', 'employee_basic_data.employee_id')
                ->where('timesheets.status', 'approved')
                ->whereNull('timesheets.deleted_at')
                ->whereBetween('timesheets.date', [
                    $range['start']->format('Y-m-d'),
                    $range['end']->format('Y-m-d'),
                ])
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
            $currentRoleId = isset($sessionUser['role']['id']) ? (int) $sessionUser['role']['id'] : null;

            if (!in_array($currentRoleId, [RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_SUPPORT_HEAD->value])) {
                abort(403, 'Access denied. Only Admins and Head of Support can export the MD recap.');
            }

            $month = $request->filled('month') ? (int) $request->month : now()->month;
            $year  = $request->filled('year')  ? (int) $request->year  : now()->year;

            $range = ReportingPeriod::dateRange($year, $month);

            $rows = DB::table('timesheets')
                ->join('employee',            'timesheets.employee_id', '=', 'employee.employee_id')
                ->join('employee_basic_data', 'employee.employee_id',   '=', 'employee_basic_data.employee_id')
                ->where('timesheets.status', 'approved')
                ->whereNull('timesheets.deleted_at')
                ->whereBetween('timesheets.date', [
                    $range['start']->format('Y-m-d'),
                    $range['end']->format('Y-m-d'),
                ])
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

            $filename = "MD_Recap_{$this->monthName($month)}_{$year}.xlsx";

            return Excel::download(new MdRecapExport(collect($exportRows), $month, $year), $filename);

        } catch (\Exception $e) {
            Log::error('exportMdRecap error');
            abort(500, $e->getMessage());
        }
    }

    // ── Helper ────────────────────────────────────────────────────────────

    private function monthName(int $month): string
    {
        return Carbon::create(null, $month, 1)->format('F');
    }
}
