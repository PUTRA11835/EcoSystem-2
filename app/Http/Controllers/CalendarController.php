<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use App\Models\Event;
use Illuminate\Http\Request;
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
        $user = session('user');
        // Role is stored as nested array: $user['role']['id']
        $roleId = isset($user['role']['id']) ? (int) $user['role']['id'] : null;

        $isHead  = in_array($roleId, RoleId::HEAD_GROUP, true);
        $isAdmin = $roleId === RoleId::ADMIN->value;

        return view('calendar.timesheets', [
            'user' => $user,
            'isHead' => $isHead,
            'isAdmin' => $isAdmin,
            'roleId' => $roleId,
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
                'message' => 'Failed to retrieve events: ' . $e->getMessage()
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
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create event: ' . $e->getMessage()
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
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update event: ' . $e->getMessage()
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
                'message' => 'Failed to delete event: ' . $e->getMessage()
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
                'message' => 'Failed to retrieve statistics: ' . $e->getMessage()
            ], 500);
        }
    }
}