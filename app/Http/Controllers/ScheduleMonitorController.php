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

    /** Admin-only: live status of every known scheduled task. */
    public function index(Request $request)
    {
        if (!$this->assertAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $data = ScheduleMonitorService::getStatusData();

        return response()->json([
            'success' => true,
            'data'    => $data,
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
