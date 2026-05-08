<?php

namespace App\Services;

use App\Enums\RoleId;
use App\Models\Employee;
use App\Models\PeriodAuditLog;
use App\Models\PeriodLateExceptionRequest;
use App\Models\ReportingPeriod;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PeriodService
{
    // ── Domain constants ──────────────────────────────────────────────────────

    const DOMAIN_PROJECT = 'project';
    const DOMAIN_SUPPORT = 'support';

    // ── Domain resolution ─────────────────────────────────────────────────────

    /**
     * Get the timesheet domain ('project' | 'support' | null) for a given role ID.
     * Returns null for roles not subject to period restrictions (Admin, RPMO, etc.).
     */
    public function getDomainForRole(int $roleId): ?string
    {
        return match($roleId) {
            RoleId::EMPLOYEE_PROJECT->value, RoleId::HEAD_OF_PROJECT->value => self::DOMAIN_PROJECT,
            RoleId::EMPLOYEE->value,         RoleId::HEAD_OF_SUPPORT->value => self::DOMAIN_SUPPORT,
            default => null,
        };
    }

    // ── Period access validation ──────────────────────────────────────────────

    /**
     * Check if an employee can submit a timesheet for a given date.
     *
     * Rules:
     *  1. Roles without a domain (Admin, RPMO, etc.) → always allowed.
     *  2. The date's period must be within the active window:
     *       - Current period  (the one today falls in)
     *       - Previous period (one month before current)
     *  3. Outside the active window → only allowed with an approved, unexpired
     *     PeriodLateExceptionRequest for that specific period + employee + domain.
     *  4. Inside the active window → period must be globally open and domain must be open.
     *
     * Returns ['allowed' => bool, 'reason' => string].
     */
    public function canSubmitTimesheet(int $employeeId, Carbon $date): array
    {
        $roleId = Employee::where('employee_id', $employeeId)->value('role_id');
        if ($roleId === null) {
            return ['allowed' => false, 'reason' => 'Employee not found.'];
        }

        $roleId = (int) $roleId;
        $domain = $this->getDomainForRole($roleId);

        // Roles without a domain bypass all period restrictions
        if ($domain === null) {
            return ['allowed' => true, 'reason' => ''];
        }

        // Resolve period for the submitted date
        $dateCoords = ReportingPeriod::periodFor($date);
        $period     = ReportingPeriod::where('year', $dateCoords['year'])
            ->where('month', $dateCoords['month'])
            ->first();

        if (!$period) {
            return [
                'allowed' => false,
                'reason'  => 'Period not available. Waiting for RPMO to create and open this period.',
            ];
        }

        // ── Compute active window: current + previous period ──────────────────
        $now          = now();
        $curCoords    = ReportingPeriod::periodFor($now);
        $prevMonth    = $curCoords['month'] === 1 ? 12 : $curCoords['month'] - 1;
        $prevYear     = $curCoords['month'] === 1 ? $curCoords['year'] - 1 : $curCoords['year'];

        $isCurrentPeriod  = $dateCoords['year']  == $curCoords['year']
                         && $dateCoords['month'] == $curCoords['month'];
        $isPreviousPeriod = $dateCoords['year']  == $prevYear
                         && $dateCoords['month'] == $prevMonth;
        $inActiveWindow   = $isCurrentPeriod || $isPreviousPeriod;

        // ── Outside active window → require approved late exception request ────
        if (!$inActiveWindow) {
            $hasActiveException = PeriodLateExceptionRequest::where('period_id', $period->id)
                ->where('employee_id', $employeeId)
                ->where('domain', $domain)
                ->where('status', 'approved')
                ->where('expires_at', '>', $now)
                ->exists();

            if (!$hasActiveException) {
                return [
                    'allowed' => false,
                    'reason'  => 'You can only submit timesheets for the current period or the previous period. '
                               . 'For older periods, submit a Late Exception Request.',
                ];
            }

            // Late exception is valid → bypass period/domain status checks
            return ['allowed' => true, 'reason' => ''];
        }

        // ── Inside active window → standard period/domain status checks ───────
        if ($period->global_status === 'not_open') {
            return [
                'allowed' => false,
                'reason'  => 'Period not yet opened. Waiting for RPMO to open this period.',
            ];
        }

        if ($period->global_status === 'closed') {
            return [
                'allowed' => false,
                'reason'  => 'Period is closed.',
            ];
        }

        $domainStatus = $period->domainStatus($domain);
        $domainLabel  = ucfirst($domain);

        if ($domainStatus === 'not_open') {
            return [
                'allowed' => false,
                'reason'  => "{$domainLabel} domain has not been opened by {$domainLabel} Head.",
            ];
        }

        if ($domainStatus === 'closed') {
            return [
                'allowed' => false,
                'reason'  => "{$domainLabel} domain is closed.",
            ];
        }

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * Return the 2 periods that are in the active window for a given date.
     * Used by the frontend to inform users which periods they can normally submit to.
     */
    public function getActiveWindowPeriods(Carbon $reference = null): array
    {
        $now       = $reference ?? now();
        $cur       = ReportingPeriod::periodFor($now);
        $prevMonth = $cur['month'] === 1 ? 12 : $cur['month'] - 1;
        $prevYear  = $cur['month'] === 1 ? $cur['year'] - 1 : $cur['year'];

        $current  = ReportingPeriod::where('year', $cur['year'])->where('month', $cur['month'])->first();
        $previous = ReportingPeriod::where('year', $prevYear)->where('month', $prevMonth)->first();

        return array_filter([$current, $previous]);
    }

    // ── RPMO actions ──────────────────────────────────────────────────────────

    /**
     * Create a new period (RPMO only).
     * @throws \InvalidArgumentException on duplicate or overlap.
     */
    public function createPeriod(
        int    $year,
        int    $month,
        string $startDate,
        string $endDate,
        int    $actorId,
        int    $actorRoleId
    ): ReportingPeriod {
        if (ReportingPeriod::where('year', $year)->where('month', $month)->exists()) {
            throw new \InvalidArgumentException("Period {$year}-{$month} already exists.");
        }

        if (ReportingPeriod::hasOverlap($startDate, $endDate)) {
            throw new \InvalidArgumentException('Period dates overlap with an existing period.');
        }

        return DB::transaction(function () use ($year, $month, $startDate, $endDate, $actorId, $actorRoleId) {
            $period = ReportingPeriod::create([
                'year'           => $year,
                'month'          => $month,
                'start_date'     => $startDate,
                'end_date'       => $endDate,
                'global_status'  => 'not_open',
                'project_status' => 'not_open',
                'support_status' => 'not_open',
            ]);

            $this->log($period, 'period_created', $actorId, $actorRoleId);
            return $period;
        });
    }

    /**
     * RPMO opens (or re-opens) a period globally.
     * Allowed from: 'not_open' or 'closed' → 'open'.
     */
    public function openGlobal(ReportingPeriod $period, int $actorId, int $actorRoleId): void
    {
        if ($period->global_status === 'open') {
            throw new \InvalidArgumentException('Period is already globally open.');
        }

        if (ReportingPeriod::where('global_status', 'open')->where('id', '!=', $period->id)->exists()) {
            throw new \InvalidArgumentException(
                'Another period is already globally open. Close it before opening a new one.'
            );
        }

        $isReopen = $period->global_status === 'closed';

        DB::transaction(function () use ($period, $actorId, $actorRoleId, $isReopen) {
            $period->update([
                'global_status' => 'open',
                'opened_at'     => now(),
                'opened_by'     => $actorId,
            ]);
            $this->log($period, $isReopen ? 'global_reopen' : 'global_open', $actorId, $actorRoleId);
        });
    }

    /**
     * RPMO closes a period globally.
     * Both project and support domains must be closed first.
     */
    public function closeGlobal(ReportingPeriod $period, int $actorId, int $actorRoleId): void
    {
        if ($period->global_status !== 'open') {
            throw new \InvalidArgumentException('Period is not globally open.');
        }

        if ($period->project_status === 'open') {
            throw new \InvalidArgumentException(
                'Cannot close period globally. Project domain is still open.'
            );
        }

        if ($period->support_status === 'open') {
            throw new \InvalidArgumentException(
                'Cannot close period globally. Support domain is still open.'
            );
        }

        DB::transaction(function () use ($period, $actorId, $actorRoleId) {
            $period->update([
                'global_status' => 'closed',
                'closed_at'     => now(),
                'closed_by'     => $actorId,
            ]);
            $this->log($period, 'global_close', $actorId, $actorRoleId);
        });
    }

    /**
     * RPMO force-closes a specific domain.
     */
    public function forceCloseDomain(
        ReportingPeriod $period,
        string          $domain,
        int             $actorId,
        int             $actorRoleId
    ): void {
        $this->assertValidDomain($domain);

        if ($period->global_status === 'not_open') {
            throw new \InvalidArgumentException(
                'Cannot force-close a domain on a period that has not been opened globally.'
            );
        }

        $statusCol = "{$domain}_status";
        if ($period->{$statusCol} === 'closed') {
            throw new \InvalidArgumentException(ucfirst($domain) . ' domain is already closed.');
        }

        DB::transaction(function () use ($period, $domain, $actorId, $actorRoleId) {
            $period->update([
                "{$domain}_status"    => 'closed',
                "{$domain}_closed_at" => now(),
                "{$domain}_closed_by" => $actorId,
            ]);
            $this->log($period, "force_close_{$domain}", $actorId, $actorRoleId, isForce: true);
        });
    }

    // ── Head actions ──────────────────────────────────────────────────────────

    public function openDomain(
        ReportingPeriod $period,
        string          $domain,
        int             $actorId,
        int             $actorRoleId
    ): void {
        $this->assertValidDomain($domain);

        if ($period->global_status !== 'open') {
            throw new \InvalidArgumentException(
                'Cannot open domain — period is not globally open. Waiting for RPMO.'
            );
        }

        $statusCol = "{$domain}_status";
        if ($period->{$statusCol} === 'open') {
            throw new \InvalidArgumentException(ucfirst($domain) . ' domain is already open.');
        }

        DB::transaction(function () use ($period, $domain, $actorId, $actorRoleId) {
            $period->update([
                "{$domain}_status"    => 'open',
                "{$domain}_opened_at" => now(),
                "{$domain}_opened_by" => $actorId,
            ]);
            $this->log($period, "{$domain}_open", $actorId, $actorRoleId);
        });
    }

    public function closeDomain(
        ReportingPeriod $period,
        string          $domain,
        int             $actorId,
        int             $actorRoleId
    ): void {
        $this->assertValidDomain($domain);

        $statusCol = "{$domain}_status";
        if ($period->{$statusCol} !== 'open') {
            throw new \InvalidArgumentException(ucfirst($domain) . ' domain is not open.');
        }

        DB::transaction(function () use ($period, $domain, $actorId, $actorRoleId) {
            $period->update([
                "{$domain}_status"    => 'closed',
                "{$domain}_closed_at" => now(),
                "{$domain}_closed_by" => $actorId,
            ]);
            $this->log($period, "{$domain}_close", $actorId, $actorRoleId);
        });
    }

    // ── Late exception REQUEST flow (2-level approval, request-based only) ────

    /**
     * Employee submits a request for late timesheet access for a closed/old period.
     * One active request per (period, employee). Re-submission allowed only if previously rejected.
     *
     * @throws \InvalidArgumentException if a pending/approved request already exists.
     */
    public function createLateExceptionRequest(
        int     $periodId,
        int     $employeeId,
        string  $domain,
        ?string $notes = null
    ): PeriodLateExceptionRequest {
        $this->assertValidDomain($domain);
        ReportingPeriod::findOrFail($periodId); // ensure period exists

        $existing = PeriodLateExceptionRequest::where('period_id', $periodId)
            ->where('employee_id', $employeeId)
            ->first();

        if ($existing) {
            if ($existing->isPending()) {
                throw new \InvalidArgumentException(
                    'You already have a pending request for this period.'
                );
            }
            if ($existing->isAccessActive()) {
                throw new \InvalidArgumentException(
                    'A request for this period has already been approved and access is still active.'
                );
            }
            // Expired or rejected → allow re-submission
            $existing->update([
                'domain'           => $domain,
                'notes'            => $notes,
                'status'           => 'pending_head',
                'head_id'          => null,
                'head_approved_at' => null,
                'head_notes'       => null,
                'rpmo_id'          => null,
                'rpmo_approved_at' => null,
                'rpmo_notes'       => null,
                'rejected_by'      => null,
                'rejected_at'      => null,
                'rejection_notes'  => null,
                'expires_at'       => null,
            ]);
            return $existing->fresh();
        }

        return PeriodLateExceptionRequest::create([
            'period_id'   => $periodId,
            'employee_id' => $employeeId,
            'domain'      => $domain,
            'notes'       => $notes,
            'status'      => 'pending_head',
        ]);
    }

    /**
     * Head approves the request (Level 1) → moves to pending_rpmo.
     */
    public function headApproveLateRequest(
        PeriodLateExceptionRequest $req,
        int                        $headId,
        int                        $headRoleId,
        ?string                    $notes = null
    ): void {
        if ($req->status !== 'pending_head') {
            throw new \InvalidArgumentException('Request is not pending Head approval.');
        }

        $req->update([
            'status'           => 'pending_rpmo',
            'head_id'          => $headId,
            'head_approved_at' => now(),
            'head_notes'       => $notes,
        ]);
    }

    /**
     * Head rejects the request — flow ends.
     */
    public function headRejectLateRequest(
        PeriodLateExceptionRequest $req,
        int                        $headId,
        int                        $headRoleId,
        ?string                    $reason = null
    ): void {
        if ($req->status !== 'pending_head') {
            throw new \InvalidArgumentException('Request is not pending Head approval.');
        }

        $req->update([
            'status'          => 'rejected',
            'rejected_by'     => $headId,
            'rejected_at'     => now(),
            'rejection_notes' => $reason,
        ]);
    }

    /**
     * RPMO approves the request (Level 2).
     * Must provide an expires_at deadline — this controls when access expires.
     * No separate PeriodLateException is created; access is determined by this record.
     *
     * @throws \InvalidArgumentException if deadline is in the past or missing.
     */
    public function rpmoApproveLateRequest(
        PeriodLateExceptionRequest $req,
        int                        $rpmoId,
        int                        $rpmoRoleId,
        Carbon                     $expiresAt,
        ?string                    $notes = null
    ): PeriodLateExceptionRequest {
        if ($req->status !== 'pending_rpmo') {
            throw new \InvalidArgumentException('Request is not pending RPMO approval.');
        }

        if ($expiresAt->isPast()) {
            throw new \InvalidArgumentException('Access deadline cannot be in the past.');
        }

        $req->update([
            'status'           => 'approved',
            'rpmo_id'          => $rpmoId,
            'rpmo_approved_at' => now(),
            'rpmo_notes'       => $notes,
            'expires_at'       => $expiresAt,
        ]);

        return $req->fresh();
    }

    /**
     * RPMO rejects the request — flow ends.
     */
    public function rpmoRejectLateRequest(
        PeriodLateExceptionRequest $req,
        int                        $rpmoId,
        int                        $rpmoRoleId,
        ?string                    $reason = null
    ): void {
        if ($req->status !== 'pending_rpmo') {
            throw new \InvalidArgumentException('Request is not pending RPMO approval.');
        }

        $req->update([
            'status'          => 'rejected',
            'rejected_by'     => $rpmoId,
            'rejected_at'     => now(),
            'rejection_notes' => $reason,
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function assertValidDomain(string $domain): void
    {
        if (!in_array($domain, [self::DOMAIN_PROJECT, self::DOMAIN_SUPPORT], true)) {
            throw new \InvalidArgumentException("Invalid domain: {$domain}");
        }
    }

    private function log(
        ReportingPeriod $period,
        string          $action,
        int             $actorId,
        int             $actorRoleId,
        bool            $isForce = false,
        ?array          $metadata = null
    ): void {
        PeriodAuditLog::create([
            'period_id'     => $period->id,
            'action'        => $action,
            'actor_id'      => $actorId,
            'actor_role_id' => $actorRoleId,
            'is_force'      => $isForce,
            'metadata'      => $metadata,
        ]);
    }
}
