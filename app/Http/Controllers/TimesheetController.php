<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use App\Exports\TimesheetApprovalExport;
use App\Exports\TimesheetSupportExport;
use App\Exports\TimesheetProjectExport;
use App\Exports\TimesheetOfficeExport;
use App\Models\Timesheet;
use App\Support\SessionUser;
use App\Models\ConsultantMandays;
use App\Models\ConsultantMandaysDetail;
use App\Models\CustomerMandays;
use App\Models\DeliveryProject;
use App\Models\DeliveryProjectActivity;
use App\Models\DeliverySupportActivity;
use App\Models\Employee;
use App\Models\Grade;
use App\Models\Notification;
use App\Models\ReportingPeriod;
use App\Models\Ticket;
use App\Services\PeriodService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class TimesheetController extends Controller
{
    /**
     * Get submitted timesheets for approval (for Head of Project/Head of Support)
     */
    public function submittedForApproval(Request $request)
    {
        try {
            $user = SessionUser::fromSession(session('user'));

            // Admin, Head of Project, Head of Support, and RPMO can access this
            $approvalRoles = array_merge([RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_RPMO_HEAD->value], RoleId::HEAD_GROUP);
            if (!$user->hasAnyRole($approvalRoles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $query = Timesheet::with(['employee.basicData', 'activity.delivery_project', 'delivery_project', 'approver.basicData', 'ticket.customer.basicData', 'ticket'])
                ->whereIn('status', ['draft', 'submitted', 'approved', 'rejected']);

            // Filter by date range if provided
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->dateRange($request->start_date, $request->end_date);
            }

            // Filter by status if provided
            if ($request->has('status')) {
                $query->byStatus($request->status);
            }

            // Filter by timesheet type if provided
            if ($request->has('type_filter')) {
                $type = $request->type_filter;
                if ($type === 'support') {
                    $query->whereNotNull('ticket_id');
                } elseif ($type === 'project') {
                    $query->whereNotNull('delivery_projects_id');
                } elseif ($type === 'office') {
                    $query->whereNull('ticket_id')->whereNull('delivery_projects_id');
                }
            }

            $rows = $query->orderBy('date', 'desc')
                          ->orderBy('created_at', 'desc')
                          ->get();

            // Batch-fetch per-employee quota from the latest approved consultant mandays detail
            $ticketIds = $rows->pluck('ticket_id')->filter()->unique()->values();
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
                            $key = $ticketId . '_' . $detail->employee_id;
                            $jatahMap[$key] = round((float)($detail->approved_mandays ?? 0) + (float)($detail->approved_additional ?? 0), 2);
                        }
                    });
            }

            $timesheets = $rows->map(function ($timesheet) use ($jatahMap) {
                                    return [
                                        'id' => $timesheet->id,
                                        'employee_id' => $timesheet->employee_id,
                                        'employee_name' => trim($timesheet->employee?->basicData?->first_name . ' ' . $timesheet->employee?->basicData?->last_name),
                                        'date' => $timesheet->date?->format('Y-m-d'),
                                        'activity_date' => $timesheet->activity_date?->format('Y-m-d'),
                                        'start_time' => $timesheet->start_time,
                                        'end_time' => $timesheet->end_time,
                                        'duration_minutes' => $timesheet->duration_minutes,
                                        'duration_hours' => round($timesheet->duration_minutes / 60, 2),
                                        'description' => $timesheet->description,
                                        'activity_type' => $timesheet->activity_type,
                                        'ticket_id' => $timesheet->ticket_id,
                                        'ticket_number' => $timesheet->ticket?->ticket_number,
                                        'ticket_description' => $timesheet->ticket?->description,
                                        'customer_name' => $timesheet->ticket?->customer?->basicData?->name_1,
                                        'ticket_type' => $timesheet->ticket?->ticket_type,
                                        'jatah_md' => $timesheet->ticket_id ? ($jatahMap[$timesheet->ticket_id . '_' . $timesheet->employee_id] ?? null) : null,
                                        'md_consumed' => $timesheet->md_consumed,
                                        'presence' => $timesheet->presence,
                                        'location' => $timesheet->location,
                                        'delivery_projects_id' => $timesheet->delivery_projects_id,
                                        'activity_id' => $timesheet->activity_id,
                                        'activity_name' => $timesheet->activity?->name,
                                        'project_name' => $timesheet->activity?->delivery_project?->name ?? $timesheet->delivery_project?->name,
                                        'status' => $timesheet->status,
                                        'is_billable' => $timesheet->is_billable,
                                        'rejection_reason' => $timesheet->rejection_reason,
                                        'approved_by' => $timesheet->approved_by,
                                        'approver_name' => trim($timesheet->approver?->basicData?->first_name . ' ' . $timesheet->approver?->basicData?->last_name),
                                        'approved_at' => $timesheet->approved_at?->format('Y-m-d H:i:s'),
                                        'created_at' => $timesheet->created_at?->format('Y-m-d H:i:s'),
                                        'period_year'  => $timesheet->period_year,
                                        'period_month' => $timesheet->period_month,
                                    ];
                                });

            return response()->json([
                'success' => true,
                'data' => $timesheets,
                'message' => 'Submitted timesheets retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving submitted timesheets: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve submitted timesheets'
            ], 500);
        }
    }

    /**
     * Export timesheets to Excel (Head & above).
     *
     * Exports exactly the rows the caller currently has on screen — the frontend
     * sends the ids of its already-filtered/sorted table (`filteredTimesheets`),
     * so there is no separate filtering logic to keep in sync with the table's
     * column filters, stat-card status, date range, sort, etc. `type_filter`
     * only decides which column layout to use (it mirrors whichever type tab is
     * active), not which rows to include — that's already been decided by `ids`.
     *
     * POST /api/timesheets/export  { ids: number[], type_filter: '' | 'support' | 'project' | 'office' }
     */
    public function exportToExcel(Request $request)
    {
        $sessionUser = SessionUser::fromSession(session('user'));
        // Checked against ALL assigned roles, not just whichever role is "primary"
        $allowed = array_merge([RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_RPMO_HEAD->value], RoleId::HEAD_GROUP);
        if (!$sessionUser || !$sessionUser->hasAnyRole($allowed)) {
            abort(403);
        }

        $ids = array_map('intval', (array) $request->input('ids', []));
        if (empty($ids)) {
            abort(422, 'No timesheets to export.');
        }

        $type = $request->input('type_filter', '');

        $rows = Timesheet::with(['employee.basicData', 'ticket.customer.basicData', 'activity.delivery_project', 'delivery_project', 'approver.basicData'])
            ->whereIn('id', $ids)
            ->whereNull('deleted_at')
            ->get();

        // whereIn() doesn't preserve order — reorder to match the on-screen
        // sort/order the frontend sent.
        $order = array_flip($ids);
        $rows  = $rows->sortBy(fn ($r) => $order[$r->id] ?? PHP_INT_MAX)->values();

        $filename = 'TIMESHEET_' . now()->timezone('Asia/Jakarta')->format('dmY') . '.xlsx';

        if ($type === 'support') {
            $ticketIds = $rows->pluck('ticket_id')->filter()->unique()->values();

            // Per-employee quota MD — same batch lookup used by index()/submittedForApproval().
            $jatahMap = [];
            if ($ticketIds->isNotEmpty()) {
                $latestCMs = ConsultantMandays::whereIn('ticket_id', $ticketIds)
                    ->where('status', 'approved')
                    ->orderBy('approved_at', 'desc')
                    ->get()
                    ->groupBy('ticket_id')
                    ->map(fn ($g) => $g->first());

                $cmIdToTicketId = $latestCMs->mapWithKeys(fn ($cm) => [$cm->id => $cm->ticket_id]);

                ConsultantMandaysDetail::whereIn('consultant_mandays_id', $cmIdToTicketId->keys())
                    ->get()
                    ->each(function ($detail) use (&$jatahMap, $cmIdToTicketId) {
                        $ticketId = $cmIdToTicketId[$detail->consultant_mandays_id] ?? null;
                        if ($ticketId) {
                            $key = $ticketId . '_' . $detail->employee_id;
                            $jatahMap[$key] = round((float) ($detail->approved_mandays ?? 0) + (float) ($detail->approved_additional ?? 0), 2);
                        }
                    });
            }

            $deliveryMap = DeliverySupportActivity::with(['deliverySupport.client.basicData'])
                ->whereIn('ticket_id', $ticketIds)
                ->whereNotNull('ticket_id')
                ->get()
                ->keyBy('ticket_id')
                ->map(function ($activity) {
                    $ds = $activity->deliverySupport;
                    if (!$ds) {
                        return null;
                    }
                    $clientName = $ds->client?->basicData?->name_1;
                    return trim($ds->name . ($clientName ? " ({$clientName})" : '') . ($ds->type ? ", {$ds->type}" : ''));
                });

            return Excel::download(new TimesheetSupportExport($rows, $jatahMap, $deliveryMap), $filename);
        }

        if ($type === 'project') {
            return Excel::download(new TimesheetProjectExport($rows), $filename);
        }

        if ($type === 'office') {
            return Excel::download(new TimesheetOfficeExport($rows), $filename);
        }

        return Excel::download(new TimesheetApprovalExport($rows), $filename);
    }

    /**
     * Get remaining MD quota for a ticket for the current user.
     * GET /api/timesheets/remaining-md?ticket_id=X
     */
    public function remainingMd(Request $request)
    {
        $data       = $request->validate(['ticket_id' => 'required|integer']);
        $ticketId   = (int) $data['ticket_id'];
        $sessionUser = session('user');
        $employeeId  = $sessionUser['id'] ?? null;

        if (!$employeeId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // Step 1: find the latest approved ConsultantMandays for this ticket.
        $latestApproved = ConsultantMandays::where('ticket_id', $ticketId)
            ->where('status', 'approved')
            ->orderBy('approved_at', 'desc')
            ->first();

        // Step 2: find this employee's detail row in that approved proposal.
        $quotaDetail = $latestApproved
            ? ConsultantMandaysDetail::where('consultant_mandays_id', $latestApproved->id)
                ->where('employee_id', $employeeId)
                ->first()
            : null;

        // Quota = employee's Head-approved days + any approved additional granted by Head.
        $quota = $quotaDetail
            ? round((float) ($quotaDetail->approved_mandays ?? 0) + (float) ($quotaDetail->approved_additional ?? 0), 2)
            : null;

        // Total MD consumed by this employee for this ticket.
        // Draft timesheets count — they represent planned/in-progress work.
        // Rejected timesheets return their MD to the quota — exclude them.
        $consumed = (float) Timesheet::where('ticket_id', $ticketId)
            ->where('employee_id', $employeeId)
            ->whereIn('status', ['draft', 'submitted', 'approved'])
            ->sum('md_consumed');

        $remaining = $quota !== null ? round($quota - $consumed, 2) : null;

        return response()->json([
            'success' => true,
            'data'    => [
                'ticket_id'  => $ticketId,
                'quota'      => $quota,
                'consumed'   => round($consumed, 2),
                'remaining'  => $remaining,
                'employee_id' => $employeeId,
            ],
        ]);
    }

    /**
     * Get the current user's approved (not expired) late exception requests.
     * GET /api/timesheets/my-late-exceptions
     */
    public function myLateExceptions()
    {
        $sessionUser = session('user');
        $employeeId  = $sessionUser['id'] ?? null;

        if (!$employeeId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $requests = \App\Models\PeriodLateExceptionRequest::where('employee_id', $employeeId)
            ->where('status', 'approved')
            ->where('expires_at', '>', now())
            ->with('period')
            ->get()
            ->map(fn($r) => [
                'id'           => $r->id,
                'domain'       => $r->domain,
                'period_id'    => $r->period_id,
                'period_year'  => $r->period?->year,
                'period_month' => $r->period?->month,
                'period_label' => $r->period?->getLabel(),
                'period_start' => $r->period?->start_date?->format('Y-m-d'),
                'period_end'   => $r->period?->end_date?->format('Y-m-d'),
                'expires_at'   => $r->expires_at?->format('d M Y, H:i'),
            ]);

        return response()->json(['success' => true, 'data' => $requests]);
    }

    /**
     * Return all periods valid for timesheet input for the current user:
     *   - Current period (if open for the user's domain)
     *   - Previous period (if open for the user's domain)
     *   - Approved late exception periods (access not expired)
     * GET /api/timesheets/valid-periods
     */
    public function validPeriods()
    {
        $sessionUser = session('user');
        $employeeId  = $sessionUser['id'] ?? null;
        $roleId      = isset($sessionUser['role']['id']) ? (int) $sessionUser['role']['id'] : null;

        if (!$employeeId) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        /** @var PeriodService $svc */
        $svc    = app(PeriodService::class);
        $domain = $svc->getDomainForRole($roleId ?? 0);

        // Roles without a domain (Admin, RPMO, etc.) — return just the current active period
        if ($domain === null) {
            $active = ReportingPeriod::getActive();
            return response()->json([
                'success' => true,
                'data'    => [
                    'active_window'      => $active ? [[
                        'id'           => $active->id,
                        'label'        => $active->getLabel(),
                        'start_date'   => $active->start_date?->format('Y-m-d'),
                        'end_date'     => $active->end_date?->format('Y-m-d'),
                        'global_status'=> $active->global_status,
                        'type'         => 'current',
                    ]] : [],
                    'late_exceptions'    => [],
                ],
            ]);
        }

        // Active window periods
        $windowPeriods = $svc->getActiveWindowPeriods();
        $now           = now();
        $curCoords     = ReportingPeriod::periodFor($now);

        $activeWindow = collect($windowPeriods)->map(function ($p) use ($curCoords, $domain) {
            $isCurrent = $p->year == $curCoords['year'] && $p->month == $curCoords['month'];
            return [
                'id'            => $p->id,
                'label'         => $p->getLabel(),
                'start_date'    => $p->start_date?->format('Y-m-d'),
                'end_date'      => $p->end_date?->format('Y-m-d'),
                'global_status' => $p->global_status,
                'domain_status' => $p->domainStatus($domain),
                'type'          => $isCurrent ? 'current' : 'previous',
            ];
        })->values();

        // Approved unexpired late exception requests
        $lateExceptions = \App\Models\PeriodLateExceptionRequest::where('employee_id', $employeeId)
            ->where('domain', $domain)
            ->where('status', 'approved')
            ->where('expires_at', '>', $now)
            ->with('period')
            ->get()
            ->map(fn($r) => [
                'id'           => $r->id,
                'period_id'    => $r->period_id,
                'label'        => $r->period?->getLabel(),
                'start_date'   => $r->period?->start_date?->format('Y-m-d'),
                'end_date'     => $r->period?->end_date?->format('Y-m-d'),
                'expires_at'     => $r->expires_at?->setTimezone(config('app.timezone'))->format('d M Y, H:i'),
                'expires_at_iso' => $r->expires_at?->setTimezone(config('app.timezone'))->toIso8601String(),
                'type'         => 'late_exception',
            ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'active_window'   => $activeWindow,
                'late_exceptions' => $lateExceptions,
            ],
        ]);
    }

    /**
     * Get all timesheets (API)
     */
    public function index(Request $request)
    {
        try {
            $sessionUser       = SessionUser::fromSession(session('user'));
            $currentEmployeeId = $sessionUser->id;
            $roleIds           = $sessionUser->role_ids;

            // Load employee, activity, and ticket (with customer) relationships
            $query = Timesheet::with(['employee.basicData', 'activity', 'ticket.customer.basicData']);

            // ── Visibility filter ─────────────────────────────────────────────
            // Admin sees everything. Others see own timesheets + additional scope per role:
            //   Head of Support  → also sees all support timesheets (ticket_id IS NOT NULL)
            //   Head of Project  → also sees all project timesheets (delivery_projects_id IS NOT NULL)
            //   RPMO             → also sees all office timesheets (both NULL)
            // Multi-role: scopes are additive (union of all roles' access).
            if (!$sessionUser->hasRole(RoleId::EC_ADMINISTRATOR->value)) {
                $query->where(function ($q) use ($currentEmployeeId, $roleIds) {
                    $q->where('employee_id', $currentEmployeeId);

                    if (in_array(RoleId::DELIVERY_SUPPORT_HEAD->value, $roleIds, true)) {
                        $q->orWhereNotNull('ticket_id');
                    }
                    if (in_array(RoleId::DELIVERY_PROJECT_HEAD->value, $roleIds, true)) {
                        $q->orWhereNotNull('delivery_projects_id');
                    }
                    if (in_array(RoleId::DELIVERY_RPMO_HEAD->value, $roleIds, true)) {
                        $q->orWhere(function ($inner) {
                            $inner->whereNull('ticket_id')->whereNull('delivery_projects_id');
                        });
                    }
                });
            }

            // Filter by employee if provided (override, for admin use)
            if ($request->has('employee_id')) {
                $query->forEmployee($request->employee_id);
            }

            // Filter by date range if provided
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->dateRange($request->start_date, $request->end_date);
            }

            // Filter by status if provided
            if ($request->has('status')) {
                $query->byStatus($request->status);
            }

            // Filter by project if provided (column still exists in DB)
            if ($request->has('delivery_projects_id')) {
                $query->forProject($request->delivery_projects_id);
            }

            $rows = $query->orderBy('date', 'desc')
                          ->orderBy('created_at', 'desc')
                          ->get();

            // Fetch per-employee quota from the latest approved consultant mandays detail
            $ticketIds = $rows->whereNotNull('ticket_id')->pluck('ticket_id')->unique()->values();
            $approvedMandaysMap = []; // keyed as "ticketId_employeeId"
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
                    ->each(function ($detail) use (&$approvedMandaysMap, $cmIdToTicketId) {
                        $ticketId = $cmIdToTicketId[$detail->consultant_mandays_id] ?? null;
                        if ($ticketId) {
                            $key = $ticketId . '_' . $detail->employee_id;
                            $approvedMandaysMap[$key] = round((float)($detail->approved_mandays ?? 0) + (float)($detail->approved_additional ?? 0), 2);
                        }
                    });
            }

            $timesheets = $rows->map(function ($t) use ($approvedMandaysMap) {
                return [
                    'id'                   => $t->id,
                    'employee_id'          => $t->employee_id,
                    'employee_name'        => trim(($t->employee?->basicData?->first_name ?? '') . ' ' . ($t->employee?->basicData?->last_name ?? '')),
                    'delivery_projects_id' => $t->delivery_projects_id,
                    'ticket_id'            => $t->ticket_id,
                    'ticket_number'        => $t->ticket?->ticket_number,
                    'ticket_description'   => $t->ticket?->description,
                    'customer_name'        => $t->ticket?->customer?->basicData?->name_1,
                    'ticket_type'          => $t->ticket?->ticket_type,
                    'jatah_md'             => $t->ticket_id ? ($approvedMandaysMap[$t->ticket_id . '_' . $t->employee_id] ?? null) : null,
                    'activity_id'          => $t->activity_id,
                    'activity'             => $t->activity ? ['id' => $t->activity->id, 'name' => $t->activity->name] : null,
                    'date'                 => $t->date?->format('Y-m-d'),
                    'activity_date'        => $t->activity_date?->format('Y-m-d'),
                    'start_time'           => $t->start_time,
                    'end_time'             => $t->end_time,
                    'duration_minutes'     => $t->duration_minutes,
                    'description'          => $t->description,
                    'notes'                => $t->notes,
                    'activity_type'        => $t->activity_type,
                    'status'               => $t->status,
                    'rejection_reason'     => $t->rejection_reason,
                    'is_billable'          => $t->is_billable,
                    'presence'             => $t->presence,
                    'location'             => $t->location,
                    'md_consumed'          => $t->md_consumed,
                    'approved_by'          => $t->approved_by,
                    'approved_at'          => $t->approved_at?->format('Y-m-d H:i:s'),
                    'created_at'           => $t->created_at?->format('Y-m-d H:i:s'),
                    'period_year'          => $t->period_year,
                    'period_month'         => $t->period_month,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $timesheets,
                'message' => 'Timesheets retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving timesheets: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve timesheets'
            ], 500);
        }
    }

    /**
     * Get single timesheet (API)
     */
    public function show($id)
    {
        try {
            $timesheet   = Timesheet::with(['employee', 'activity'])->findOrFail($id);
            $sessionUser = session('user');
            $roleId      = $sessionUser['role']['id'] ?? 0;
            $sessionEmpId = $sessionUser['id'] ?? null;

            // Admins, Head of Support, Head of Project, and Helpdesk can view any timesheet.
            // All other roles may only view their own.
            $privileged = in_array($roleId, [
                RoleId::EC_ADMINISTRATOR->value,
                RoleId::DELIVERY_SUPPORT_HEAD->value,
                RoleId::DELIVERY_PROJECT_HEAD->value,
                RoleId::DELIVERY_HELPDESK->value,
            ], true);

            if (!$privileged && (int) $timesheet->employee_id !== (int) $sessionEmpId) {
                return response()->json([
                    'success' => false,
                    'message' => 'You are not authorised to view this timesheet.',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data'    => $timesheet,
                'message' => 'Timesheet retrieved successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving timesheet: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Timesheet not found',
            ], 404);
        }
    }

    /**
     * Store new timesheet (API)
     */
    public function store(Request $request)
    {
        try {
            Log::info('Creating timesheet with data:', $request->all());
            
            // Base validation
            $rules = [
                'employee_id' => 'required|exists:employee,employee_id',
                'date' => 'required|date|before_or_equal:today',
                'activity_date' => 'nullable|date',
                'start_time' => 'required',
                'end_time' => 'required|after:start_time',
                'description' => 'required|string',
                'notes' => 'nullable|string',
                'activity_type' => 'required|in:development,meeting,documentation,testing,support,training,other',
                'is_billable' => 'sometimes|boolean',
                'presence' => 'nullable|string',
                'location' => 'nullable|string',
                'md_consumed' => 'nullable|numeric|min:0',
            ];

            // Conditional validation based on what's provided
            if ($request->filled('delivery_projects_id')) {
                $rules['delivery_projects_id'] = 'nullable|integer';
                $rules['activity_id'] = 'nullable|exists:delivery_project_activities,id'; // Activity validation
                $rules['ticket_id'] = 'nullable';
            } elseif ($request->filled('ticket_id')) {
                $rules['ticket_id'] = 'nullable|integer';
                $rules['delivery_projects_id'] = 'nullable';
                // Activity date has no window restriction (unlike `date`, the submit date) —
                // it just needs to be a real date, tracking when the work actually happened.
                $rules['activity_date'] = 'required|date';
            }

            $validated = $request->validate($rules);

            $validated['status'] = 'draft';

            // Ensure is_billable has default value
            if (!isset($validated['is_billable'])) {
                $validated['is_billable'] = true;
            }

            // Add presence, location, md_consumed from request
            $validated['presence'] = $request->input('presence');
            $validated['location'] = $request->input('location');
            $validated['md_consumed'] = $request->input('md_consumed');

            // Ensure only one of project_id or ticket_id is set
            if (!empty($validated['delivery_projects_id'])) {
                $validated['ticket_id'] = null;
                // Keep activity_id if provided
                $validated['activity_id'] = $request->input('activity_id') ?: null;
            } elseif (!empty($validated['ticket_id'])) {
                $validated['delivery_projects_id'] = null;
                $validated['activity_id'] = null; // No activity for support tickets
            } else {
                $validated['delivery_projects_id'] = null;
                $validated['ticket_id'] = null;
                $validated['activity_id'] = null;
            }

            // ── Resolution Days quota guard ───────────────────────────────────
            if (!empty($validated['ticket_id'])) {
                $quotaError = $this->checkResolutionQuota(
                    (int) $validated['ticket_id'],
                    (int) $validated['employee_id'],
                    (float) ($validated['md_consumed'] ?? 0)
                );
                if ($quotaError) {
                    return response()->json(['success' => false, 'message' => $quotaError], 422);
                }

                $levelError = $this->checkConsultantLevelDayLimit(
                    (int) $validated['employee_id'],
                    (float) ($validated['md_consumed'] ?? 0)
                );
                if ($levelError) {
                    return response()->json(['success' => false, 'message' => $levelError], 422);
                }
            }
            // ─────────────────────────────────────────────────────────────────

            // ── Period access gate ────────────────────────────────────────────
            // Bypass for Admin and RPMO (they can always submit)
            $sessionRoleId = (int) (session('user')['role']['id'] ?? 0);
            $bypass = in_array($sessionRoleId, [RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_RPMO_HEAD->value]);

            if (!$bypass) {
                /** @var PeriodService $periodSvc */
                $periodSvc = app(PeriodService::class);
                $check = $periodSvc->canSubmitTimesheet(
                    (int) $validated['employee_id'],
                    Carbon::parse($validated['date'])
                );
                if (!$check['allowed']) {
                    return response()->json([
                        'success' => false,
                        'message' => $check['reason'],
                    ], 422);
                }
            }
            // ─────────────────────────────────────────────────────────────────

            // Assign period year/month
            $this->assignPeriod($validated);

            $timesheet = Timesheet::create($validated);

            Log::info('Timesheet created successfully:', ['id' => $timesheet->id]);

            return response()->json([
                'success' => true,
                'data' => $timesheet->load(['employee']),
                'message' => 'Timesheet created successfully'
            ], 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error:', $e->errors());
            
            return response()->json([
                'success' => false,
                'message' => 'Timesheet data is invalid.',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('Error creating timesheet', [
                'error'    => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create timesheet'
            ], 500);
        }
    }

    /**
     * Update timesheet (API)
     */
    public function update(Request $request, $id)
    {
        try {
            $timesheet = Timesheet::findOrFail($id);

            // Ownership check: only the owner can update (heads/admin can approve/reject via dedicated endpoints)
            $sessionUser       = SessionUser::fromSession(session('user'));
            $currentEmployeeId = $sessionUser->id;
            $privilegedRoles   = array_merge([RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_RPMO_HEAD->value], RoleId::HEAD_GROUP);

            if (!$sessionUser->hasAnyRole($privilegedRoles) && (int) $timesheet->employee_id !== (int) $currentEmployeeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: you can only edit your own timesheets'
                ], 403);
            }

            if (in_array($timesheet->status, ['approved', 'submitted'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update an approved or submitted timesheet.',
                ], 403);
            }

            $rules = [
                'date' => 'required|date|before_or_equal:today',
                'activity_date' => 'nullable|date',
                'start_time' => 'required',
                'end_time' => 'required|after:start_time',
                'description' => 'required|string',
                'notes' => 'nullable|string',
                'activity_type' => 'required|in:development,meeting,documentation,testing,support,training,other',
                'is_billable' => 'sometimes|boolean',
                'presence' => 'nullable|string',
                'location' => 'nullable|string',
                'md_consumed' => 'nullable|numeric|min:0',
            ];

            if ($request->filled('delivery_projects_id')) {
                $rules['delivery_projects_id'] = 'nullable|integer';
                $rules['activity_id'] = 'nullable|exists:delivery_project_activities,id';
                $rules['ticket_id'] = 'nullable';
            } elseif ($request->filled('ticket_id')) {
                $rules['ticket_id'] = 'nullable|integer';
                $rules['delivery_projects_id'] = 'nullable';
                $rules['activity_date'] = 'required|date';
            }

            $validated = $request->validate($rules);

            // Add presence, location, md_consumed from request
            $validated['presence'] = $request->input('presence');
            $validated['location'] = $request->input('location');
            $validated['md_consumed'] = $request->input('md_consumed');

            if (!empty($validated['delivery_projects_id'])) {
                $validated['ticket_id'] = null;
                $validated['activity_id'] = $request->input('activity_id') ?: null;
            } elseif (!empty($validated['ticket_id'])) {
                $validated['delivery_projects_id'] = null;
                $validated['activity_id'] = null;
            } else {
                $validated['delivery_projects_id'] = null;
                $validated['ticket_id'] = null;
                $validated['activity_id'] = null;
            }

            // ── Resolution Days quota guard ───────────────────────────────────
            if (!empty($validated['ticket_id'])) {
                $quotaError = $this->checkResolutionQuota(
                    (int) $validated['ticket_id'],
                    (int) $timesheet->employee_id,
                    (float) ($validated['md_consumed'] ?? 0),
                    (int) $timesheet->id
                );
                if ($quotaError) {
                    return response()->json(['success' => false, 'message' => $quotaError], 422);
                }

                $levelError = $this->checkConsultantLevelDayLimit(
                    (int) $timesheet->employee_id,
                    (float) ($validated['md_consumed'] ?? 0)
                );
                if ($levelError) {
                    return response()->json(['success' => false, 'message' => $levelError], 422);
                }
            }
            // ─────────────────────────────────────────────────────────────────

            // Auto-assign period if date changed or period not yet set
            $this->assignPeriod($validated);

            $timesheet->update($validated);

            return response()->json([
                'success' => true,
                'data' => $timesheet->load(['employee']),
                'message' => 'Timesheet updated successfully'
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error:', $e->errors());
            
            return response()->json([
                'success' => false,
                'message' => 'Timesheet data is invalid.',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('Error updating timesheet: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update timesheet'
            ], 500);
        }
    }

    /**
     * Delete timesheet (API)
     */
    public function destroy($id)
    {
        try {
            $timesheet = Timesheet::findOrFail($id);

            // Ownership check: only the owner can delete
            $sessionUser       = SessionUser::fromSession(session('user'));
            $currentEmployeeId = $sessionUser->id;
            $privilegedRoles   = array_merge([RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_RPMO_HEAD->value], RoleId::HEAD_GROUP);

            if (!$sessionUser->hasAnyRole($privilegedRoles) && (int) $timesheet->employee_id !== (int) $currentEmployeeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: you can only delete your own timesheets'
                ], 403);
            }

            if ($timesheet->status === 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete approved timesheet'
                ], 403);
            }

            $timesheet->delete();

            return response()->json([
                'success' => true,
                'message' => 'Timesheet deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting timesheet: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete timesheet'
            ], 500);
        }
    }

    /**
     * Submit timesheet for approval (API)
     */
    public function submit($id)
    {
        try {
            $timesheet = Timesheet::findOrFail($id);

            // Ownership check: only the owner can submit their own timesheet
            $sessionUser       = SessionUser::fromSession(session('user'));
            $currentEmployeeId = $sessionUser->id;
            $privilegedRoles   = array_merge([RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_RPMO_HEAD->value], RoleId::HEAD_GROUP);

            if (!$sessionUser->hasAnyRole($privilegedRoles) && (int) $timesheet->employee_id !== (int) $currentEmployeeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Forbidden: you can only submit your own timesheets'
                ], 403);
            }

            if ($timesheet->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft timesheets can be submitted'
                ], 400);
            }

            // For support timesheets, enforce mandays quota hard-stop
            if ($timesheet->ticket_id) {
                $empId    = $timesheet->employee_id;
                $ticketId = $timesheet->ticket_id;

                // Customer Mandays approval is only required for Change Request tickets
                // (same ticket_type check used by MandaysController's CR gates on Resolution
                // Days). Other ticket types never require a Customer Mandays proposal, so
                // gating their timesheets on it would block submission forever.
                if ($timesheet->ticket?->ticket_type === 'Change Request') {
                    // Latest Customer Mandays version for this ticket must be approved before
                    // any timesheet can be submitted — an older approved version doesn't count
                    // if a newer draft/revision superseded it.
                    $latestCustomerMandays = CustomerMandays::where('ticket_id', $ticketId)
                        ->orderBy('version', 'desc')
                        ->first();

                    if (!$latestCustomerMandays || $latestCustomerMandays->status !== 'approved') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Customer Mandays status is not approved yet, cannot submit timesheet.',
                        ], 422);
                    }
                }

                $latestApproved = ConsultantMandays::where('ticket_id', $ticketId)
                    ->where('status', 'approved')
                    ->orderBy('approved_at', 'desc')
                    ->first();

                if (!$latestApproved) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot submit: no approved mandays proposal found for this ticket. Contact your Head.',
                    ], 422);
                }

                $quotaDetail = ConsultantMandaysDetail::where('consultant_mandays_id', $latestApproved->id)
                    ->where('employee_id', $empId)
                    ->first();

                if (!$quotaDetail) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot submit: you are not listed in the approved mandays proposal for this ticket. Contact your Head.',
                    ], 422);
                }

                $quota    = round((float) ($quotaDetail->approved_mandays ?? 0) + (float) ($quotaDetail->approved_additional ?? 0), 2);
                $consumed = (float) Timesheet::where('ticket_id', $ticketId)
                    ->where('employee_id', $empId)
                    ->whereIn('status', ['draft', 'submitted', 'approved'])
                    ->sum('md_consumed');
                $remaining = round($quota - $consumed, 2);

                if ($remaining < 0) {
                    return response()->json([
                        'success' => false,
                        'message' => "Cannot submit: quota exceeded. Remaining MD is {$remaining}. Contact your Head to increase the quota.",
                    ], 422);
                }
            }

            $timesheet->update(['status' => 'submitted']);

            $this->notifyHeadsOnSubmit($timesheet);

            return response()->json([
                'success' => true,
                'data' => $timesheet,
                'message' => 'Timesheet submitted for approval'
            ]);
        } catch (\Exception $e) {
            Log::error('Error submitting timesheet: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit timesheet'
            ], 500);
        }
    }

    /**
     * Send bell notifications to relevant Head when a timesheet is submitted.
     * - Support timesheet  → notify all Head of Support (role 5)
     * - Project timesheet  → notify all Head of Project (role 4)
     * - Office timesheet   → notify all RPMO (role 7)
     */
    private function notifyHeadsOnSubmit(Timesheet $timesheet): void
    {
        try {
            $isSupport = !$timesheet->delivery_projects_id && $timesheet->ticket_id;
            $isProject = (bool) $timesheet->delivery_projects_id;

            if ($isSupport) {
                $targetRole = RoleId::DELIVERY_SUPPORT_HEAD->value;
            } elseif ($isProject) {
                $targetRole = RoleId::DELIVERY_PROJECT_HEAD->value;
            } else {
                $targetRole = RoleId::DELIVERY_RPMO_HEAD->value;
            }

            // Submitter name — use session if available, else query
            $submitter = Employee::where('employee_id', $timesheet->employee_id)->first();
            $fromName  = $submitter?->name ?? $submitter?->eci ?? "Employee #{$timesheet->employee_id}";

            // Ticket number for support
            $ticketNumber = null;
            if ($isSupport && $timesheet->ticket_id) {
                $ticket = Ticket::find($timesheet->ticket_id);
                $ticketNumber = $ticket?->ticket_number ?? $timesheet->ticket_id;
            }

            // Build notification message
            if ($isSupport) {
                $preview = "Ticket #{$ticketNumber} — {$fromName} submitted a timesheet for validation";
            } elseif ($isProject) {
                $preview = "{$fromName} submitted a project timesheet for approval";
            } else {
                $preview = "{$fromName} submitted an office timesheet for approval";
            }

            // Create notification for every active Head with the target role
            $heads = Employee::withRole($targetRole)->where('is_active', true)->get();

            foreach ($heads as $head) {
                Notification::create([
                    'employee_id'      => $head->employee_id,
                    'type'             => 'timesheet_submitted',
                    'ticket_id'        => $timesheet->ticket_id,
                    'from_employee_id' => $timesheet->employee_id,
                    'from_name'        => $fromName,
                    'preview'          => $preview,
                    'is_read'          => false,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('notifyHeadsOnSubmit failed: ' . $e->getMessage());
        }
    }

    /**
     * Approve timesheet (API)
     */
    public function approve(Request $request, $id)
    {
        try {
            $user = session('user');
            $sessionUser = SessionUser::fromSession($user);

            // Admin, Head of Project, Head of Support, and RPMO can approve (checked
            // against ALL assigned roles, not just whichever role is "primary")
            $approvalRoles = array_merge([RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_RPMO_HEAD->value], RoleId::HEAD_GROUP);
            if (!$sessionUser || !$sessionUser->hasAnyRole($approvalRoles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Only managers can approve timesheets'
                ], 403);
            }

            $timesheet = Timesheet::findOrFail($id);

            if ($timesheet->status !== 'submitted') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only submitted timesheets can be approved'
                ], 400);
            }

            $timesheet->update([
                'status' => 'approved',
                'approved_by' => $user['id'] ?? null,
                'approved_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $timesheet->load(['approver']),
                'message' => 'Timesheet approved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error approving timesheet: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve timesheet'
            ], 500);
        }
    }

    /**
     * Reject timesheet (API)
     */
    public function reject(Request $request, $id)
    {
        try {
            $user = session('user');
            $sessionUser = SessionUser::fromSession($user);

            // Admin, Head of Project, Head of Support, and RPMO can reject (checked
            // against ALL assigned roles, not just whichever role is "primary")
            $approvalRoles = array_merge([RoleId::EC_ADMINISTRATOR->value, RoleId::DELIVERY_RPMO_HEAD->value], RoleId::HEAD_GROUP);
            if (!$sessionUser || !$sessionUser->hasAnyRole($approvalRoles)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Only managers can reject timesheets'
                ], 403);
            }

            $timesheet = Timesheet::findOrFail($id);

            if ($timesheet->status !== 'submitted') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only submitted timesheets can be rejected'
                ], 400);
            }

            $validated = $request->validate([
                'rejection_reason' => 'required|string',
            ]);

            $timesheet->update([
                'status' => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $timesheet,
                'message' => 'Timesheet rejected'
            ]);
        } catch (\Exception $e) {
            Log::error('Error rejecting timesheet: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject timesheet'
            ], 500);
        }
    }

    /**
     * Get timesheet statistics (API)
     */
    public function statistics(Request $request)
    {
        try {
            $employeeId = $request->input('employee_id');
            $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));

            $query = Timesheet::dateRange($startDate, $endDate);
            
            if ($employeeId) {
                $query->forEmployee($employeeId);
            }

            $totalMinutes = $query->sum('duration_minutes') ?? 0;
            $totalHours = round($totalMinutes / 60, 2);

            $billableMinutes = $query->billable()->sum('duration_minutes') ?? 0;
            $billableHours = round($billableMinutes / 60, 2);

            $stats = [
                'total_hours' => $totalHours,
                'total_entries' => $query->count(),
                'billable_hours' => $billableHours,
                'by_status' => Timesheet::dateRange($startDate, $endDate)
                    ->when($employeeId, fn($q) => $q->forEmployee($employeeId))
                    ->selectRaw('status, COUNT(*) as count, SUM(duration_minutes) as total_minutes')
                    ->groupBy('status')
                    ->get()
                    ->mapWithKeys(fn($item) => [
                        $item->status => [
                            'count' => $item->count,
                            'hours' => round(($item->total_minutes ?? 0) / 60, 2)
                        ]
                    ])
                    ->toArray(),
                'by_activity_type' => Timesheet::dateRange($startDate, $endDate)
                    ->when($employeeId, fn($q) => $q->forEmployee($employeeId))
                    ->selectRaw('activity_type, SUM(duration_minutes) as total_minutes')
                    ->groupBy('activity_type')
                    ->get()
                    ->mapWithKeys(fn($item) => [
                        $item->activity_type => round(($item->total_minutes ?? 0) / 60, 2)
                    ])
                    ->toArray(),
                'by_type' => [
                    'project' => Timesheet::dateRange($startDate, $endDate)
                        ->when($employeeId, fn($q) => $q->forEmployee($employeeId))
                        ->whereNotNull('delivery_projects_id')
                        ->sum('duration_minutes') / 60,
                    'support' => Timesheet::dateRange($startDate, $endDate)
                        ->when($employeeId, fn($q) => $q->forEmployee($employeeId))
                        ->whereNotNull('ticket_id')
                        ->sum('duration_minutes') / 60,
                    'office' => Timesheet::dateRange($startDate, $endDate)
                        ->when($employeeId, fn($q) => $q->forEmployee($employeeId))
                        ->whereNull('delivery_projects_id')
                        ->whereNull('ticket_id')
                        ->sum('duration_minutes') / 60,
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving statistics', [
                'error'    => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics'
            ], 500);
        }
    }

    /**
     * Get projects where the logged-in employee is a team member
     */
    public function myProjects(Request $request)
    {
        try {
            $user = session('user');
            // Session stores employee_id as 'id', fallback to 'employee_id' for compatibility
            $employeeId = $user['id'] ?? $user['employee_id'] ?? null;

            if (!$employeeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not authenticated'
                ], 401);
            }

            $projects = DeliveryProject::whereHas('teamMembers', function ($query) use ($employeeId) {
                $query->where('employee.employee_id', $employeeId);
            })
            ->with(['client.basicData'])
            ->select('id', 'name', 'client_id', 'status', 'phase')
            ->orderBy('name')
            ->get();

            return response()->json([
                'success' => true,
                'data' => $projects,
                'message' => 'Projects retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving my projects: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve projects'
            ], 500);
        }
    }

    /**
     * Get activities assigned to the logged-in employee for a specific project
     */
    public function myActivities(Request $request, $projectId)
    {
        try {
            $user = session('user');
            // Session stores employee_id as 'id', fallback to 'employee_id' for compatibility
            $employeeId = $user['id'] ?? $user['employee_id'] ?? null;

            if (!$employeeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not authenticated'
                ], 401);
            }

            // Query activities that have this employee in the pivot table
            $activities = DeliveryProjectActivity::where('delivery_projects_id', $projectId)
                ->whereExists(function ($query) use ($employeeId) {
                    $query->select(DB::raw(1))
                        ->from('activity_employee')
                        ->whereColumn('activity_employee.delivery_project_activity_id', 'delivery_project_activities.id')
                        ->where('activity_employee.employee_id', $employeeId);
                })
                ->with(['phase', 'stage'])
                ->select('id', 'name', 'delivery_project_phase_id', 'stage_id', 'status', 'start_date', 'end_date')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $activities,
                'message' => 'Activities retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving my activities: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve activities'
            ], 500);
        }
    }

    /**
     * Get ALL activities assigned to the logged-in employee across all projects
     * This is used for the timesheet dropdown to show activities directly
     */
    public function allMyActivities(Request $request)
    {
        try {
            $user = session('user');
            // Session stores employee_id as 'id', fallback to 'employee_id' for compatibility
            $employeeId = $user['id'] ?? $user['employee_id'] ?? null;

            Log::info('allMyActivities called', [
                'user' => $user,
                'employeeId' => $employeeId
            ]);

            if (!$employeeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not authenticated'
                ], 401);
            }

            // Query activities that have this employee in the pivot table
            $activities = DeliveryProjectActivity::whereExists(function ($query) use ($employeeId) {
                    $query->select(DB::raw(1))
                        ->from('activity_employee')
                        ->whereColumn('activity_employee.delivery_project_activity_id', 'delivery_project_activities.id')
                        ->where('activity_employee.employee_id', $employeeId);
                })
                ->with(['phase', 'stage', 'delivery_project:id,name'])
                ->select('id', 'name', 'delivery_projects_id', 'delivery_project_phase_id', 'stage_id', 'status', 'start_date', 'end_date')
                ->orderBy('delivery_projects_id')
                ->orderBy('name')
                ->get();

            Log::info('Activities found', ['count' => $activities->count()]);

            $result = $activities->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'name' => $activity->name,
                        'delivery_projects_id' => $activity->delivery_projects_id,
                        'project_name' => $activity->delivery_project->name ?? 'Unknown Project',
                        'phase_name' => $activity->phase->name ?? null,
                        'stage_name' => $activity->stage->name ?? null,
                        'status' => $activity->status,
                        'start_date' => $activity->start_date,
                        'end_date' => $activity->end_date,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'All assigned activities retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving all my activities: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve activities'
            ], 500);
        }
    }

    // ── Resolution Days quota guard ─────────────────────────────────────────

    /**
     * For support timesheets: verify this MD Consumed value won't push the employee's
     * remaining quota for this ticket negative. Mirrors the "remaining < 0" hard-stop
     * already enforced at submit() (see submit() above) — applied earlier, at
     * create/update, so a timesheet can't end up stuck as an unsubmittable draft.
     * Returns an error message if blocked, or null if OK (including when no approved
     * proposal exists yet — that case is caught later by submit(), not here).
     */
    private function checkResolutionQuota(int $ticketId, int $employeeId, float $newMdConsumed, ?int $excludeTimesheetId = null): ?string
    {
        $latestApproved = ConsultantMandays::where('ticket_id', $ticketId)
            ->where('status', 'approved')
            ->orderBy('approved_at', 'desc')
            ->first();

        if (!$latestApproved) {
            return null;
        }

        $quotaDetail = ConsultantMandaysDetail::where('consultant_mandays_id', $latestApproved->id)
            ->where('employee_id', $employeeId)
            ->first();

        if (!$quotaDetail) {
            return null;
        }

        $quota = round((float) ($quotaDetail->approved_mandays ?? 0) + (float) ($quotaDetail->approved_additional ?? 0), 2);

        $consumed = (float) Timesheet::where('ticket_id', $ticketId)
            ->where('employee_id', $employeeId)
            ->whereIn('status', ['draft', 'submitted', 'approved'])
            ->when($excludeTimesheetId, fn ($q) => $q->where('id', '!=', $excludeTimesheetId))
            ->sum('md_consumed');

        $remaining = round($quota - $consumed - $newMdConsumed, 2);

        if ($remaining < 0) {
            return "MD Consumed exceeds the remaining quota for this ticket. Remaining would be {$remaining}. Contact your Head to increase the quota.";
        }

        return null;
    }

    /**
     * Consultants at Middle level or above may consume at most 1 MD per single
     * support timesheet entry (no such cap for Associate and below). Level is
     * the employee's highest Certification qualification_level across all
     * modules — see Employee::highestQualificationLevelSortOrder().
     * Returns an error message if blocked, or null if OK.
     */
    private function checkConsultantLevelDayLimit(int $employeeId, float $mdConsumed): ?string
    {
        if ($mdConsumed <= 1) {
            return null;
        }

        $middleSortOrder = Grade::sortOrderForLevel('Middle');
        if ($middleSortOrder === null) {
            return null;
        }

        $levelSortOrder = Employee::find($employeeId)?->highestQualificationLevelSortOrder();
        if ($levelSortOrder === null || $levelSortOrder < $middleSortOrder) {
            return null;
        }

        return 'Consultants at Middle level or above can consume a maximum of 1 MD per timesheet entry. Please split this into multiple entries.';
    }

    // ── Period helper ─────────────────────────────────────────────────────

    /**
     * Compute the period for the timesheet date.
     * If that period is already closed, advance to the next period.
     * Sets period_year and period_month on $data.
     */
    private function assignPeriod(array &$data): void
    {
        if (empty($data['date'])) return;

        // Use the natural period for the date (21-20 rule).
        // Period access is already validated by canSubmitTimesheet() before this is called.
        $p = ReportingPeriod::periodFor(Carbon::parse($data['date']));

        $data['period_year']  = $p['year'];
        $data['period_month'] = $p['month'];
    }
}