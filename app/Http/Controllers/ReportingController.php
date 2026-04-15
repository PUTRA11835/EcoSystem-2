<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use App\Exports\MdRecapExport;
use App\Exports\TimesheetReportExport;
use App\Models\CustomerMandays;
use App\Models\ReportingPeriod;
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
        $sessionUser = session('user');
        if (!$sessionUser) {
            return redirect()->route('login');
        }

        $user = new \stdClass();
        $user->id   = $sessionUser['id'] ?? null;
        $user->name = $sessionUser['name'] ?? $sessionUser['email'] ?? 'Unknown';
        $user->role = new \stdClass();
        $user->role->role_id   = $sessionUser['role']['id'] ?? 0;
        $user->role->role_name = $sessionUser['role']['name'] ?? 'Unknown';

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
            Log::error('currentPeriod error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── API: Close period ─────────────────────────────────────────────────

    public function closePeriod(Request $request)
    {
        try {
            $sessionUser   = session('user');
            $currentRoleId = isset($sessionUser['role']['id']) ? (int) $sessionUser['role']['id'] : null;
            $employeeId    = $sessionUser['id'] ?? null;

            if (!in_array($currentRoleId, [RoleId::ADMIN->value, RoleId::HEAD_OF_SUPPORT->value])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $current = ReportingPeriod::current();

            if ($current['is_closed']) {
                return response()->json(['success' => false, 'message' => 'This period is already closed.'], 422);
            }

            ReportingPeriod::updateOrCreate(
                ['year' => $current['year'], 'month' => $current['month']],
                ['closed_at' => now(), 'closed_by' => $employeeId]
            );

            return response()->json([
                'success' => true,
                'message' => 'Period ' . $this->monthName($current['month']) . ' ' . $current['year'] . ' has been closed.',
            ]);
        } catch (\Exception $e) {
            Log::error('closePeriod error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── API: Timesheet support report ─────────────────────────────────────

    public function timesheetSupport(Request $request)
    {
        try {
            $sessionUser       = session('user');
            $currentEmployeeId = $sessionUser['id'] ?? null;
            $currentRoleId     = isset($sessionUser['role']['id']) ? (int) $sessionUser['role']['id'] : null;

            $query = DB::table('timesheets')
                ->join('ticket',              'timesheets.ticket_id',   '=', 'ticket.ticket_id')
                ->join('employee',            'timesheets.employee_id', '=', 'employee.employee_id')
                ->join('employee_basic_data', 'employee.employee_id',   '=', 'employee_basic_data.employee_id')
                ->leftJoin('customer',            'ticket.customer_id',   '=', 'customer.customer_id')
                ->leftJoin('customer_basic_data', 'customer.customer_id', '=', 'customer_basic_data.customer_id')
                ->where('timesheets.status', 'approved')
                ->whereNotNull('timesheets.ticket_id')
                ->whereNull('timesheets.deleted_at')
                ->select(
                    'timesheets.employee_id',
                    DB::raw("TRIM(CONCAT(COALESCE(employee_basic_data.first_name,''), ' ', COALESCE(employee_basic_data.last_name,''))) as employee_name"),
                    'timesheets.ticket_id',
                    'ticket.ticket_number',
                    DB::raw("COALESCE(customer_basic_data.name_1, '') as customer_name"),
                    DB::raw('SUM(COALESCE(timesheets.md_consumed, 0)) as total_md_consumed')
                )
                ->groupBy(
                    'timesheets.employee_id',
                    'employee_basic_data.first_name',
                    'employee_basic_data.last_name',
                    'timesheets.ticket_id',
                    'ticket.ticket_number',
                    'customer_basic_data.name_1'
                );

            if ($currentRoleId !== RoleId::ADMIN->value && $currentRoleId !== RoleId::HEAD_OF_SUPPORT->value) {
                $query->where('timesheets.employee_id', $currentEmployeeId);
            }

            if ($request->filled('start_date') && $request->filled('end_date')) {
                $query->whereBetween('timesheets.date', [$request->start_date, $request->end_date]);
            }

            $rows = $query->orderByRaw('employee_name')->orderBy('ticket.ticket_number')->get();

            $ticketIds = $rows->pluck('ticket_id')->unique()->values();
            $jatahMap  = [];
            if ($ticketIds->isNotEmpty()) {
                CustomerMandays::whereIn('ticket_id', $ticketIds)
                    ->where('status', 'approved')
                    ->orderBy('version', 'desc')
                    ->get()
                    ->groupBy('ticket_id')
                    ->each(function ($versions, $ticketId) use (&$jatahMap) {
                        $jatahMap[$ticketId] = (float) $versions->first()->total_mandays;
                    });
            }

            $data = $rows->map(function ($row) use ($jatahMap) {
                $jatahMd    = $jatahMap[$row->ticket_id] ?? null;
                $mdConsumed = (float) $row->total_md_consumed;
                $remain     = $jatahMd !== null ? round($jatahMd - $mdConsumed, 2) : null;

                if ($jatahMd === null)            $status = null;
                elseif ($mdConsumed == $jatahMd)  $status = 'Match';
                elseif ($mdConsumed > $jatahMd)   $status = 'Over';
                else                              $status = 'Less';

                return [
                    'employee_id'   => $row->employee_id,
                    'employee_name' => trim($row->employee_name),
                    'ticket_id'     => $row->ticket_id,
                    'ticket_number' => $row->ticket_number,
                    'customer_name' => $row->customer_name,
                    'jatah_md'      => $jatahMd,
                    'md_consumed'   => $mdConsumed,
                    'remain'        => $remain,
                    'status'        => $status,
                ];
            });

            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {
            Log::error('Reporting timesheetSupport: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── Web: Export Excel ─────────────────────────────────────────────────

    public function exportExcel(Request $request)
    {
        try {
            $sessionUser       = session('user');
            $currentEmployeeId = $sessionUser['id'] ?? null;
            $currentRoleId     = isset($sessionUser['role']['id']) ? (int) $sessionUser['role']['id'] : null;

            if (!in_array($currentRoleId, [RoleId::ADMIN->value, RoleId::HEAD_OF_SUPPORT->value])) {
                abort(403);
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
                ->orderByRaw('employee_name')
                ->orderBy('ticket.ticket_number')
                ->orderBy('timesheets.date')
                ->get();

            // Batch-fetch jatah MD per ticket
            $ticketIds = $rows->pluck('ticket_id')->unique()->values();
            $jatahMap  = [];
            if ($ticketIds->isNotEmpty()) {
                CustomerMandays::whereIn('ticket_id', $ticketIds)
                    ->where('status', 'approved')
                    ->orderBy('version', 'desc')
                    ->get()
                    ->groupBy('ticket_id')
                    ->each(function ($versions, $ticketId) use (&$jatahMap) {
                        $jatahMap[$ticketId] = (float) $versions->first()->total_mandays;
                    });
            }

            // Compute per-(employee, ticket) totals for status column
            $totalMap = [];
            foreach ($rows as $r) {
                $key = $r->employee_id . '_' . $r->ticket_id;
                $totalMap[$key] = ($totalMap[$key] ?? 0) + (float) ($r->md_consumed ?? 0);
            }

            $exportRows = $rows->map(function ($r) use ($jatahMap, $totalMap, $periodYear, $periodMonth) {
                $jatahMd    = $jatahMap[$r->ticket_id] ?? null;
                $mdConsumed = (float) ($r->md_consumed ?? 0);
                $totalConsumed = $totalMap[$r->employee_id . '_' . $r->ticket_id] ?? $mdConsumed;

                if ($jatahMd === null)                 $status = null;
                elseif ($totalConsumed == $jatahMd)    $status = 'Match';
                elseif ($totalConsumed > $jatahMd)     $status = 'Over';
                else                                   $status = 'Less';

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
            });

            $monthName = $this->monthName($periodMonth);
            $filename  = "Timesheet_Report_{$monthName}_{$periodYear}.xlsx";

            return Excel::download(
                new TimesheetReportExport(collect($exportRows), $periodYear, $periodMonth),
                $filename
            );

        } catch (\Exception $e) {
            Log::error('exportExcel error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── Web: MD Recap page ────────────────────────────────────────────────

    public function mdRecapIndex()
    {
        $sessionUser = session('user');
        if (!$sessionUser) return redirect()->route('login');

        $roleId = (int) ($sessionUser['role']['id'] ?? 0);
        if (!in_array($roleId, [RoleId::ADMIN->value, RoleId::HEAD_OF_SUPPORT->value])) {
            abort(403);
        }

        return view('reporting.md-recap', ['user' => $sessionUser]);
    }

    // ── API: MD Recap data ────────────────────────────────────────────────

    public function mdRecap(Request $request)
    {
        try {
            $sessionUser   = session('user');
            $currentRoleId = isset($sessionUser['role']['id']) ? (int) $sessionUser['role']['id'] : null;

            if (!in_array($currentRoleId, [RoleId::ADMIN->value, RoleId::HEAD_OF_SUPPORT->value])) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $month = $request->filled('month') ? (int) $request->month : now()->month;
            $year  = $request->filled('year')  ? (int) $request->year  : now()->year;

            $rows = DB::table('timesheets')
                ->join('employee',            'timesheets.employee_id', '=', 'employee.employee_id')
                ->join('employee_basic_data', 'employee.employee_id',   '=', 'employee_basic_data.employee_id')
                ->where('timesheets.status', 'approved')
                ->whereNull('timesheets.deleted_at')
                ->whereMonth('timesheets.date', $month)
                ->whereYear('timesheets.date', $year)
                ->whereNotNull('timesheets.presence')
                ->select(
                    DB::raw("TRIM(CONCAT(COALESCE(employee_basic_data.first_name,''), ' ', COALESCE(employee_basic_data.last_name,''))) as employee_name"),
                    DB::raw("CASE WHEN LOWER(timesheets.presence) = 'onsite' THEN 'OnSite' ELSE 'Remote' END as mode"),
                    DB::raw('SUM(COALESCE(timesheets.md_consumed, timesheets.duration_minutes / 480.0, 0)) as mandays')
                )
                ->groupBy('employee_basic_data.first_name', 'employee_basic_data.last_name', 'mode')
                ->orderByRaw('employee_name')
                ->orderBy('mode')
                ->get();

            $data = $rows->map(fn($r) => [
                'name'    => trim($r->employee_name),
                'mode'    => $r->mode,
                'mandays' => round((float) $r->mandays, 2),
            ]);

            return response()->json(['success' => true, 'data' => $data]);

        } catch (\Exception $e) {
            Log::error('mdRecap error: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // ── Web: MD Recap export ──────────────────────────────────────────────

    public function exportMdRecap(Request $request)
    {
        try {
            $sessionUser   = session('user');
            $currentRoleId = isset($sessionUser['role']['id']) ? (int) $sessionUser['role']['id'] : null;

            if (!in_array($currentRoleId, [RoleId::ADMIN->value, RoleId::HEAD_OF_SUPPORT->value])) {
                abort(403);
            }

            $month = $request->filled('month') ? (int) $request->month : now()->month;
            $year  = $request->filled('year')  ? (int) $request->year  : now()->year;

            $rows = DB::table('timesheets')
                ->join('employee',            'timesheets.employee_id', '=', 'employee.employee_id')
                ->join('employee_basic_data', 'employee.employee_id',   '=', 'employee_basic_data.employee_id')
                ->where('timesheets.status', 'approved')
                ->whereNull('timesheets.deleted_at')
                ->whereMonth('timesheets.date', $month)
                ->whereYear('timesheets.date', $year)
                ->whereNotNull('timesheets.presence')
                ->select(
                    DB::raw("TRIM(CONCAT(COALESCE(employee_basic_data.first_name,''), ' ', COALESCE(employee_basic_data.last_name,''))) as employee_name"),
                    DB::raw("CASE WHEN LOWER(timesheets.presence) = 'onsite' THEN 'OnSite' ELSE 'Remote' END as mode"),
                    DB::raw('SUM(COALESCE(timesheets.md_consumed, timesheets.duration_minutes / 480.0, 0)) as mandays')
                )
                ->groupBy('employee_basic_data.first_name', 'employee_basic_data.last_name', 'mode')
                ->orderByRaw('employee_name')
                ->orderBy('mode')
                ->get();

            $exportRows = $rows->map(fn($r) => [
                'name'    => trim($r->employee_name),
                'mode'    => $r->mode,
                'mandays' => round((float) $r->mandays, 2),
            ]);

            $filename = "MD_Recap_{$this->monthName($month)}_{$year}.xlsx";

            return Excel::download(new MdRecapExport(collect($exportRows), $month, $year), $filename);

        } catch (\Exception $e) {
            Log::error('exportMdRecap error: ' . $e->getMessage());
            abort(500, $e->getMessage());
        }
    }

    // ── Helper ────────────────────────────────────────────────────────────

    private function monthName(int $month): string
    {
        return Carbon::create(null, $month, 1)->format('F');
    }
}
