<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use App\Models\Employee;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CalendarController extends Controller
{
    public const EVENT_SLUG = 'calendar.events';

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
        return view('calendar.events', [
            // Company-wide events are HR's. These three decide which buttons the calendar shows; the API enforces them again.
            'canPublish'      => $this->canManagePublic('create'),
            'canEditPublic'   => $this->canManagePublic('edit'),
            'canDeletePublic' => $this->canManagePublic('delete'),
        ]);
    }

    /**
     * Display timesheets view
     */
    public function timesheets()
    {
        $user       = session('user');
        $roleId     = isset($user['role']['id']) ? (int) $user['role']['id'] : null;
        $employeeId = $user['id'] ?? null;

        // ── Collect ALL role IDs (primary + pivot assignments) ────────────────
        // "Primary" role in session is just whichever pivot row the DB returns
        // first (no ORDER BY at login) — an employee holding both Delivery
        // Support Head and a lower-id role can end up with the wrong role as
        // "primary". So every check below must look at the full role set, not
        // just $roleId.
        $allRoleIds = collect([$roleId])->filter();
        if ($employeeId) {
            $pivotRoles = DB::table('employee_role_assignment')
                ->where('employee_id', $employeeId)
                ->pluck('role_id');
            $allRoleIds = $allRoleIds->merge($pivotRoles)->unique()->map(fn($id) => (int) $id);
        }

        $isHead  = $allRoleIds->intersect(RoleId::HEAD_GROUP)->isNotEmpty()
                || $allRoleIds->contains(RoleId::DELIVERY_RPMO_HEAD->value)
                || $allRoleIds->contains(RoleId::EC_ADMINISTRATOR->value);
        $isAdmin = $allRoleIds->contains(RoleId::EC_ADMINISTRATOR->value);
        $isHoS   = $allRoleIds->contains(RoleId::DELIVERY_SUPPORT_HEAD->value);

        // ── Map each role to the timesheet type(s) it allows ─────────────────
        // Heads and RPMO are locked to their approval domain regardless of extra roles.
        $headLock = match(true) {
            $allRoleIds->contains(RoleId::DELIVERY_SUPPORT_HEAD->value) => 'support',
            $allRoleIds->contains(RoleId::DELIVERY_PROJECT_HEAD->value) => 'project',
            $allRoleIds->contains(RoleId::DELIVERY_RPMO_HEAD->value)    => 'office',
            default                                                    => null,
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
            'isHoS'        => $isHoS,
            'roleId'       => $roleId,
            'lockedType'   => $lockedType,
            'allowedTypes' => $allowedTypes,
        ]);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Events API
    //
    // An event is PERSONAL (only its creator sees it, only its creator changes it) or COMPANY-WIDE (`is_public`: every
    // employee sees it). Company-wide events are HR's: making, editing or deleting one needs the Create / Edit / Delete box
    // of `calendar.events` in Management → Roles — the same boxes as every other module, no hard-coded role.
    // ═════════════════════════════════════════════════════════════════════════

    /** The signed-in employee's id (the session stores it as `id`), or null for a non-employee session. */
    private function me(): ?int
    {
        $user = session('user');

        return ($user['type'] ?? null) === 'employee' && !empty($user['id']) ? (int) $user['id'] : null;
    }

    /** Whether the signed-in employee holds one box (create | edit | delete) of Events — i.e. may manage COMPANY-WIDE events. */
    private function canManagePublic(string $action): bool
    {
        $me = $this->me();

        return $me !== null && (bool) Employee::find($me)?->hasMenuPermission(self::EVENT_SLUG, "can_{$action}");
    }

    /** Edit / delete right on one event: company-wide → the HR boxes; personal → its creator only. */
    private function canChange(Event $event, string $action): bool
    {
        return $event->is_public
            ? $this->canManagePublic($action)
            : ($this->me() !== null && (int) $event->created_by === $this->me());
    }

    /** One event as the calendar needs it: plain Y-m-d / H:i strings (the model's own JSON turns dates into UTC timestamps) plus what the viewer may do. */
    private function present(Event $event): array
    {
        return [
            'id'              => $event->id,
            'title'           => $event->title,
            'description'     => $event->description,
            'start_date'      => $event->start_date?->format('Y-m-d'),
            'start_time'      => substr((string) $event->start_time, 0, 5),
            'end_date'        => ($event->end_date ?? $event->start_date)?->format('Y-m-d'),
            'end_time'        => $event->end_time ? substr((string) $event->end_time, 0, 5) : null,
            'location'        => $event->location,
            'type'            => $event->type,
            'all_day'         => (bool) $event->all_day,
            'color'           => $event->color,
            'is_public'       => (bool) $event->is_public,
            'is_mine'         => $this->me() !== null && (int) $event->created_by === $this->me(),
            'created_by_name' => $event->creator?->fullNameLabel(),
            'can_edit'        => $this->canChange($event, 'edit'),
            'can_delete'      => $this->canChange($event, 'delete'),
        ];
    }

    private function deny(string $message = 'Only HR can create, change or delete company-wide events.')
    {
        return response()->json(['success' => false, 'message' => $message], 403);
    }

    private function eventRules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'required|date',
            'start_time' => 'required',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'end_time' => 'nullable',
            'location' => 'nullable|string|max:255',
            'type' => 'required|in:meeting,task,deadline,urgent,reminder',
            'all_day' => 'boolean',
            'is_public' => 'boolean',
            'color' => 'nullable|string|max:7',
            'employee_id' => 'nullable|exists:employee,employee_id',
            'customer_id' => 'nullable|exists:customer,id',
        ];
    }

    /**
     * Get the events the signed-in employee can see (API): company-wide + their own.
     */
    public function getEvents(Request $request)
    {
        try {
            $query = Event::visibleTo($this->me())->with('creator.basicData');

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
                           ->get()
                           ->map(fn (Event $e) => $this->present($e))
                           ->values();

            return response()->json([
                'success' => true,
                'data' => $events,
                'can_publish' => $this->canManagePublic('create'),
                'message' => 'Events retrieved successfully'
            ]);
        } catch (\Exception $e) {
            report($e);

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
        $event = Event::visibleTo($this->me())->with('creator.basicData')->find($id);

        if (!$event) {
            return response()->json(['success' => false, 'message' => 'Event not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->present($event),
            'message' => 'Event retrieved successfully'
        ]);
    }

    /**
     * Store new event (API)
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate($this->eventRules());

            $me = $this->me();
            if ($me === null) {
                return $this->deny('Only employees can add events.');
            }

            $validated['is_public'] = (bool) ($validated['is_public'] ?? false);
            if ($validated['is_public'] && !$this->canManagePublic('create')) {
                return $this->deny();
            }
            $validated['created_by'] = $me;

            $event = Event::create($validated);

            return response()->json([
                'success' => true,
                'data' => $this->present($event->load('creator.basicData')),
                'message' => 'Event created successfully'
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Calendar event data is invalid.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            report($e);

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
            $event = Event::visibleTo($this->me())->find($id);
            if (!$event) {
                return response()->json(['success' => false, 'message' => 'Event not found'], 404);
            }
            if (!$this->canChange($event, 'edit')) {
                return $this->deny($event->is_public ? 'Only HR can change company-wide events.' : 'You can only change your own events.');
            }

            $validated = $request->validate($this->eventRules());

            // Switching a personal event to company-wide is publishing it — that needs the Create box.
            $validated['is_public'] = (bool) ($validated['is_public'] ?? $event->is_public);
            if ($validated['is_public'] && !$event->is_public && !$this->canManagePublic('create')) {
                return $this->deny();
            }

            $event->update($validated);

            return response()->json([
                'success' => true,
                'data' => $this->present($event->load('creator.basicData')),
                'message' => 'Event updated successfully'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Calendar event update data is invalid.',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            report($e);

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
            $event = Event::visibleTo($this->me())->find($id);
            if (!$event) {
                return response()->json(['success' => false, 'message' => 'Event not found'], 404);
            }
            if (!$this->canChange($event, 'delete')) {
                return $this->deny($event->is_public ? 'Only HR can delete company-wide events.' : 'You can only delete your own events.');
            }

            $event->delete();

            return response()->json([
                'success' => true,
                'message' => 'Event deleted successfully'
            ]);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete event'
            ], 500);
        }
    }

    /**
     * Get events statistics (API) — over the events the signed-in employee can see.
     */
    public function statistics(Request $request)
    {
        try {
            $me = $this->me();
            $startDate = $request->input('start_date', Carbon::now()->startOfMonth());
            $endDate = $request->input('end_date', Carbon::now()->endOfMonth());

            $stats = [
                'total_events' => Event::visibleTo($me)->dateRange($startDate, $endDate)->count(),
                'by_type' => Event::visibleTo($me)->dateRange($startDate, $endDate)
                    ->selectRaw('type, COUNT(*) as count')
                    ->groupBy('type')
                    ->pluck('count', 'type'),
                'upcoming_events' => Event::visibleTo($me)->with('creator.basicData')->where('start_date', '>=', Carbon::now())
                    ->orderBy('start_date', 'asc')
                    ->limit(5)
                    ->get()
                    ->map(fn (Event $e) => $this->present($e))
                    ->values(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics'
            ], 500);
        }
    }
}
