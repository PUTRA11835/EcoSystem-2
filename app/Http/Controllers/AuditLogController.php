<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Admin-only: render the audit log page.
     */
    public function index(Request $request)
    {
        $user = session('user');

        if (($user['role']['id'] ?? 0) !== RoleId::EC_ADMINISTRATOR->value) {
            abort(403, 'Access denied.');
        }

        return view('admin.audit-log', [
            'user' => $user,
        ]);
    }

    /**
     * Admin-only: return paginated audit log data as JSON.
     * GET /api/admin/audit-logs?page=1&per_page=25&search=&module=&event=&date_from=&date_to=
     */
    public function getData(Request $request)
    {
        $user = session('user');

        if (($user['role']['id'] ?? 0) !== RoleId::EC_ADMINISTRATOR->value) {
            return response()->json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $perPage  = max(1, min((int) $request->input('per_page', 200), 500));
        $search   = trim($request->input('search', ''));
        $module   = $request->input('module', '');
        $event    = $request->input('event', '');
        $dateFrom = $request->input('date_from', '');
        $dateTo   = $request->input('date_to', '');

        $query = AuditLog::query()->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('record_label', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('actor_name', 'like', "%{$search}%")
                  ->orWhere('auditable_type', 'like', "%{$search}%");
            });
        }

        if ($module !== '') {
            $query->where('module', $module);
        }

        if ($event !== '') {
            $query->where('event', $event);
        }

        if ($dateFrom !== '') {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $records = $query->paginate($perPage);

        $items = collect($records->items())->map(function (AuditLog $row) {
            return [
                'id'             => $row->id,
                'module'         => $row->module,
                'auditable_type' => $row->auditable_type,
                'auditable_id'   => $row->auditable_id,
                'record_label'   => $row->record_label ?? '-',
                'description'    => $row->description ?? '-',
                'event'          => $row->event,
                'event_label'    => $row->getEventLabel(),
                'event_color'    => $row->getEventColor(),
                'actor_name'     => $row->actor_name ?? 'System',
                'actor_role_id'  => $row->actor_role_id,
                'old_values'     => $row->old_values,
                'new_values'     => $row->new_values,
                'ip_address'     => $row->ip_address ?? '-',
                'created_at'     => $row->created_at
                    ? $row->created_at->setTimezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB'
                    : '-',
            ];
        });

        $statsQuery = AuditLog::query();
        $stats = [
            'created' => (clone $statsQuery)->where('event', 'created')->count(),
            'updated' => (clone $statsQuery)->where('event', 'updated')->count(),
            'deleted' => (clone $statsQuery)->where('event', 'deleted')->count(),
        ];

        return response()->json([
            'success' => true,
            'data'    => $items,
            'stats'   => $stats,
            'meta'    => [
                'total'        => $records->total(),
                'per_page'     => $records->perPage(),
                'current_page' => $records->currentPage(),
                'last_page'    => $records->lastPage(),
            ],
        ]);
    }

    /**
     * Admin-only: distinct module values for the filter dropdown.
     * GET /api/admin/audit-logs/modules
     */
    public function modules(Request $request)
    {
        $user = session('user');

        if (($user['role']['id'] ?? 0) !== RoleId::EC_ADMINISTRATOR->value) {
            return response()->json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $modules = AuditLog::query()
            ->distinct()
            ->orderBy('module')
            ->pluck('module');

        return response()->json(['success' => true, 'data' => $modules]);
    }
}
