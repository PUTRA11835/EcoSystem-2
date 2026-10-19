<?php

namespace App\Services\Approval;

use App\Models\Employee;
use App\Models\EmployeeBasicData;
use Illuminate\Support\Facades\DB;

/**
 * "Who is this person's approver?" answered from the master data, for approval flows that do not name a fixed person:
 *
 *   SUPERVISOR      the employee's direct supervisor — Master Employee › Basic Data › Direct Supervision
 *                   (stored as an employee_id; a legacy ECI is still understood)
 *   PROJECT LEADER  the leaders (Lead · Project Manager · Co Project Manager) of the delivery project(s) the employee is a
 *                   member of on that day. An employee in no project has none — the flow then falls back (see the callers).
 *
 * Used by Overtime (an approval step of type "supervisor" / "project leader") and by Timesheet (Settings → who approves).
 */
class ApproverResolver
{
    /** The member roles that count as the leader of a project. */
    public const LEAD_ROLES = ['Lead', 'Project Manager', 'Co Project Manager'];

    /** @var array<int,?int> */
    private array $supervisors = [];

    /** The employee's direct supervisor, or null (none set, unknown, or the employee themself). */
    public function supervisorOf(int $employeeId): ?int
    {
        if (!array_key_exists($employeeId, $this->supervisors)) {
            $value = DB::table('employee_basic_data')->where('employee_id', $employeeId)->value('direct_supervision');
            $id = EmployeeBasicData::resolveSupervisorId($value);
            $this->supervisors[$employeeId] = $id !== null && $id !== $employeeId ? $id : null;
        }

        return $this->supervisors[$employeeId];
    }

    /**
     * The people this employee supervises.
     *
     * @return int[]
     */
    public function superviseesOf(int $leaderId): array
    {
        $eci = Employee::where('employee_id', $leaderId)->value('eci');

        return DB::table('employee_basic_data')
            ->whereIn('direct_supervision', array_filter([(string) $leaderId, $eci]))
            ->where('employee_id', '!=', $leaderId)
            ->pluck('employee_id')->map(fn ($i) => (int) $i)->unique()->values()->all();
    }

    /**
     * The leaders of the project(s) an employee belongs to on a day — of one project when $projectId is given.
     *
     * @return int[]
     */
    public function projectLeadersOf(int $employeeId, string $date, ?int $projectId = null): array
    {
        $projects = $this->activeMembership($employeeId, $date, $date, $projectId);
        if (!$projects) {
            return [];
        }

        return DB::table('delivery_project_employee')
            ->whereIn('delivery_projects_id', $projects)->whereIn('role', self::LEAD_ROLES)
            ->whereNotNull('employee_id')->where('employee_id', '!=', $employeeId)
            ->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', $date))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $date))
            ->pluck('employee_id')->map(fn ($i) => (int) $i)->unique()->values()->all();
    }

    /**
     * Everyone who is a member of a project this leader leads during [$from, $to], with the project ids.
     *
     * @return array<int,int[]> employee_id => project ids
     */
    public function ledBy(int $leaderId, string $from, string $to): array
    {
        $projects = DB::table('delivery_project_employee')->where('employee_id', $leaderId)->whereIn('role', self::LEAD_ROLES)
            ->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', $to))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $from))
            ->pluck('delivery_projects_id')->unique()->all();
        if (!$projects) {
            return [];
        }

        $out = [];
        $rows = DB::table('delivery_project_employee')->whereIn('delivery_projects_id', $projects)
            ->whereNotNull('employee_id')->where('employee_id', '!=', $leaderId)
            ->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', $to))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $from))
            ->get(['employee_id', 'delivery_projects_id']);
        foreach ($rows as $r) {
            $out[(int) $r->employee_id][] = (int) $r->delivery_projects_id;
        }

        return $out;
    }

    /** Whether the employee supervises anyone. */
    public function supervisesAnyone(int $employeeId): bool
    {
        return $this->superviseesOf($employeeId) !== [];
    }

    /** Whether the employee leads a project at all (any time) — for opening a page before there is anything to review. */
    public function leadsAnyProject(int $employeeId): bool
    {
        return DB::table('delivery_project_employee')->where('employee_id', $employeeId)->whereIn('role', self::LEAD_ROLES)->exists();
    }

    /** @return int[] project ids the employee belongs to between two days (one project when $projectId is given) */
    private function activeMembership(int $employeeId, string $from, string $to, ?int $projectId): array
    {
        return DB::table('delivery_project_employee')->where('employee_id', $employeeId)
            ->when($projectId, fn ($q) => $q->where('delivery_projects_id', $projectId))
            ->where(fn ($q) => $q->whereNull('start_date')->orWhere('start_date', '<=', $to))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $from))
            ->pluck('delivery_projects_id')->unique()->values()->all();
    }
}
