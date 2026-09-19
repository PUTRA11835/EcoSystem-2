<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use App\Models\SecurityEvent;
use App\Services\IpLocationService;
use App\Support\SessionPayloadDecoder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SecurityCenterController extends Controller
{
    /** Same account this app protects from force-logout everywhere else. */
    private const PROTECTED_ECI = 'ECI_ADMIN';

    private function assertAdmin(): ?array
    {
        $user = session('user');

        if (($user['role']['id'] ?? 0) !== RoleId::EC_ADMINISTRATOR->value) {
            return null;
        }

        return $user;
    }

    /** Admin-only: render the Security Center page. */
    public function index(Request $request)
    {
        $user = $this->assertAdmin();

        if (!$user) {
            abort(403, 'Access denied.');
        }

        return view('admin.security-center', ['user' => $user]);
    }

    /**
     * Admin-only: paginated security_events data as JSON.
     * GET /api/admin/security-events?page=1&per_page=25&event_type=&severity=&status=&search=&date_from=&date_to=
     */
    /** Shared filter logic for getData() and exportCsv() - same query params. */
    private function filteredQuery(Request $request)
    {
        $search    = trim($request->input('search', ''));
        $eventType = $request->input('event_type', '');
        $severity  = $request->input('severity', '');
        $status    = $request->input('status', '');
        $dateFrom  = $request->input('date_from', '');
        $dateTo    = $request->input('date_to', '');

        $query = SecurityEvent::query()->orderByDesc('id');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('actor_name', 'like', "%{$search}%")
                  ->orWhere('target_identifier', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        if ($eventType !== '') {
            $query->where('event_type', $eventType);
        }

        if ($severity !== '') {
            $query->where('severity', $severity);
        }

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($dateFrom !== '') {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo !== '') {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        // Allowlisted sort columns only - never pass the request value straight into orderBy().
        $sortColumns = [
            'created_at' => 'created_at',
            'severity'   => 'severity',
            'event_type' => 'event_type',
            'status'     => 'status',
        ];
        $sortBy  = $sortColumns[$request->input('sort_by')] ?? null;
        $sortDir = $request->input('sort_dir') === 'asc' ? 'asc' : 'desc';

        if ($sortBy) {
            $query->reorder($sortBy, $sortDir)->orderBy('id', $sortDir);
        }

        return $query;
    }

    public function getData(Request $request)
    {
        if (!$this->assertAdmin()) {
            return response()->json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $perPage = max(1, min((int) $request->input('per_page', 25), 500));
        $records = $this->filteredQuery($request)->paginate($perPage);

        // Location, not just a raw IP: resolve once per DISTINCT address on
        // this page (cached 24h per IP in IpLocationService), since the same
        // attacker or employee IP commonly repeats across several rows.
        $ipsOnPage = collect($records->items())->flatMap(fn (SecurityEvent $row) => [$row->ip_address, $row->target_ip])->all();
        $locations = IpLocationService::formatMany($ipsOnPage);

        $items = collect($records->items())->map(function (SecurityEvent $row) use ($locations) {
            $locationIp = $row->target_ip ?: $row->ip_address;

            return [
                'id'                  => $row->id,
                'event_type'          => $row->event_type,
                'severity'            => $row->severity,
                'severity_color'      => $row->getSeverityColor(),
                'module'              => $row->module,
                'status'              => $row->status,
                'title'               => $row->title,
                'description'         => $row->description ?? '-',
                'target_employee_id'  => $row->target_employee_id,
                'target_identifier'   => $row->target_identifier ?? '-',
                'target_ip'           => $row->target_ip ?? '-',
                'ip_address'          => $row->ip_address ?? '-',
                'location'            => $locationIp ? ($locations[$locationIp] ?? 'Unknown location') : '-',
                'payload'             => $row->payload,
                'resolved_by_name'    => $row->resolved_by_name,
                'resolution_note'     => $row->resolution_note,
                'resolved_at'         => $row->resolved_at
                    ? $row->resolved_at->setTimezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB'
                    : null,
                'created_at'          => $row->created_at
                    ? $row->created_at->setTimezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB'
                    : '-',
            ];
        });

        $stats = [
            'open'            => SecurityEvent::query()->where('status', 'open')->count(),
            'critical_high'   => SecurityEvent::query()->where('status', 'open')->whereIn('severity', ['critical', 'high'])->count(),
            'locked_accounts' => DB::table('auth_users')->where('locked_until', '>', now())->count(),
            'blocked_ips'     => DB::table('blocked_ips')->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })->count(),
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

    /** Admin-only: distinct event_type values for the filter dropdown. */
    public function eventTypes(Request $request)
    {
        if (!$this->assertAdmin()) {
            return response()->json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $types = SecurityEvent::query()->distinct()->orderBy('event_type')->pluck('event_type');

        return response()->json(['success' => true, 'data' => $types]);
    }

    /** Admin-only: currently blocked IPs (active or permanent), for the Unblock action. */
    public function blockedIps(Request $request)
    {
        if (!$this->assertAdmin()) {
            return response()->json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $rows = DB::table('blocked_ips')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($row) {
                return [
                    'ip_address'      => $row->ip_address,
                    'reason'          => $row->reason,
                    'source'          => $row->source,
                    'blocked_by_name' => $row->blocked_by_name,
                    'expires_at'      => $row->expires_at
                        ? \Illuminate\Support\Carbon::parse($row->expires_at)->setTimezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB'
                        : 'Permanent',
                    'created_at'      => \Illuminate\Support\Carbon::parse($row->created_at)->setTimezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB',
                ];
            });

        return response()->json(['success' => true, 'data' => $rows]);
    }

    /**
     * Admin-only: top offending IPs / most-targeted accounts, plus a daily
     * event-count trend - all within the last N days (default 7).
     * GET /api/admin/security-events/top-offenders?days=7
     */
    public function topOffenders(Request $request)
    {
        if (!$this->assertAdmin()) {
            return response()->json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $days  = max(1, min((int) $request->input('days', 7), 90));
        $since = now()->subDays($days);

        $topIps = SecurityEvent::where('created_at', '>=', $since)
            ->whereNotNull('ip_address')
            ->select('ip_address', DB::raw('COUNT(*) as event_count'), DB::raw('MAX(created_at) as last_seen'))
            ->groupBy('ip_address')
            ->orderByDesc('event_count')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'ip_address'  => $r->ip_address,
                'event_count' => (int) $r->event_count,
                'last_seen'   => \Illuminate\Support\Carbon::parse($r->last_seen)->setTimezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB',
            ]);

        $topTargets = SecurityEvent::where('created_at', '>=', $since)
            ->whereNotNull('target_identifier')
            ->select('target_identifier', DB::raw('COUNT(*) as event_count'), DB::raw('MAX(created_at) as last_seen'))
            ->groupBy('target_identifier')
            ->orderByDesc('event_count')
            ->limit(5)
            ->get()
            ->map(fn ($r) => [
                'target_identifier' => $r->target_identifier,
                'event_count'       => (int) $r->event_count,
                'last_seen'         => \Illuminate\Support\Carbon::parse($r->last_seen)->setTimezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB',
            ]);

        // Daily counts. created_at is stored in the app's configured timezone
        // (config('app.timezone') = 'Asia/Jakarta', not UTC - same as every
        // other admin page's ->setTimezone('Asia/Jakarta') calls, which are
        // effectively no-ops for that reason), so DATE() needs no conversion.
        $trend = SecurityEvent::where('created_at', '>=', $since)
            ->select(DB::raw('DATE(created_at) as day'), DB::raw('COUNT(*) as count'))
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($r) => ['day' => $r->day, 'count' => (int) $r->count]);

        return response()->json([
            'success' => true,
            'data'    => [
                'top_ips'     => $topIps,
                'top_targets' => $topTargets,
                'trend'       => $trend,
            ],
        ]);
    }

    /**
     * Admin-only: export the currently filtered events as CSV (for incident
     * reports/compliance). Same filters as getData(), unpaginated.
     * GET /api/admin/security-events/export?...same filters as getData
     */
    public function exportCsv(Request $request)
    {
        if (!$this->assertAdmin()) {
            abort(403, 'Access denied.');
        }

        $query = $this->filteredQuery($request);

        $filename = 'security_events_' . now()->format('Y-m-d_His') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($handle, [
                'ID', 'Type', 'Severity', 'Status', 'Title', 'Description',
                'Target', 'IP Address', 'Actor', 'Created At (WIB)', 'Resolved By', 'Resolution Note',
            ]);

            $query->reorder('id', 'asc')->chunk(500, function ($rows) use ($handle) {
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row->id,
                        $row->event_type,
                        $row->severity,
                        $row->status,
                        $row->title,
                        $row->description,
                        $row->target_identifier ?? $row->target_ip ?? '',
                        $row->ip_address,
                        $row->actor_name,
                        $row->created_at ? $row->created_at->setTimezone('Asia/Jakarta')->format('d M Y H:i') : '',
                        $row->resolved_by_name,
                        $row->resolution_note,
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    /** Admin-only: mark an event resolved. POST /api/admin/security-events/{id}/resolve */
    public function resolve(Request $request, int $id)
    {
        $user = $this->assertAdmin();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $event = SecurityEvent::find($id);

        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Event not found.'], 404);
        }

        $event->update([
            'status'           => 'resolved',
            'resolved_by_id'   => $user['id'] ?? null,
            'resolved_by_name' => $user['name'] ?? 'Admin',
            'resolved_at'      => now(),
            'resolution_note'  => $request->input('note') ? trim((string) $request->input('note')) : null,
        ]);

        return response()->json(['success' => true, 'message' => 'Event resolved.']);
    }

    /** Admin-only: clear an account lockout and auto-resolve its triggering event. */
    public function unlockAccount(Request $request)
    {
        $user = $this->assertAdmin();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $authUserId = (int) $request->input('auth_user_id');

        if (!$authUserId) {
            return response()->json(['success' => false, 'message' => 'auth_user_id is required.'], 422);
        }

        DB::table('auth_users')->where('id', $authUserId)->update(['locked_until' => null]);

        SecurityEvent::query()
            ->where('event_type', 'brute_force_account_lockout')
            ->where('status', 'open')
            ->whereJsonContains('payload->auth_user_id', $authUserId)
            ->latest('id')
            ->limit(1)
            ->update([
                'status'           => 'resolved',
                'resolved_by_id'   => $user['id'] ?? null,
                'resolved_by_name' => $user['name'] ?? 'Admin',
                'resolved_at'      => now(),
                'resolution_note'  => 'Account manually unlocked.',
            ]);

        return response()->json(['success' => true, 'message' => 'Account unlocked.']);
    }

    /** Admin-only: manually block an IP address. */
    public function blockIp(Request $request)
    {
        $user = $this->assertAdmin();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $ip = trim((string) $request->input('ip_address'));

        if ($ip === '') {
            return response()->json(['success' => false, 'message' => 'ip_address is required.'], 422);
        }

        DB::table('blocked_ips')->updateOrInsert(
            ['ip_address' => $ip],
            [
                'reason'          => trim((string) $request->input('reason', '')) ?: 'Manually blocked by admin',
                'source'          => 'manual',
                'blocked_by_id'   => $user['id'] ?? null,
                'blocked_by_name' => $user['name'] ?? 'Admin',
                'expires_at'      => null,
                'created_at'      => now(),
            ]
        );

        return response()->json(['success' => true, 'message' => "IP {$ip} blocked."]);
    }

    /** Admin-only: remove an IP from the blocklist. */
    public function unblockIp(Request $request)
    {
        if (!$this->assertAdmin()) {
            return response()->json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $ip = trim((string) $request->input('ip_address'));

        DB::table('blocked_ips')->where('ip_address', $ip)->delete();

        return response()->json(['success' => true, 'message' => "IP {$ip} unblocked."]);
    }

    /**
     * Admin-only: force-logout every active session belonging to an employee.
     * Reuses the same session.payload decode primitive as AdminSessionController.
     */
    public function forceLogoutAccount(Request $request)
    {
        $admin = $this->assertAdmin();

        if (!$admin) {
            return response()->json(['success' => false, 'message' => 'Access denied.'], 403);
        }

        $employeeId = (int) $request->input('employee_id');

        if (!$employeeId) {
            return response()->json(['success' => false, 'message' => 'employee_id is required.'], 422);
        }

        $candidates = DB::table('sessions')->select('id', 'payload')->get();

        $deletableIds = $candidates
            ->filter(function ($s) use ($employeeId) {
                $user = SessionPayloadDecoder::decode($s->payload);

                if (!$user || ($user['eci'] ?? null) === self::PROTECTED_ECI) {
                    return false;
                }

                return (int) ($user['id'] ?? 0) === $employeeId;
            })
            ->pluck('id');

        $count = DB::table('sessions')->whereIn('id', $deletableIds)->delete();

        return response()->json([
            'success' => true,
            'message' => "Terminated {$count} session(s).",
            'count'   => $count,
        ]);
    }
}
