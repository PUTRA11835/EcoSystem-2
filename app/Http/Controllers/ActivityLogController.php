<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use App\Models\LoginActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityLogController extends Controller
{
    /**
     * Admin-only: render the activity log page.
     */
    public function index(Request $request)
    {
        $user = session('user');

        if (($user['role']['id'] ?? 0) !== RoleId::EC_ADMINISTRATOR->value) {
            abort(403, 'Access denied.');
        }

        return view('admin.activity-log', [
            'user' => $user,
        ]);
    }

    /**
     * Admin-only: return paginated activity log data as JSON.
     * GET /api/admin/activity-logs?page=1&per_page=25&search=&status=&date_from=&date_to=&user_type=
     */
    public function getData(Request $request)
    {
        $user = session('user');

        if (($user['role']['id'] ?? 0) !== RoleId::EC_ADMINISTRATOR->value) {
            return response()->json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $perPage  = max(1, min((int) $request->input('per_page', 200), 500));
        $search   = trim($request->input('search', ''));
        $status   = $request->input('status', '');
        $userType = $request->input('user_type', '');
        $dateFrom = $request->input('date_from', '');
        $dateTo   = $request->input('date_to', '');

        $query = DB::table('login_activity')
            ->orderByDesc('activity_id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('user_name', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhere('browser', 'like', "%{$search}%")
                  ->orWhere('os', 'like', "%{$search}%")
                  ->orWhere('location_city', 'like', "%{$search}%")
                  ->orWhere('location_country', 'like', "%{$search}%");
            });
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($userType !== '') {
            $query->where('user_type', $userType);
        }

        if ($dateFrom !== '') {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $total   = $query->count();
        $records = $query->paginate($perPage);

        $items = collect($records->items())->map(function ($row) {
            $loginAt  = $row->login_at  ? \Carbon\Carbon::parse($row->login_at)->setTimezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB' : '-';
            $logoutAt = $row->logout_at ? \Carbon\Carbon::parse($row->logout_at)->setTimezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB' : '-';
            $createdAt = $row->created_at ? \Carbon\Carbon::parse($row->created_at)->setTimezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB' : '-';

            $location = collect([$row->location_city, $row->location_country])->filter()->implode(', ');

            return [
                'activity_id'   => $row->activity_id,
                'user_name'     => $row->user_name ?? '-',
                'user_type'     => $row->user_type ?? 'employee',
                'role_id'       => $row->role_id,
                'ip_address'    => $row->ip_address ?? '-',
                'device_type'   => $row->device_type ?? '-',
                'device_brand'  => $row->device_brand ?? null,
                'device_model'  => $row->device_model ?? null,
                'browser'       => $row->browser ?? '-',
                'os'            => $row->os ?? '-',
                'location'      => $location ?: '-',
                'status'        => $row->status,
                'login_at'      => $loginAt,
                'logout_at'     => $logoutAt,
                'created_at'    => $createdAt,
                'user_agent'    => $row->user_agent ?? '',
            ];
        });

        $statsQuery = DB::table('login_activity');
        $stats = [
            'success' => (clone $statsQuery)->where('status', 'success')->count(),
            'failed'  => (clone $statsQuery)->where('status', 'failed')->count(),
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
}
