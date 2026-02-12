<?php

namespace App\Http\Controllers;

use App\Models\Timesheet;
use App\Models\DeliveryProject;
use App\Models\DeliveryProjectActivity;
use App\Models\Employee;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TimesheetController extends Controller
{
    /**
     * Get submitted timesheets for approval (for Head of Project/Head of Support)
     */
    public function submittedForApproval(Request $request)
    {
        try {
            $user = session('user');
            // Role is stored as nested array: $user['role']['id']
            $roleId = isset($user['role']['id']) ? (int) $user['role']['id'] : null;

            // Only Admin (1), Head of Project (4), and Head of Support (5) can access this
            if (!in_array($roleId, [1, 4, 5], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 403);
            }

            $query = Timesheet::with(['employee.basicData', 'activity.delivery_project', 'delivery_project', 'approver.basicData'])
                ->whereIn('status', ['submitted', 'approved', 'rejected']);

            // Filter by date range if provided
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->dateRange($request->start_date, $request->end_date);
            }

            // Filter by status if provided
            if ($request->has('status')) {
                $query->byStatus($request->status);
            }

            $timesheets = $query->orderBy('date', 'desc')
                                ->orderBy('created_at', 'desc')
                                ->get()
                                ->map(function ($timesheet) {
                                    return [
                                        'id' => $timesheet->id,
                                        'employee_id' => $timesheet->employee_id,
                                        'employee_name' => $timesheet->employee?->basicData?->first_name . ' ' . $timesheet->employee?->basicData?->last_name,
                                        'date' => $timesheet->date?->format('Y-m-d'),
                                        'start_time' => $timesheet->start_time,
                                        'end_time' => $timesheet->end_time,
                                        'duration_minutes' => $timesheet->duration_minutes,
                                        'duration_hours' => round($timesheet->duration_minutes / 60, 2),
                                        'description' => $timesheet->description,
                                        'activity_type' => $timesheet->activity_type,
                                        'activity_name' => $timesheet->activity?->name,
                                        'project_name' => $timesheet->activity?->delivery_project?->name ?? $timesheet->delivery_project?->name,
                                        'status' => $timesheet->status,
                                        'is_billable' => $timesheet->is_billable,
                                        'rejection_reason' => $timesheet->rejection_reason,
                                        'approved_by' => $timesheet->approved_by,
                                        'approver_name' => $timesheet->approver?->basicData?->first_name . ' ' . $timesheet->approver?->basicData?->last_name,
                                        'approved_at' => $timesheet->approved_at?->format('Y-m-d H:i:s'),
                                        'created_at' => $timesheet->created_at?->format('Y-m-d H:i:s'),
                                    ];
                                });

            return response()->json([
                'success' => true,
                'data' => $timesheets,
                'message' => 'Submitted timesheets retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving submitted timesheets: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve submitted timesheets: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all timesheets (API)
     */
    public function index(Request $request)
    {
        try {
            // Load employee and activity relationships
            $query = Timesheet::with(['employee', 'activity']);

            // Filter by employee if provided
            if ($request->has('employee_id')) {
                $query->forEmployee($request->employee_id);
            }

            // Filter by date range if provided
            if ($request->has('start_date') && $request->has('end_date')) {
                $query->dateRange($request->start_date, $request->end_date);
            }

            // Filter by status if provided
            if ($request->has('status')) {
                $query->byStatus($request->status);
            }

            // Filter by project if provided (column still exists in DB)
            if ($request->has('delivery_projects_id')) {
                $query->forProject($request->delivery_projects_id);
            }

            $timesheets = $query->orderBy('date', 'desc')
                                ->orderBy('start_time', 'desc')
                                ->get();

            return response()->json([
                'success' => true,
                'data' => $timesheets,
                'message' => 'Timesheets retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving timesheets: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve timesheets: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single timesheet (API)
     */
    public function show($id)
    {
        try {
            // Load employee and activity relationships
            $timesheet = Timesheet::with(['employee', 'activity'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $timesheet,
                'message' => 'Timesheet retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving timesheet: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Timesheet not found'
            ], 404);
        }
    }

    /**
     * Store new timesheet (API)
     */
    public function store(Request $request)
    {
        try {
            Log::info('Creating timesheet with data:', $request->all());
            
            // Base validation
            $rules = [
                'employee_id' => 'required|exists:employee,employee_id',
                'date' => 'required|date',
                'start_time' => 'required',
                'end_time' => 'required|after:start_time',
                'description' => 'required|string',
                'activity_type' => 'required|in:development,meeting,documentation,testing,support,training,other',
                'is_billable' => 'sometimes|boolean',
                'presence' => 'nullable|string',
                'location' => 'nullable|string',
            ];

            // Conditional validation based on what's provided
            if ($request->filled('delivery_projects_id')) {
                $rules['delivery_projects_id'] = 'nullable|integer';
                $rules['activity_id'] = 'nullable|exists:delivery_project_activities,id'; // Activity validation
                $rules['ticket_id'] = 'nullable';
            } elseif ($request->filled('ticket_id')) {
                $rules['ticket_id'] = 'nullable|integer';
                $rules['delivery_projects_id'] = 'nullable';
            }

            $validated = $request->validate($rules);

            $validated['status'] = 'draft';

            // Ensure is_billable has default value
            if (!isset($validated['is_billable'])) {
                $validated['is_billable'] = true;
            }

            // Add presence and location from request
            $validated['presence'] = $request->input('presence');
            $validated['location'] = $request->input('location');

            // Ensure only one of project_id or ticket_id is set
            if (!empty($validated['delivery_projects_id'])) {
                $validated['ticket_id'] = null;
                // Keep activity_id if provided
                $validated['activity_id'] = $request->input('activity_id') ?: null;
            } elseif (!empty($validated['ticket_id'])) {
                $validated['delivery_projects_id'] = null;
                $validated['activity_id'] = null; // No activity for support tickets
            } else {
                $validated['delivery_projects_id'] = null;
                $validated['ticket_id'] = null;
                $validated['activity_id'] = null;
            }

            $timesheet = Timesheet::create($validated);

            Log::info('Timesheet created successfully:', ['id' => $timesheet->id]);

            return response()->json([
                'success' => true,
                'data' => $timesheet->load(['employee']),
                'message' => 'Timesheet created successfully'
            ], 201);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error:', $e->errors());
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('Error creating timesheet: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create timesheet: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update timesheet (API)
     */
    public function update(Request $request, $id)
    {
        try {
            $timesheet = Timesheet::findOrFail($id);

            if ($timesheet->status === 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update approved timesheet'
                ], 403);
            }

            $rules = [
                'date' => 'required|date',
                'start_time' => 'required',
                'end_time' => 'required|after:start_time',
                'description' => 'required|string',
                'activity_type' => 'required|in:development,meeting,documentation,testing,support,training,other',
                'is_billable' => 'sometimes|boolean',
                'presence' => 'nullable|string',
                'location' => 'nullable|string',
            ];

            if ($request->filled('delivery_projects_id')) {
                $rules['delivery_projects_id'] = 'nullable|integer';
                $rules['activity_id'] = 'nullable|exists:delivery_project_activities,id';
                $rules['ticket_id'] = 'nullable';
            } elseif ($request->filled('ticket_id')) {
                $rules['ticket_id'] = 'nullable|integer';
                $rules['delivery_projects_id'] = 'nullable';
            }

            $validated = $request->validate($rules);

            // Add presence and location from request
            $validated['presence'] = $request->input('presence');
            $validated['location'] = $request->input('location');

            if (!empty($validated['delivery_projects_id'])) {
                $validated['ticket_id'] = null;
                $validated['activity_id'] = $request->input('activity_id') ?: null;
            } elseif (!empty($validated['ticket_id'])) {
                $validated['delivery_projects_id'] = null;
                $validated['activity_id'] = null;
            } else {
                $validated['delivery_projects_id'] = null;
                $validated['ticket_id'] = null;
                $validated['activity_id'] = null;
            }

            $timesheet->update($validated);

            return response()->json([
                'success' => true,
                'data' => $timesheet->load(['employee']),
                'message' => 'Timesheet updated successfully'
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation error:', $e->errors());
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('Error updating timesheet: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update timesheet: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete timesheet (API)
     */
    public function destroy($id)
    {
        try {
            $timesheet = Timesheet::findOrFail($id);

            if ($timesheet->status === 'approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete approved timesheet'
                ], 403);
            }

            $timesheet->delete();

            return response()->json([
                'success' => true,
                'message' => 'Timesheet deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting timesheet: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete timesheet: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Submit timesheet for approval (API)
     */
    public function submit($id)
    {
        try {
            $timesheet = Timesheet::findOrFail($id);

            if ($timesheet->status !== 'draft') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only draft timesheets can be submitted'
                ], 400);
            }

            $timesheet->update(['status' => 'submitted']);

            return response()->json([
                'success' => true,
                'data' => $timesheet,
                'message' => 'Timesheet submitted for approval'
            ]);
        } catch (\Exception $e) {
            Log::error('Error submitting timesheet: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit timesheet: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve timesheet (API)
     */
    public function approve(Request $request, $id)
    {
        try {
            $user = session('user');
            // Role is stored as nested array: $user['role']['id']
            $roleId = isset($user['role']['id']) ? (int) $user['role']['id'] : null;

            // Only Admin (1), Head of Project (4), and Head of Support (5) can approve
            if (!in_array($roleId, [1, 4, 5], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Only managers can approve timesheets'
                ], 403);
            }

            $timesheet = Timesheet::findOrFail($id);

            if ($timesheet->status !== 'submitted') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only submitted timesheets can be approved'
                ], 400);
            }

            $timesheet->update([
                'status' => 'approved',
                'approved_by' => $user['id'] ?? null,
                'approved_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $timesheet->load(['approver']),
                'message' => 'Timesheet approved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error approving timesheet: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve timesheet: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject timesheet (API)
     */
    public function reject(Request $request, $id)
    {
        try {
            $user = session('user');
            // Role is stored as nested array: $user['role']['id']
            $roleId = isset($user['role']['id']) ? (int) $user['role']['id'] : null;

            // Only Admin (1), Head of Project (4), and Head of Support (5) can reject
            if (!in_array($roleId, [1, 4, 5], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: Only managers can reject timesheets'
                ], 403);
            }

            $timesheet = Timesheet::findOrFail($id);

            if ($timesheet->status !== 'submitted') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only submitted timesheets can be rejected'
                ], 400);
            }

            $validated = $request->validate([
                'rejection_reason' => 'required|string',
            ]);

            $timesheet->update([
                'status' => 'rejected',
                'rejection_reason' => $validated['rejection_reason'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $timesheet,
                'message' => 'Timesheet rejected'
            ]);
        } catch (\Exception $e) {
            Log::error('Error rejecting timesheet: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject timesheet: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get timesheet statistics (API)
     */
    public function statistics(Request $request)
    {
        try {
            $employeeId = $request->input('employee_id');
            $startDate = $request->input('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
            $endDate = $request->input('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));

            $query = Timesheet::dateRange($startDate, $endDate);
            
            if ($employeeId) {
                $query->forEmployee($employeeId);
            }

            $totalMinutes = $query->sum('duration_minutes') ?? 0;
            $totalHours = round($totalMinutes / 60, 2);

            $billableMinutes = $query->billable()->sum('duration_minutes') ?? 0;
            $billableHours = round($billableMinutes / 60, 2);

            $stats = [
                'total_hours' => $totalHours,
                'total_entries' => $query->count(),
                'billable_hours' => $billableHours,
                'by_status' => Timesheet::dateRange($startDate, $endDate)
                    ->when($employeeId, fn($q) => $q->forEmployee($employeeId))
                    ->selectRaw('status, COUNT(*) as count, SUM(duration_minutes) as total_minutes')
                    ->groupBy('status')
                    ->get()
                    ->mapWithKeys(fn($item) => [
                        $item->status => [
                            'count' => $item->count,
                            'hours' => round(($item->total_minutes ?? 0) / 60, 2)
                        ]
                    ])
                    ->toArray(),
                'by_activity_type' => Timesheet::dateRange($startDate, $endDate)
                    ->when($employeeId, fn($q) => $q->forEmployee($employeeId))
                    ->selectRaw('activity_type, SUM(duration_minutes) as total_minutes')
                    ->groupBy('activity_type')
                    ->get()
                    ->mapWithKeys(fn($item) => [
                        $item->activity_type => round(($item->total_minutes ?? 0) / 60, 2)
                    ])
                    ->toArray(),
                'by_type' => [
                    'project' => Timesheet::dateRange($startDate, $endDate)
                        ->when($employeeId, fn($q) => $q->forEmployee($employeeId))
                        ->whereNotNull('delivery_projects_id')
                        ->sum('duration_minutes') / 60,
                    'support' => Timesheet::dateRange($startDate, $endDate)
                        ->when($employeeId, fn($q) => $q->forEmployee($employeeId))
                        ->whereNotNull('ticket_id')
                        ->sum('duration_minutes') / 60,
                    'office' => Timesheet::dateRange($startDate, $endDate)
                        ->when($employeeId, fn($q) => $q->forEmployee($employeeId))
                        ->whereNull('delivery_projects_id')
                        ->whereNull('ticket_id')
                        ->sum('duration_minutes') / 60,
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Statistics retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving statistics: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get projects where the logged-in employee is a team member
     */
    public function myProjects(Request $request)
    {
        try {
            $user = session('user');
            // Session stores employee_id as 'id', fallback to 'employee_id' for compatibility
            $employeeId = $user['id'] ?? $user['employee_id'] ?? null;

            if (!$employeeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not authenticated'
                ], 401);
            }

            $projects = DeliveryProject::whereHas('teamMembers', function ($query) use ($employeeId) {
                $query->where('employee.employee_id', $employeeId);
            })
            ->with(['client.basicData'])
            ->select('id', 'name', 'client_id', 'status', 'phase')
            ->orderBy('name')
            ->get();

            return response()->json([
                'success' => true,
                'data' => $projects,
                'message' => 'Projects retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving my projects: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve projects: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get activities assigned to the logged-in employee for a specific project
     */
    public function myActivities(Request $request, $projectId)
    {
        try {
            $user = session('user');
            // Session stores employee_id as 'id', fallback to 'employee_id' for compatibility
            $employeeId = $user['id'] ?? $user['employee_id'] ?? null;

            if (!$employeeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not authenticated'
                ], 401);
            }

            // Query activities that have this employee in the pivot table
            $activities = DeliveryProjectActivity::where('delivery_projects_id', $projectId)
                ->whereExists(function ($query) use ($employeeId) {
                    $query->select(DB::raw(1))
                        ->from('activity_employee')
                        ->whereColumn('activity_employee.delivery_project_activity_id', 'delivery_project_activities.id')
                        ->where('activity_employee.employee_id', $employeeId);
                })
                ->with(['phase', 'stage'])
                ->select('id', 'name', 'delivery_project_phase_id', 'stage_id', 'status', 'start_date', 'end_date')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $activities,
                'message' => 'Activities retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving my activities: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve activities: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get ALL activities assigned to the logged-in employee across all projects
     * This is used for the timesheet dropdown to show activities directly
     */
    public function allMyActivities(Request $request)
    {
        try {
            $user = session('user');
            // Session stores employee_id as 'id', fallback to 'employee_id' for compatibility
            $employeeId = $user['id'] ?? $user['employee_id'] ?? null;

            Log::info('allMyActivities called', [
                'user' => $user,
                'employeeId' => $employeeId
            ]);

            if (!$employeeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not authenticated'
                ], 401);
            }

            // Query activities that have this employee in the pivot table
            $activities = DeliveryProjectActivity::whereExists(function ($query) use ($employeeId) {
                    $query->select(DB::raw(1))
                        ->from('activity_employee')
                        ->whereColumn('activity_employee.delivery_project_activity_id', 'delivery_project_activities.id')
                        ->where('activity_employee.employee_id', $employeeId);
                })
                ->with(['phase', 'stage', 'delivery_project:id,name'])
                ->select('id', 'name', 'delivery_projects_id', 'delivery_project_phase_id', 'stage_id', 'status', 'start_date', 'end_date')
                ->orderBy('delivery_projects_id')
                ->orderBy('name')
                ->get();

            Log::info('Activities found', ['count' => $activities->count()]);

            $result = $activities->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'name' => $activity->name,
                        'delivery_projects_id' => $activity->delivery_projects_id,
                        'project_name' => $activity->delivery_project->name ?? 'Unknown Project',
                        'phase_name' => $activity->phase->name ?? null,
                        'stage_name' => $activity->stage->name ?? null,
                        'status' => $activity->status,
                        'start_date' => $activity->start_date,
                        'end_date' => $activity->end_date,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'All assigned activities retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error retrieving all my activities: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve activities: ' . $e->getMessage()
            ], 500);
        }
    }
}