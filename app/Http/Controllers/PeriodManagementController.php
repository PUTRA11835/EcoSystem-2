<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use App\Models\Employee;
use App\Models\PeriodAuditLog;
use App\Models\PeriodLateException;
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

    // ── Web view ──────────────────────────────────────────────────────────────

    public function index()
    {
        if (!$this->authorizeRoles(RoleId::PERIOD_MANAGEMENT_GROUP)) {
            abort(403);
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
        $domainEmployees = collect();
        if (in_array($roleId, [RoleId::HEAD_OF_PROJECT->value, RoleId::HEAD_OF_SUPPORT->value])) {
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

    // ── API: Create period (RPMO only) ────────────────────────────────────────

    public function store(Request $request): JsonResponse
    {
        if ($this->actorRoleId() !== RoleId::RPMO->value) {
            return $this->unauthorized('Only RPMO Head can create periods.');
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

    // ── API: Open global (RPMO only) ──────────────────────────────────────────

    public function openGlobal(ReportingPeriod $period): JsonResponse
    {
        if ($this->actorRoleId() !== RoleId::RPMO->value) {
            return $this->unauthorized('Only RPMO Head can open periods globally.');
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

    // ── API: Close global (RPMO only) ─────────────────────────────────────────

    public function closeGlobal(ReportingPeriod $period): JsonResponse
    {
        if ($this->actorRoleId() !== RoleId::RPMO->value) {
            return $this->unauthorized('Only RPMO Head can close periods globally.');
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

    // ── API: Force close domain (RPMO only) ───────────────────────────────────

    public function forceCloseDomain(Request $request, ReportingPeriod $period): JsonResponse
    {
        if ($this->actorRoleId() !== RoleId::RPMO->value) {
            return $this->unauthorized('Only RPMO Head can force-close domains.');
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

    // ── API: Open domain (Project/Support Head only) ──────────────────────────

    public function openDomain(ReportingPeriod $period): JsonResponse
    {
        $roleId = $this->actorRoleId();
        if (!in_array($roleId, [RoleId::HEAD_OF_PROJECT->value, RoleId::HEAD_OF_SUPPORT->value])) {
            return $this->unauthorized('Only Project or Support Head can open domain periods.');
        }

        $domain = $this->svc->getDomainForRole($roleId);

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

    // ── API: Close domain (Project/Support Head only) ─────────────────────────

    public function closeDomain(ReportingPeriod $period): JsonResponse
    {
        $roleId = $this->actorRoleId();
        if (!in_array($roleId, [RoleId::HEAD_OF_PROJECT->value, RoleId::HEAD_OF_SUPPORT->value])) {
            return $this->unauthorized('Only Project or Support Head can close domain periods.');
        }

        $domain = $this->svc->getDomainForRole($roleId);

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

    // ── API: List late exceptions (Head only) ─────────────────────────────────

    public function listExceptions(ReportingPeriod $period): JsonResponse
    {
        $roleId = $this->actorRoleId();
        if (!in_array($roleId, [RoleId::HEAD_OF_PROJECT->value, RoleId::HEAD_OF_SUPPORT->value])) {
            return $this->unauthorized();
        }

        $domain = $this->svc->getDomainForRole($roleId);

        $exceptions = PeriodLateException::where('period_id', $period->id)
            ->where('domain', $domain)
            ->with(['employee.basicData', 'grantedBy.basicData'])
            ->get()
            ->map(fn($ex) => [
                'id'            => $ex->id,
                'employee_id'   => $ex->employee_id,
                'employee_name' => $ex->employee?->basicData?->full_name ?? "EMP#{$ex->employee_id}",
                'granted_by'    => $ex->grantedBy?->basicData?->full_name ?? 'Unknown',
                'granted_at'    => $ex->granted_at?->format('d M Y, H:i'),
                'expires_at'    => $ex->expires_at?->format('d M Y, H:i'),
                'notes'         => $ex->notes,
                'is_active'     => $ex->isActive(),
            ]);

        return response()->json(['success' => true, 'data' => $exceptions]);
    }

    // ── API: Grant late exception (Head only) ─────────────────────────────────

    public function grantException(Request $request, ReportingPeriod $period): JsonResponse
    {
        $roleId = $this->actorRoleId();
        if (!in_array($roleId, [RoleId::HEAD_OF_PROJECT->value, RoleId::HEAD_OF_SUPPORT->value])) {
            return $this->unauthorized();
        }

        $data   = $request->validate([
            'employee_id' => 'required|exists:employee,employee_id',
            'notes'       => 'nullable|string|max:500',
        ]);
        $domain = $this->svc->getDomainForRole($roleId);

        // Verify employee belongs to the head's domain
        $empRoleId = Employee::where('employee_id', $data['employee_id'])->value('role_id');
        $empDomain = $this->svc->getDomainForRole((int) $empRoleId);
        if ($empDomain !== $domain) {
            return $this->invalid('Employee does not belong to your domain.');
        }

        try {
            $exception = $this->svc->grantLateException(
                $period->id,
                $data['employee_id'],
                $domain,
                $this->actorId(),
                $roleId,
                $data['notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Late submission access granted.',
                'data'    => [
                    'id'            => $exception->id,
                    'employee_id'   => $exception->employee_id,
                    'employee_name' => $exception->employee?->basicData?->full_name ?? "EMP#{$exception->employee_id}",
                    'granted_at'    => $exception->granted_at?->format('d M Y, H:i'),
                    'notes'         => $exception->notes,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->invalid($e->getMessage());
        }
    }

    // ── API: Revoke late exception (Head only) ────────────────────────────────

    public function revokeException(ReportingPeriod $period, PeriodLateException $exception): JsonResponse
    {
        $roleId = $this->actorRoleId();
        if (!in_array($roleId, [RoleId::HEAD_OF_PROJECT->value, RoleId::HEAD_OF_SUPPORT->value])) {
            return $this->unauthorized();
        }

        // Verify the exception belongs to this period and this head's domain
        $domain = $this->svc->getDomainForRole($roleId);
        if ($exception->period_id !== $period->id || $exception->domain !== $domain) {
            return $this->unauthorized('Exception does not belong to your domain/period.');
        }

        try {
            $this->svc->revokeLateException($exception, $this->actorId(), $roleId);
            return response()->json(['success' => true, 'message' => 'Late submission access revoked.']);
        } catch (\Exception $e) {
            return $this->invalid($e->getMessage());
        }
    }

    // ── API: Active period (used by other pages) ──────────────────────────────

    public function activePeriod(): JsonResponse
    {
        $period = ReportingPeriod::getActive();
        return response()->json(['success' => true, 'data' => $period]);
    }
}
