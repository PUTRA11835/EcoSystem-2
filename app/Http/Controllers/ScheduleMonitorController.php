<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use App\Services\ScheduleMonitorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScheduleMonitorController extends Controller
{
    private function assertAdmin(): bool
    {
        return (int) session('user.role.id') === RoleId::EC_ADMINISTRATOR->value;
    }

    public function page()
    {
        if (!$this->assertAdmin()) {
            abort(403);
        }

        return view('admin.schedule-monitor');
    }

    /** Admin-only: live backlog snapshot for every supervised queue worker. */
    public function queueHealth(Request $request)
    {
        if (!$this->assertAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $data = ScheduleMonitorService::getQueueHealth();

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /** Admin-only: live disk space (total/used/free) plus a content breakdown. */
    public function diskUsage(Request $request)
    {
        if (!$this->assertAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success'   => true,
            'space'     => ScheduleMonitorService::getDiskSpace(),
            'breakdown' => ScheduleMonitorService::getDiskBreakdown(),
        ]);
    }

    /** Admin-only: live status of every known scheduled task. */
    public function index(Request $request)
    {
        if (!$this->assertAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $data = ScheduleMonitorService::getStatusData();

        // Pulled out of the regular task list - it's not a real task, it's
        // proof-of-life for the trigger itself (see ScheduleHeartbeat), and
        // showing it twice (banner + a card indistinguishable from the 7
        // real ones) would just be confusing. Keeping it out of $data also
        // keeps "Known Tasks" honestly at 7, not 8.
        $heartbeat = null;
        $data = array_values(array_filter($data, function ($entry) use (&$heartbeat) {
            if ($entry['task_name'] === 'scheduler-heartbeat') {
                $heartbeat = $entry;
                return false;
            }
            return true;
        }));

        return response()->json([
            'success'   => true,
            'data'      => $data,
            'heartbeat' => $heartbeat,
            'summary' => [
                'total'   => count($data),
                // "stale" and "failed" can overlap (a task that failed a while
                // ago and hasn't run since is both) - each task is counted at
                // most once here so the headline number never double-counts.
                'issues'  => count(array_filter($data, fn ($e) => $e['is_stale'] || $e['status'] === 'failed')),
                'stale'   => count(array_filter($data, fn ($e) => $e['is_stale'])),
                'failing' => count(array_filter($data, fn ($e) => $e['status'] === 'failed')),
            ],
        ]);
    }

    /**
     * Admin-only: paginated log of past failures/skips (successes are never
     * logged per-row - see ScheduleMonitorService - only the latest state).
     */
    public function getRuns(Request $request)
    {
        if (!$this->assertAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $perPage  = max(1, min((int) $request->input('per_page', 200), 500));
        $taskName = $request->input('task_name', '');
        $status   = $request->input('status', '');

        $query = DB::table('scheduled_task_runs');

        if ($taskName !== '') {
            $query->where('task_name', $taskName);
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        $sortColumns = [
            'task_name' => 'task_name',
            'status'    => 'status',
            'time'      => 'created_at',
        ];
        $sortBy  = $sortColumns[$request->input('sort_by')] ?? 'created_at';
        $sortDir = $request->input('sort_dir') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);
        if ($sortBy !== 'created_at') {
            $query->orderByDesc('created_at'); // stable tie-break
        }

        $records = $query->paginate($perPage);

        $commands = config('schedule_monitor.commands', []);

        $items = collect($records->items())->map(function ($row) use ($commands) {
            return [
                'id'          => $row->id,
                'task_name'   => $row->task_name,
                'label'       => $commands[$row->task_name]['label'] ?? $row->task_name,
                'status'      => $row->status,
                'started_at'  => $row->started_at
                    ? \Carbon\Carbon::parse($row->started_at)->setTimezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB'
                    : '-',
                'finished_at' => $row->finished_at
                    ? \Carbon\Carbon::parse($row->finished_at)->setTimezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB'
                    : '-',
                'duration_ms' => $row->duration_ms,
                'exit_code'   => $row->exit_code,
                'error'       => $row->error,
                'created_at'  => \Carbon\Carbon::parse($row->created_at)->setTimezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB',
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $items,
            'meta'    => [
                'total'        => $records->total(),
                'per_page'     => $records->perPage(),
                'current_page' => $records->currentPage(),
                'last_page'    => $records->lastPage(),
            ],
        ]);
    }
}
