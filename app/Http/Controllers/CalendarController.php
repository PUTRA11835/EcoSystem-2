<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CalendarController extends Controller
{
    /**
     * Display calendar index view
     */
    public function index()
    {
        return view('calendar.index');
    }

    /**
     * Display events view
     */
    public function events()
    {
        return view('calendar.events');
    }

    /**
     * Display timesheets view
     */
    public function timesheets()
    {
        $user       = session('user');
        $roleId     = isset($user['role']['id']) ? (int) $user['role']['id'] : null;
        $employeeId = $user['id'] ?? null;

        $isHead  = in_array($roleId, RoleId::HEAD_GROUP, true)
                || $roleId === RoleId::DELIVERY_RPMO_HEAD->value
                || $roleId === RoleId::EC_ADMINISTRATOR->value;
        $isAdmin = $roleId === RoleId::EC_ADMINISTRATOR->value;

        // ── Collect ALL role IDs (primary + pivot assignments) ────────────────
        $allRoleIds = collect([$roleId])->filter();
        if ($employeeId) {
            $pivotRoles = DB::table('employee_role_assignment')
                ->where('employee_id', $employeeId)
                ->pluck('role_id');
            $allRoleIds = $allRoleIds->merge($pivotRoles)->unique()->map(fn($id) => (int) $id);
        }

        // ── Map each role to the timesheet type(s) it allows ─────────────────
        // Heads and RPMO are locked to their approval domain regardless of extra roles.
        $headLock = match($roleId) {
            RoleId::DELIVERY_SUPPORT_HEAD->value => 'support',
            RoleId::DELIVERY_PROJECT_HEAD->value => 'project',
            RoleId::DELIVERY_RPMO_HEAD->value            => 'office',
            default                        => null,
        };

        if ($headLock !== null) {
            // Approval roles: single-type lock, no multi-role expansion
            $lockedType   = $headLock;
            $allowedTypes = [$headLock];
        } else {
            // Regular users: compute allowed types from ALL assigned roles
            $typeMap = [
                RoleId::DELIVERY_SUPPORT_USER->value         => 'support',
                RoleId::EC_USER->value       => 'office',
                RoleId::DELIVERY_PROJECT_USER->value => 'project',
                // Admin and Helpdesk get all types
                RoleId::EC_ADMINISTRATOR->value            => '*',
                RoleId::DELIVERY_HELPDESK->value         => '*',
            ];

            $computed    = [];
            $grantedAll  = false;
            foreach ($allRoleIds as $rid) {
                $mapped = $typeMap[$rid] ?? null;
                if ($mapped === '*') { $grantedAll = true; break; }
                if ($mapped)          $computed[] = $mapped;
            }

            $allowedTypes = $grantedAll
                ? ['project', 'support', 'office']
                : (count(array_unique($computed)) > 0 ? array_values(array_unique($computed)) : ['project', 'support', 'office']);

            // Lock only when exactly one type is allowed
            $lockedType = count($allowedTypes) === 1 ? $allowedTypes[0] : null;
        }

        return view('calendar.timesheets', [
            'user'         => $user,
            'isHead'       => $isHead,
            'isAdmin'      => $isAdmin,
            'roleId'       => $roleId,
            'lockedType'   => $lockedType,
            'allowedTypes' => $allowedTypes,
        ]);
    }

    /**
     * Get all events (API)
     */
    public function getEvents(Request $request)
    {
        try {
            $query = Event::with(['creator', 'employee', 'customer']);

            // Filter by date range if provided
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->dateRange($request->start_date, $request->end_date);
            }

            // Filter by type if provided
            if ($request->has('type')) {
                $query->ofType($request->type);
            }

            // Filter by employee if provided
            if ($request->has('employee_id')) {
                $query->forEmployee($request->employee_id);
            }

            // Filter by customer if provided
            if ($request->has('customer_id')) {
                $query->forCustomer($request->customer_id);
            }

            $events = $query->orderBy('start_date', 'asc')
                           ->orderBy('start_time', 'asc')
                           ->get();

            return response()->json([
                'success' => true,
                'data' => $events,
                'message' => 'Events retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve events'
            ], 500);
        }
    }

    /**
     * Get single event (API)
     */
    public function show($id)
    {
        try {
            $event = Event::with(['creator', 'employee', 'customer'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $event,
                'message' => 'Event retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Event not found'
            ], 404);
        }
    }

    /**
     * Store new event (API)
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'required|date',
                'start_time' => 'required',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'end_time' => 'nullable',
                'location' => 'nullable|string|max:255',
                'type' => 'required|in:meeting,task,deadline,urgent,reminder',
                'all_day' => 'boolean',
                'color' => 'nullable|string|max:7',
                'employee_id' => 'nullable|exists:employee,employee_id',
                'customer_id' => 'nullable|exists:customer,id',
            ]);

            // Set created_by from session
            $user = session('user');
            if ($user && isset($user['employee_id'])) {
                $validated['created_by'] = $user['employee_id'];
            }

            $event = Event::create($validated);

            return response()->json([
                'success' => true,
                'data' => $event->load(['creator', 'employee', 'customer']),
                'message' => 'Event created successfully'
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Calendar event data is invalid.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create event'
            ], 500);
        }
    }

    /**
     * Update event (API)
     */
    public function update(Request $request, $id)
    {
        try {
            $event = Event::findOrFail($id);

            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'required|date',
                'start_time' => 'required',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'end_time' => 'nullable',
                'location' => 'nullable|string|max:255',
                'type' => 'required|in:meeting,task,deadline,urgent,reminder',
                'all_day' => 'boolean',
                'color' => 'nullable|string|max:7',
                'employee_id' => 'nullable|exists:employee,employee_id',
                'customer_id' => 'nullable|exists:customer,id',
            ]);

            $event->update($validated);

            return response()->json([
                'success' => true,
                'data' => $event->load(['creator', 'employee', 'customer']),
                'message' => 'Event updated successfully'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Calendar event update data is invalid.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update event'
            ], 500);
        }
    }

    /**
     * Delete event (API)
     */
    public function destroy($id)
    {
        try {
            $event = Event::findOrFail($id);
            $event->delete();

            return response()->json([
                'success' => true,
                'message' => 'Event deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete event'
            ], 500);
        }
    }

    /**
     * Get events statistics (API)
     */
    public function statistics(Request $request)
    {
        try {
            $startDate = $request->input('start_date', Carbon::now()->startOfMonth());
            $endDate = $request->input('end_date', Carbon::now()->endOfMonth());

            $stats = [
                'total_events' => Event::dateRange($startDate, $endDate)->count(),
                'by_type' => Event::dateRange($startDate, $endDate)
                    ->selectRaw('type, COUNT(*) as count')
                    ->groupBy('type')
                    ->pluck('count', 'type'),
                'upcoming_events' => Event::where('start_date', '>=', Carbon::now())
                    ->orderBy('start_date', 'asc')
                    ->limit(5)
                    ->get(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics'
            ], 500);
        }
    }
}