<?php

namespace App\Services;

use App\Enums\RoleId;
use App\Models\Employee;
use App\Models\PeriodAuditLog;
use App\Models\PeriodLateException;
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
     * Returns null for roles not subject to period restrictions.
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
     * Returns ['allowed' => bool, 'reason' => string].
     *
     * Rules:
     *  - Roles without a domain (Admin, RPMO, Helpdesk, EC User, etc.) → always allowed.
     *  - Period must exist and be globally open.
     *  - Domain must be open.
     *  - Exception: if a late_exception exists for this employee + domain + period, allow even if closed.
     */
    public function canSubmitTimesheet(int $employeeId, Carbon $date): array
    {
        $roleId = Employee::where('employee_id', $employeeId)->value('role_id');
        if ($roleId === null) {
            return ['allowed' => false, 'reason' => 'Employee not found.'];
        }

        $roleId = (int) $roleId;
        $domain = $this->getDomainForRole($roleId);

        // Roles without a domain bypass period restrictions
        if ($domain === null) {
            return ['allowed' => true, 'reason' => ''];
        }

        // Resolve period record for this date
        $p      = ReportingPeriod::periodFor($date);
        $period = ReportingPeriod::where('year', $p['year'])->where('month', $p['month'])->first();

        if (!$period) {
            return [
                'allowed' => false,
                'reason'  => 'Period not available. Waiting for RPMO to create and open this period.',
            ];
        }

        // Helper: check late exception (bypasses all closures)
        $hasException = fn() => PeriodLateException::where('period_id', $period->id)
            ->where('employee_id', $employeeId)
            ->where('domain', $domain)
            ->where(fn($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->exists();

        // Global status check
        if ($period->global_status === 'not_open') {
            return [
                'allowed' => false,
                'reason'  => 'Period not available. Waiting for RPMO to open.',
            ];
        }

        if ($period->global_status === 'closed') {
            if ($hasException()) return ['allowed' => true, 'reason' => ''];
            $head = $domain === self::DOMAIN_PROJECT ? 'Project Head' : 'Support Head';
            return [
                'allowed' => false,
                'reason'  => "Period is closed. Contact {$head} for late submission access.",
            ];
        }

        // Domain status check
        $domainStatus = $period->domainStatus($domain);
        $domainLabel  = ucfirst($domain);

        if ($domainStatus === 'not_open') {
            return [
                'allowed' => false,
                'reason'  => "{$domainLabel} period has not been opened by {$domainLabel} Head.",
            ];
        }

        if ($domainStatus === 'closed') {
            if ($hasException()) return ['allowed' => true, 'reason' => ''];
            $head = $domain === self::DOMAIN_PROJECT ? 'Project Head' : 'Support Head';
            return [
                'allowed' => false,
                'reason'  => "{$domainLabel} period is closed. Contact {$head} for late submission access.",
            ];
        }

        return ['allowed' => true, 'reason' => ''];
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

        // Check for date overlap
        $overlap = ReportingPeriod::where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
              ->orWhereBetween('end_date', [$startDate, $endDate])
              ->orWhere(function ($q2) use ($startDate, $endDate) {
                  $q2->where('start_date', '<=', $startDate)
                     ->where('end_date', '>=', $endDate);
              });
        })->exists();

        if ($overlap) {
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
     * RPMO opens a period globally.
     * Only one period can be globally open at a time.
     */
    public function openGlobal(ReportingPeriod $period, int $actorId, int $actorRoleId): void
    {
        if ($period->global_status !== 'not_open') {
            throw new \InvalidArgumentException(
                'Period must be in "Not Open" state to be opened globally.'
            );
        }

        if (ReportingPeriod::where('global_status', 'open')->where('id', '!=', $period->id)->exists()) {
            throw new \InvalidArgumentException(
                'Another period is already globally open. Close it before opening a new one.'
            );
        }

        DB::transaction(function () use ($period, $actorId, $actorRoleId) {
            $period->update([
                'global_status' => 'open',
                'opened_at'     => now(),
                'opened_by'     => $actorId,
            ]);
            $this->log($period, 'global_open', $actorId, $actorRoleId);
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
     * RPMO force-closes a specific domain (overrides normal closing flow).
     * Logged as a force action with warning in audit trail.
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
            throw new \InvalidArgumentException(
                ucfirst($domain) . ' domain is already closed.'
            );
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

    /**
     * Head opens their domain.
     * Requires global_status = 'open'.
     */
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
            throw new \InvalidArgumentException(
                ucfirst($domain) . ' domain is already open.'
            );
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

    /**
     * Head closes their domain.
     */
    public function closeDomain(
        ReportingPeriod $period,
        string          $domain,
        int             $actorId,
        int             $actorRoleId
    ): void {
        $this->assertValidDomain($domain);

        $statusCol = "{$domain}_status";
        if ($period->{$statusCol} !== 'open') {
            throw new \InvalidArgumentException(
                ucfirst($domain) . ' domain is not open.'
            );
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

    // ── Late exception management ─────────────────────────────────────────────

    /**
     * Grant a late submission exception for a specific employee + domain.
     * Head only. Upserts — calling again refreshes notes/granted_at.
     */
    public function grantLateException(
        int     $periodId,
        int     $employeeId,
        string  $domain,
        int     $actorId,
        int     $actorRoleId,
        ?string $notes = null
    ): PeriodLateException {
        $this->assertValidDomain($domain);
        $period = ReportingPeriod::findOrFail($periodId);

        return DB::transaction(function () use ($period, $employeeId, $domain, $actorId, $actorRoleId, $notes) {
            $exception = PeriodLateException::updateOrCreate(
                ['period_id' => $period->id, 'employee_id' => $employeeId, 'domain' => $domain],
                ['granted_by' => $actorId, 'granted_at' => now(), 'notes' => $notes]
            );

            $this->log($period, "late_exception_granted_{$domain}", $actorId, $actorRoleId, metadata: [
                'employee_id' => $employeeId,
                'notes'       => $notes,
            ]);

            return $exception;
        });
    }

    /**
     * Revoke a late submission exception.
     */
    public function revokeLateException(
        PeriodLateException $exception,
        int                 $actorId,
        int                 $actorRoleId
    ): void {
        $period = $exception->period;

        DB::transaction(function () use ($exception, $period, $actorId, $actorRoleId) {
            $this->log($period, "late_exception_revoked_{$exception->domain}", $actorId, $actorRoleId, metadata: [
                'employee_id' => $exception->employee_id,
            ]);
            $exception->delete();
        });
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
