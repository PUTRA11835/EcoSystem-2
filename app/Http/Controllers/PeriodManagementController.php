<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\PeriodAuditLog;
use App\Models\PeriodLateExceptionRequest;
use Carbon\Carbon;
use App\Models\ReportingPeriod;
use App\Services\PeriodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeriodManagementController extends Controller
{
    public function __construct(private PeriodService $svc) {}

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function sessionUser(): array
    {
        return session('user', []);
    }

    private function actorId(): int
    {
        return (int) ($this->sessionUser()['id'] ?? 0);
    }

    private function actorRoleId(): int
    {
        return (int) ($this->sessionUser()['role']['id'] ?? 0);
    }

    private function authorizeRoles(array $allowed): bool
    {
        return in_array($this->actorRoleId(), $allowed, true);
    }

    private function unauthorized(string $msg = 'Unauthorized.'): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $msg], 403);
    }

    private function invalid(string $msg): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $msg], 422);
    }

    // ── Notification helpers ───────────────────────────────────────────────────

    /** Resolve the display name of the current actor. */
    private function actorName(): string
    {
        $actor = Employee::with('basicData')->where('employee_id', $this->actorId())->first();
        return $actor?->basicData?->full_name ?? "EMP#{$this->actorId()}";
    }

    /**
     * Create a notification record for one recipient.
     *
     * @param int    $toId      recipient employee_id
     * @param string $type      notification type constant
     * @param string $fromName  sender display name
     * @param string $preview   short message body
     * @param string $link      URL the user is taken to on click
     */
    private function notify(int $toId, string $type, string $fromName, string $preview, string $link): void
    {
        Notification::create([
            'employee_id'      => $toId,
            'type'             => $type,
            'from_employee_id' => $this->actorId(),
            'from_name'        => $fromName,
            'preview'          => $preview,
            'link'             => $link,
            'is_read'          => false,
        ]);
    }

    /** Notify every active employee that holds one of the given role IDs. */
    private function notifyRoles(array $roleIds, string $type, string $fromName, string $preview, string $link): void
    {
        Employee::whereIn('role_id', $roleIds)
            ->pluck('employee_id')
            ->each(fn ($id) => $this->notify($id, $type, $fromName, $preview, $link));
    }

    // ── Web view ──────────────────────────────────────────────────────────────

    public function index()
    {
        if (!$this->authorizeRoles(RoleId::PERIOD_MANAGEMENT_GROUP)) {
            abort(403, 'Access denied. You do not have permission to manage periods.');
        }

        $roleId  = $this->actorRoleId();
        $periods = ReportingPeriod::orderBy('year', 'desc')->orderBy('month', 'desc')->get();
        $active  = ReportingPeriod::getActive();
        $domain  = $this->svc->getDomainForRole($roleId);

        // Period created but not yet opened globally (waiting for RPMO to open)
        $pending = ReportingPeriod::where('global_status', 'not_open')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->first();

        // For heads: get eligible employees for their domain's late exceptions
        // Admin gets all delivery employees (both domains)
        $domainEmployees = collect();
        if ($roleId === RoleId::ADMIN->value) {
            $domainEmployees = Employee::whereIn('role_id', [
                    RoleId::EMPLOYEE->value,
                    RoleId::EMPLOYEE_PROJECT->value,
                ])
                ->with('basicData')
                ->orderBy('employee_id')
                ->get(['employee_id', 'role_id']);
        } elseif (in_array($roleId, [RoleId::HEAD_OF_PROJECT->value, RoleId::HEAD_OF_SUPPORT->value])) {
            $domainRoleId    = ($domain === PeriodService::DOMAIN_PROJECT)
                ? RoleId::EMPLOYEE_PROJECT->value
                : RoleId::EMPLOYEE->value;
            $domainEmployees = Employee::where('role_id', $domainRoleId)
                ->with('basicData')
                ->orderBy('employee_id')
                ->get(['employee_id', 'role_id']);
        }

        return view('rpmo.periods.index', compact('periods', 'active', 'pending', 'roleId', 'domain', 'domainEmployees'));
    }

    // ── Superadmin check ──────────────────────────────────────────────────────

    private function isAdmin(): bool
    {
        return $this->actorRoleId() === RoleId::ADMIN->value;
    }

    // ── API: Create period (RPMO / Admin) ─────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        if (!in_array($this->actorRoleId(), [RoleId::ADMIN->value, RoleId::RPMO->value])) {
            return $this->unauthorized('Only RPMO Head or EC Administrator can create periods.');
        }

        $data = $request->validate([
            'year'       => 'required|integer|min:2020|max:2100',
            'month'      => 'required|integer|min:1|max:12',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
        ]);

        try {
            $period = $this->svc->createPeriod(
                $data['year'],
                $data['month'],
                $data['start_date'],
                $data['end_date'],
                $this->actorId(),
                $this->actorRoleId()
            );
            return response()->json([
                'success' => true,
                'message' => 'Period created successfully.',
                'data'    => $period,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->invalid($e->getMessage());
        }
    }

    // ── API: Open global (RPMO / Admin) ──────────────────────────────────────

    public function openGlobal(ReportingPeriod $period): JsonResponse
    {
        if (!in_array($this->actorRoleId(), [RoleId::ADMIN->value, RoleId::RPMO->value])) {
            return $this->unauthorized('Only RPMO Head or EC Administrator can open periods globally.');
        }

        try {
            $this->svc->openGlobal($period, $this->actorId(), $this->actorRoleId());
            return response()->json([
                'success' => true,
                'message' => "Period {$period->getLabel()} opened globally.",
                'data'    => $period->fresh(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->invalid($e->getMessage());
        }
    }

    // ── API: Close global (RPMO / Admin) ─────────────────────────────────────

    public function closeGlobal(ReportingPeriod $period): JsonResponse
    {
        if (!in_array($this->actorRoleId(), [RoleId::ADMIN->value, RoleId::RPMO->value])) {
            return $this->unauthorized('Only RPMO Head or EC Administrator can close periods globally.');
        }

        try {
            $this->svc->closeGlobal($period, $this->actorId(), $this->actorRoleId());
            return response()->json([
                'success' => true,
                'message' => "Period {$period->getLabel()} closed globally.",
                'data'    => $period->fresh(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->invalid($e->getMessage());
        }
    }

    // ── API: Force close domain (RPMO / Admin) ───────────────────────────────

    public function forceCloseDomain(Request $request, ReportingPeriod $period): JsonResponse
    {
        if (!in_array($this->actorRoleId(), [RoleId::ADMIN->value, RoleId::RPMO->value])) {
            return $this->unauthorized('Only RPMO Head or EC Administrator can force-close domains.');
        }

        $data = $request->validate(['domain' => 'required|in:project,support']);

        try {
            $this->svc->forceCloseDomain($period, $data['domain'], $this->actorId(), $this->actorRoleId());
            return response()->json([
                'success' => true,
                'message' => ucfirst($data['domain']) . " domain force-closed for {$period->getLabel()}.",
                'data'    => $period->fresh(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->invalid($e->getMessage());
        }
    }

    // ── API: Open domain (Project/Support Head / Admin) ──────────────────────

    public function openDomain(Request $request, ReportingPeriod $period): JsonResponse
    {
        $roleId  = $this->actorRoleId();
        $isAdmin = $this->isAdmin();

        if (!$isAdmin && !in_array($roleId, [RoleId::HEAD_OF_PROJECT->value, RoleId::HEAD_OF_SUPPORT->value])) {
            return $this->unauthorized('Only Project Head, Support Head, or EC Administrator can open domain periods.');
        }

        // Admin has no inherent domain — domain must be provided in request body
        if ($isAdmin) {
            $domain = $request->input('domain');
            if (!in_array($domain, [PeriodService::DOMAIN_PROJECT, PeriodService::DOMAIN_SUPPORT])) {
                return $this->invalid('Domain parameter must be "project" or "support".');
            }
        } else {
            $domain = $this->svc->getDomainForRole($roleId);
        }

        try {
            $this->svc->openDomain($period, $domain, $this->actorId(), $roleId);
            return response()->json([
                'success' => true,
                'message' => ucfirst($domain) . " domain opened for {$period->getLabel()}.",
                'data'    => $period->fresh(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->invalid($e->getMessage());
        }
    }

    // ── API: Close domain (Project/Support Head / Admin) ─────────────────────

    public function closeDomain(Request $request, ReportingPeriod $period): JsonResponse
    {
        $roleId  = $this->actorRoleId();
        $isAdmin = $this->isAdmin();

        if (!$isAdmin && !in_array($roleId, [RoleId::HEAD_OF_PROJECT->value, RoleId::HEAD_OF_SUPPORT->value])) {
            return $this->unauthorized('Only Project Head, Support Head, or EC Administrator can close domain periods.');
        }

        // Admin has no inherent domain — domain must be provided in request body
        if ($isAdmin) {
            $domain = $request->input('domain');
            if (!in_array($domain, [PeriodService::DOMAIN_PROJECT, PeriodService::DOMAIN_SUPPORT])) {
                return $this->invalid('Domain parameter must be "project" or "support".');
            }
        } else {
            $domain = $this->svc->getDomainForRole($roleId);
        }

        try {
            $this->svc->closeDomain($period, $domain, $this->actorId(), $roleId);
            return response()->json([
                'success' => true,
                'message' => ucfirst($domain) . " domain closed for {$period->getLabel()}.",
                'data'    => $period->fresh(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->invalid($e->getMessage());
        }
    }

    // ── API: Audit logs ───────────────────────────────────────────────────────

    public function auditLogs(ReportingPeriod $period): JsonResponse
    {
        if (!$this->authorizeRoles(RoleId::PERIOD_MANAGEMENT_GROUP)) {
            return $this->unauthorized();
        }

        $logs = PeriodAuditLog::where('period_id', $period->id)
            ->with(['actor.basicData'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($log) => [
                'id'           => $log->id,
                'action'       => $log->action,
                'action_label' => $log->getActionLabel(),
                'action_color' => $log->getActionColor(),
                'is_force'     => $log->is_force,
                'actor_name'   => $log->actor?->basicData?->full_name ?? 'System',
                'actor_role'   => $log->actor_role_id,
                'metadata'     => $log->metadata,
                'created_at'   => $log->created_at?->format('d M Y, H:i'),
            ]);

        return response()->json(['success' => true, 'data' => $logs]);
    }

    // ── Legacy grant/revoke endpoints removed ─────────────────────────────────
    // All late exception access is now request-based (2-level approval).
    // Use POST /api/periods/exception-requests to submit a request.

    // ── API: Update period dates (RPMO / Admin) ───────────────────────────────

    public function updateDates(Request $request, ReportingPeriod $period): JsonResponse
    {
        if (!in_array($this->actorRoleId(), [RoleId::ADMIN->value, RoleId::RPMO->value])) {
            return $this->unauthorized('Only RPMO Head or EC Administrator can edit period dates.');
        }

        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
        ]);

        $period->start_date = $data['start_date'];
        $period->end_date   = $data['end_date'];
        $period->save();

        PeriodAuditLog::create([
            'period_id'     => $period->id,
            'action'        => 'date_updated',
            'actor_id'      => $this->actorId(),
            'actor_role_id' => $this->actorRoleId(),
            'is_force'      => false,
            'metadata'      => ['start_date' => $data['start_date'], 'end_date' => $data['end_date']],
        ]);

        return response()->json([
            'success' => true,
            'message' => "Period {$period->getLabel()} dates updated.",
            'data'    => $period->fresh(),
        ]);
    }

    // ── API: Delete period (RPMO / Admin) ─────────────────────────────────────

    public function destroy(ReportingPeriod $period): JsonResponse
    {
        if (!in_array($this->actorRoleId(), [RoleId::ADMIN->value, RoleId::RPMO->value])) {
            return $this->unauthorized('Only RPMO Head or EC Administrator can delete periods.');
        }

        $label = $period->getLabel();

        try {
            // Remove related records before deleting the period itself
            $period->auditLogs()->delete();
            $period->lateExceptions()->delete();
            $period->lateExceptionRequests()->delete();
            $period->delete();
        } catch (\Exception $e) {
            return $this->invalid('Cannot delete period: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'message' => "Period {$label} has been deleted."]);
    }

    // ── API: Active period (used by other pages) ──────────────────────────────

    public function activePeriod(): JsonResponse
    {
        $period = ReportingPeriod::getActive();
        return response()->json(['success' => true, 'data' => $period]);
    }

    // ── API: Closed periods employee can request late access for ─────────────

    public function closedPeriods(): JsonResponse
    {
        $periods = ReportingPeriod::where('global_status', 'closed')
            ->orderBy('year', 'desc')
            ->orderBy('month', 'desc')
            ->get()
            ->map(fn($p) => [
                'id'         => $p->id,
                'label'      => $p->getLabel(),
                'start_date' => $p->start_date?->format('d M Y'),
                'end_date'   => $p->end_date?->format('d M Y'),
            ]);

        return response()->json(['success' => true, 'data' => $periods]);
    }

    // ── API: Employee submits late exception request ───────────────────────────

    public function createExceptionRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period_id' => 'required|integer|exists:reporting_periods,id',
            'notes'     => 'required|string|max:1000',
        ]);

        $employeeId = $this->actorId();
        if (!$employeeId) {
            return $this->unauthorized();
        }

        $roleId = $this->actorRoleId();
        $domain = $this->svc->getDomainForRole($roleId);
        if ($domain === null) {
            return $this->invalid('Your role does not require late exception access.');
        }

        try {
            $req = $this->svc->createLateExceptionRequest(
                (int) $data['period_id'],
                $employeeId,
                $domain,
                $data['notes']
            );

            // ── Notify domain heads ───────────────────────────────────────────
            $period      = $req->period ?? ReportingPeriod::find($data['period_id']);
            $periodLabel = $period?->getLabel() ?? "Period #{$data['period_id']}";
            $senderName  = $this->actorName();
            $domainLabel = ucfirst($domain);
            $headRole    = $domain === PeriodService::DOMAIN_PROJECT
                ? RoleId::HEAD_OF_PROJECT->value
                : RoleId::HEAD_OF_SUPPORT->value;

            $this->notifyRoles(
                [$headRole],
                'late_exception_submitted',
                $senderName,
                "{$senderName} submitted a late access request for {$periodLabel} ({$domainLabel} domain). Notes: {$data['notes']}",
                '/rpmo/periods'
            );

            return response()->json([
                'success' => true,
                'message' => 'Late access request submitted. Waiting for Head approval.',
                'data'    => $this->formatRequest($req),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->invalid($e->getMessage());
        }
    }

    // ── API: Employee views own exception requests ────────────────────────────

    public function myExceptionRequests(): JsonResponse
    {
        $employeeId = $this->actorId();
        if (!$employeeId) {
            return $this->unauthorized();
        }

        $requests = PeriodLateExceptionRequest::where('employee_id', $employeeId)
            ->with(['period', 'head.basicData', 'rpmo.basicData', 'rejectedBy.basicData'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($r) => $this->formatRequest($r));

        return response()->json(['success' => true, 'data' => $requests]);
    }

    // ── API: Head/RPMO lists pending requests ─────────────────────────────────

    public function listExceptionRequests(Request $request): JsonResponse
    {
        $roleId  = $this->actorRoleId();
        $isAdmin = $this->isAdmin();
        $isRpmo  = $roleId === RoleId::RPMO->value;
        $isHead  = in_array($roleId, [RoleId::HEAD_OF_PROJECT->value, RoleId::HEAD_OF_SUPPORT->value]);

        if (!$isAdmin && !$isRpmo && !$isHead) {
            return $this->unauthorized();
        }

        $query = PeriodLateExceptionRequest::with([
            'period', 'employee.basicData', 'head.basicData', 'rpmo.basicData', 'rejectedBy.basicData',
        ]);

        // Head sees only their domain's pending_head requests
        if ($isHead && !$isAdmin) {
            $domain = $this->svc->getDomainForRole($roleId);
            $query->where('domain', $domain)->where('status', 'pending_head');
        }
        // RPMO sees only pending_rpmo
        elseif ($isRpmo && !$isAdmin) {
            $query->where('status', 'pending_rpmo');
        }
        // Admin sees all

        $requests = $query->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($r) => $this->formatRequest($r));

        return response()->json(['success' => true, 'data' => $requests]);
    }

    // ── API: Head approve/reject (Level 1) ────────────────────────────────────

    public function headDecideRequest(Request $request, PeriodLateExceptionRequest $exRequest): JsonResponse
    {
        $roleId  = $this->actorRoleId();
        $isAdmin = $this->isAdmin();
        $isHead  = in_array($roleId, [RoleId::HEAD_OF_PROJECT->value, RoleId::HEAD_OF_SUPPORT->value]);

        if (!$isAdmin && !$isHead) {
            return $this->unauthorized('Only Head or Admin can approve at Level 1.');
        }

        // Head can only decide on their domain's requests
        if ($isHead && !$isAdmin) {
            $domain = $this->svc->getDomainForRole($roleId);
            if ($exRequest->domain !== $domain) {
                return $this->unauthorized('This request does not belong to your domain.');
            }
        }

        $data = $request->validate([
            'decision' => 'required|in:approve,reject',
            'notes'    => 'required|string|max:1000',
        ]);

        try {
            $exRequest->load(['period', 'employee.basicData']);
            $periodLabel = $exRequest->period?->getLabel() ?? "Period #{$exRequest->period_id}";
            $empId       = $exRequest->employee_id;
            $empName     = $exRequest->employee?->basicData?->full_name ?? "EMP#{$empId}";
            $headName    = $this->actorName();

            if ($data['decision'] === 'approve') {
                $this->svc->headApproveLateRequest($exRequest, $this->actorId(), $roleId, $data['notes']);
                $msg = 'Request approved. Waiting for RPMO approval.';

                // Notify the employee
                $this->notify(
                    $empId,
                    'late_exception_head_approved',
                    $headName,
                    "Your late access request for {$periodLabel} was approved by {$headName}. Waiting for RPMO approval.",
                    '/calendar/timesheets'
                );

                // Notify all RPMO
                $this->notifyRoles(
                    [RoleId::RPMO->value],
                    'late_exception_pending_rpmo',
                    $headName,
                    "{$empName}'s late access request for {$periodLabel} was approved by Head {$headName}. Your review is needed.",
                    '/rpmo/periods'
                );
            } else {
                $this->svc->headRejectLateRequest($exRequest, $this->actorId(), $roleId, $data['notes']);
                $msg = 'Request rejected.';

                // Notify the employee
                $this->notify(
                    $empId,
                    'late_exception_head_rejected',
                    $headName,
                    "Your late access request for {$periodLabel} was rejected by {$headName}. Reason: {$data['notes']}",
                    '/calendar/timesheets'
                );
            }

            return response()->json([
                'success' => true,
                'message' => $msg,
                'data'    => $this->formatRequest($exRequest->fresh()),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->invalid($e->getMessage());
        }
    }

    // ── API: RPMO approve/reject (Level 2) ───────────────────────────────────

    public function rpmoDecideRequest(Request $request, PeriodLateExceptionRequest $exRequest): JsonResponse
    {
        $roleId  = $this->actorRoleId();
        $isAdmin = $this->isAdmin();
        $isRpmo  = $roleId === RoleId::RPMO->value;

        if (!$isAdmin && !$isRpmo) {
            return $this->unauthorized('Only RPMO or Admin can approve at Level 2.');
        }

        $data = $request->validate([
            'decision'   => 'required|in:approve,reject',
            'notes'      => 'required|string|max:1000',
            // expires_at is required when approving
            'expires_at' => 'required_if:decision,approve|nullable|date|after:now',
        ]);

        try {
            $exRequest->load(['period', 'employee.basicData']);
            $periodLabel = $exRequest->period?->getLabel() ?? "Period #{$exRequest->period_id}";
            $empId       = $exRequest->employee_id;
            $rpmoName    = $this->actorName();

            if ($data['decision'] === 'approve') {
                // Parse the local-time string with the app timezone so the stored value and display both reflect what the user picked
                $expiresAt = Carbon::parse($data['expires_at'], config('app.timezone'));
                $this->svc->rpmoApproveLateRequest(
                    $exRequest,
                    $this->actorId(),
                    $roleId,
                    $expiresAt,
                    $data['notes']
                );
                $msg = 'Late access approved. Access is active until ' . $expiresAt->format('d M Y, H:i') . '.';

                // Notify the employee
                $this->notify(
                    $empId,
                    'late_exception_approved',
                    $rpmoName,
                    "Your late access request for {$periodLabel} has been approved! Access is active until {$expiresAt->format('d M Y, H:i')}.",
                    '/calendar/timesheets'
                );
            } else {
                $this->svc->rpmoRejectLateRequest($exRequest, $this->actorId(), $roleId, $data['notes']);
                $msg = 'Request rejected.';

                // Notify the employee
                $this->notify(
                    $empId,
                    'late_exception_rejected',
                    $rpmoName,
                    "Your late access request for {$periodLabel} was rejected by RPMO {$rpmoName}. Reason: {$data['notes']}",
                    '/calendar/timesheets'
                );
            }

            return response()->json([
                'success' => true,
                'message' => $msg,
                'data'    => $this->formatRequest($exRequest->fresh()),
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->invalid($e->getMessage());
        }
    }

    // ── Private: format a request record for API response ────────────────────

    private function formatRequest(PeriodLateExceptionRequest $r): array
    {
        return [
            'id'               => $r->id,
            'period_id'        => $r->period_id,
            'period_label'     => $r->period?->getLabel(),
            'period_start'     => $r->period?->start_date?->format('d M Y'),
            'period_end'       => $r->period?->end_date?->format('d M Y'),
            'employee_id'      => $r->employee_id,
            'employee_name'    => $r->employee?->basicData?->full_name ?? "EMP#{$r->employee_id}",
            'domain'           => $r->domain,
            'notes'            => $r->notes,
            'status'           => $r->status,
            'status_label'     => $r->getStatusLabel(),
            'status_color'     => $r->getStatusColor(),
            'is_access_active' => $r->isAccessActive(),
            'is_expired'       => $r->isExpired(),
            'expires_at'       => $r->expires_at?->setTimezone(config('app.timezone'))->format('d M Y, H:i'),
            'expires_at_iso'   => $r->expires_at?->setTimezone(config('app.timezone'))->toIso8601String(),
            'head_name'        => $r->head?->basicData?->full_name,
            'head_approved_at' => $r->head_approved_at?->format('d M Y, H:i'),
            'head_notes'       => $r->head_notes,
            'rpmo_name'        => $r->rpmo?->basicData?->full_name,
            'rpmo_approved_at' => $r->rpmo_approved_at?->format('d M Y, H:i'),
            'rpmo_notes'       => $r->rpmo_notes,
            'rejected_by'      => $r->rejectedBy?->basicData?->full_name,
            'rejected_at'      => $r->rejected_at?->format('d M Y, H:i'),
            'rejection_notes'  => $r->rejection_notes,
            'created_at'       => $r->created_at?->format('d M Y, H:i'),
        ];
    }
}
