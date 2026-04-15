<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\DeliverySupport;
use App\Models\DeliverySupportPhase;
use App\Models\DeliverySupportActivity;
use App\Models\DeliverySupportActivityStage;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * DeliverySupportActivityController
 *
 * Manages activities within support delivery phases
 */
class DeliverySupportActivityController extends Controller
{
    /**
     * Get all activities for a support item
     */
    public function index(DeliverySupport $support, Request $request)
    {
        try {
            $query = DeliverySupportActivity::where('delivery_support_id', $support->id)
                ->with(['phase', 'stages', 'employees.basicData'])
                ->orderBy('order_sequence');

            if ($request->has('phase_id')) {
                $query->where('delivery_support_phase_id', $request->get('phase_id'));
            }

            $activities = $query->get()->map(fn($a) => $this->formatActivity($a));

            return response()->json([
                'success' => true,
                'data' => $activities
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting support activities', [
                'support_id' => $support->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load activities'
            ], 500);
        }
    }

    /**
     * Get activities by phase
     */
    public function indexByPhase(DeliverySupport $support, DeliverySupportPhase $phase)
    {
        try {
            $activities = DeliverySupportActivity::where('delivery_support_id', $support->id)
                ->where('delivery_support_phase_id', $phase->id)
                ->with(['stages', 'employees.basicData'])
                ->orderBy('order_sequence')
                ->get()
                ->map(fn($a) => $this->formatActivity($a));

            return response()->json([
                'success' => true,
                'data' => $activities
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting activities by phase', [
                'phase_id' => $phase->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load activities'
            ], 500);
        }
    }

    /**
     * Create new activity
     */
    public function store(Request $request, DeliverySupport $support)
    {
        $validated = $request->validate([
            'delivery_support_phase_id' => 'required|exists:delivery_support_phases,id',
            'stage_id' => 'nullable|exists:delivery_support_activity_stages,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'module' => 'nullable|string|max:255',
            'new_issue' => 'boolean',
            'object' => 'nullable|string|max:255',
            'incident_type' => 'nullable|string|max:100',
            'complexity' => 'nullable|string|max:50',
            'deliverable' => 'nullable|string',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'weight' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        try {
            return DB::transaction(function () use ($validated, $support) {
                // Check weight limit
                if (isset($validated['weight'])) {
                    $currentTotal = DeliverySupportActivity::where('delivery_support_id', $support->id)
                        ->where('delivery_support_phase_id', $validated['delivery_support_phase_id'])
                        ->sum('weight');
                    if (($currentTotal + $validated['weight']) > 100.01) {
                        throw new \Exception(
                            "Total weight would exceed 100%. Current: {$currentTotal}%, trying to add: {$validated['weight']}%"
                        );
                    }
                }

                $maxSequence = DeliverySupportActivity::where('delivery_support_id', $support->id)
                    ->where('delivery_support_phase_id', $validated['delivery_support_phase_id'])
                    ->max('order_sequence') ?? 0;

                $activity = DeliverySupportActivity::create([
                    'delivery_support_id' => $support->id,
                    'delivery_support_phase_id' => $validated['delivery_support_phase_id'],
                    'stage_id' => $validated['stage_id'] ?? null,
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'order_sequence' => $maxSequence + 1,
                    'module' => $validated['module'] ?? null,
                    'new_issue' => $validated['new_issue'] ?? false,
                    'object' => $validated['object'] ?? null,
                    'incident_type' => $validated['incident_type'] ?? null,
                    'complexity' => $validated['complexity'] ?? null,
                    'deliverable' => $validated['deliverable'] ?? null,
                    'start_date' => Carbon::parse($validated['start_date']),
                    'end_date' => Carbon::parse($validated['end_date']),
                    'weight' => $validated['weight'] ?? 0,
                    'status' => 'not_started',
                    'progress_percentage' => 0,
                    'notes' => $validated['notes'] ?? null,
                ]);

                Log::info('Support activity created', [
                    'activity_id' => $activity->id,
                    'support_id' => $support->id,
                    'name' => $activity->name
                ]);

                // Update support progress
                $support->updateCalculatedProgress();

                return response()->json([
                    'success' => true,
                    'message' => 'Activity created successfully',
                    'data' => $this->formatActivity($activity->fresh(['phase', 'stages']))
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error creating support activity', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create activity'
            ], 500);
        }
    }

    /**
     * Get activity details
     */
    public function show(DeliverySupport $support, DeliverySupportActivity $activity)
    {
        try {
            $activity->load(['phase', 'stages', 'employees.basicData']);

            return response()->json([
                'success' => true,
                'data' => $this->formatActivity($activity, true)
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting support activity', [
                'activity_id' => $activity->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Activity not found'
            ], 404);
        }
    }

    /**
     * Update activity
     */
    public function update(Request $request, DeliverySupport $support, DeliverySupportActivity $activity)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'module' => 'nullable|string|max:255',
            'new_issue' => 'boolean',
            'object' => 'nullable|string|max:255',
            'incident_type' => 'nullable|string|max:100',
            'complexity' => 'nullable|string|max:50',
            'deliverable' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'actual_start_date' => 'nullable|date',
            'actual_end_date' => 'nullable|date|after_or_equal:actual_start_date',
            'weight' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|string',
            'progress_percentage' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        try {
            return DB::transaction(function () use ($validated, $activity, $support) {
                // Check weight if changing
                if (isset($validated['weight']) && $validated['weight'] != $activity->weight) {
                    $currentTotal = DeliverySupportActivity::where('delivery_support_id', $support->id)
                        ->where('delivery_support_phase_id', $activity->delivery_support_phase_id)
                        ->where('id', '!=', $activity->id)
                        ->sum('weight');
                    if (($currentTotal + $validated['weight']) > 100.01) {
                        throw new \Exception(
                            "Total weight would exceed 100%. Current (excluding this): {$currentTotal}%"
                        );
                    }
                }

                $activity->fill($validated);

                if (isset($validated['start_date'])) {
                    $activity->start_date = Carbon::parse($validated['start_date']);
                }
                if (isset($validated['end_date'])) {
                    $activity->end_date = Carbon::parse($validated['end_date']);
                }
                if (isset($validated['actual_start_date'])) {
                    $activity->actual_start_date = $validated['actual_start_date']
                        ? Carbon::parse($validated['actual_start_date'])
                        : null;
                }
                if (isset($validated['actual_end_date'])) {
                    $activity->actual_end_date = $validated['actual_end_date']
                        ? Carbon::parse($validated['actual_end_date'])
                        : null;
                }

                $activity->save();

                Log::info('Support activity updated', [
                    'activity_id' => $activity->id,
                    'changes' => $validated
                ]);

                // Update support progress
                $support->updateCalculatedProgress();

                return response()->json([
                    'success' => true,
                    'message' => 'Activity updated successfully',
                    'data' => $this->formatActivity($activity->fresh(['phase', 'stages']))
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error updating support activity', [
                'activity_id' => $activity->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update activity'
            ], 500);
        }
    }

    /**
     * Delete activity
     */
    public function destroy(DeliverySupport $support, DeliverySupportActivity $activity)
    {
        try {
            return DB::transaction(function () use ($activity, $support) {
                // Detach employees
                $activity->employees()->detach();

                // Delete related stages
                $activity->stages()->delete();

                // Delete the activity
                $activity->delete();

                Log::info('Support activity deleted', ['activity_id' => $activity->id]);

                // Update support progress
                $support->updateCalculatedProgress();

                return response()->json([
                    'success' => true,
                    'message' => 'Activity deleted successfully'
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error deleting support activity', [
                'activity_id' => $activity->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete activity'
            ], 500);
        }
    }

    /**
     * Reorder activities
     */
    public function reorder(Request $request, DeliverySupport $support)
    {
        $validated = $request->validate([
            'activities' => 'required|array',
            'activities.*.id' => 'required|exists:delivery_support_activities,id',
            'activities.*.order_sequence' => 'required|integer|min:1',
        ]);

        try {
            return DB::transaction(function () use ($validated, $support) {
                foreach ($validated['activities'] as $item) {
                    DeliverySupportActivity::where('id', $item['id'])
                        ->where('delivery_support_id', $support->id)
                        ->update(['order_sequence' => $item['order_sequence']]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Activities reordered successfully'
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error reordering activities', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder activities'
            ], 500);
        }
    }

    /**
     * Bulk update progress
     */
    public function bulkUpdateProgress(Request $request, DeliverySupport $support)
    {
        $validated = $request->validate([
            'activities' => 'required|array',
            'activities.*.id' => 'required|exists:delivery_support_activities,id',
            'activities.*.progress_percentage' => 'required|numeric|min:0|max:100',
        ]);

        try {
            return DB::transaction(function () use ($validated, $support) {
                foreach ($validated['activities'] as $item) {
                    $activity = DeliverySupportActivity::where('id', $item['id'])
                        ->where('delivery_support_id', $support->id)
                        ->first();

                    if ($activity) {
                        $activity->progress_percentage = $item['progress_percentage'];
                        $activity->status = $this->determineStatus($item['progress_percentage']);
                        $activity->save();
                    }
                }

                // Update support progress
                $support->updateCalculatedProgress();

                return response()->json([
                    'success' => true,
                    'message' => 'Progress updated for ' . count($validated['activities']) . ' activities'
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error bulk updating progress', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update progress'
            ], 500);
        }
    }

    // =========================================================================
    // EMPLOYEE ASSIGNMENT
    // =========================================================================

    /**
     * Get assigned employees for an activity
     */
    public function getAssignedEmployees(DeliverySupport $support, DeliverySupportActivity $activity)
    {
        try {
            $employees = $activity->employees()
                ->with('basicData')
                ->get()
                ->map(function ($employee) {
                    return [
                        'employee_id' => $employee->employee_id,
                        'name' => $employee->basicData->full_name ?? 'N/A',
                        'position' => $employee->basicData->position ?? 'N/A',
                        'department' => $employee->basicData->department ?? 'N/A',
                        'role' => $employee->pivot->role,
                        'allocation_percentage' => $employee->pivot->allocation_percentage,
                        'assigned_date' => $employee->pivot->assigned_date,
                        'is_active' => $employee->pivot->is_active,
                        'notes' => $employee->pivot->notes,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $employees
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting assigned employees', [
                'activity_id' => $activity->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get assigned employees'
            ], 500);
        }
    }

    /**
     * Assign employee to activity
     */
    public function assignEmployee(Request $request, DeliverySupport $support, DeliverySupportActivity $activity)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employee,employee_id',
            'role' => 'nullable|in:lead,member,reviewer,support',
            'allocation_percentage' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        try {
            if ($activity->employees()->where('employee.employee_id', $validated['employee_id'])->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee is already assigned to this activity'
                ], 422);
            }

            $activity->employees()->attach($validated['employee_id'], [
                'role' => $validated['role'] ?? 'member',
                'allocation_percentage' => $validated['allocation_percentage'] ?? 100,
                'assigned_date' => now(),
                'is_active' => true,
                'notes' => $validated['notes'] ?? null,
            ]);

            Log::info('Employee assigned to support activity', [
                'activity_id' => $activity->id,
                'employee_id' => $validated['employee_id']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Employee assigned successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error assigning employee', [
                'activity_id' => $activity->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign employee'
            ], 500);
        }
    }

    /**
     * Update employee assignment
     */
    public function updateAssignment(Request $request, DeliverySupport $support, DeliverySupportActivity $activity, $employeeId)
    {
        $validated = $request->validate([
            'role' => 'nullable|in:lead,member,reviewer,support',
            'allocation_percentage' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        try {
            $activity->employees()->updateExistingPivot($employeeId, $validated);

            Log::info('Assignment updated', [
                'activity_id' => $activity->id,
                'employee_id' => $employeeId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Assignment updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating assignment', [
                'activity_id' => $activity->id,
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update assignment'
            ], 500);
        }
    }

    /**
     * Unassign employee from activity
     */
    public function unassignEmployee(DeliverySupport $support, DeliverySupportActivity $activity, $employeeId)
    {
        try {
            $activity->employees()->detach($employeeId);

            Log::info('Employee unassigned from support activity', [
                'activity_id' => $activity->id,
                'employee_id' => $employeeId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Employee removed from activity'
            ]);

        } catch (\Exception $e) {
            Log::error('Error unassigning employee', [
                'activity_id' => $activity->id,
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove employee'
            ], 500);
        }
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Determine status based on progress
     */
    protected function determineStatus(float $progress): string
    {
        if ($progress >= 100) return 'completed';
        if ($progress > 0) return 'in_progress';
        return 'not_started';
    }

    /**
     * Format activity for response
     */
    protected function formatActivity(DeliverySupportActivity $activity, $includeDetails = false): array
    {
        $formatted = [
            'id' => $activity->id,
            'delivery_support_id' => $activity->delivery_support_id,
            'delivery_support_phase_id' => $activity->delivery_support_phase_id,
            'stage_id' => $activity->stage_id,
            'name' => $activity->name,
            'order_sequence' => $activity->order_sequence,
            'module' => $activity->module,
            'new_issue' => $activity->new_issue,
            'object' => $activity->object,
            'incident_type' => $activity->incident_type,
            'complexity' => $activity->complexity,
            'weight' => $activity->weight,
            'status' => $activity->status,
            'progress_percentage' => $activity->progress_percentage,
            'start_date' => $activity->start_date?->format('Y-m-d'),
            'end_date' => $activity->end_date?->format('Y-m-d'),
            'stages_count' => $activity->relationLoaded('stages') ? $activity->stages->count() : 0,
            'assignees_count' => $activity->relationLoaded('employees') ? $activity->employees->count() : 0,
        ];

        if ($includeDetails) {
            $formatted['description'] = $activity->description;
            $formatted['deliverable'] = $activity->deliverable;
            $formatted['actual_start_date'] = $activity->actual_start_date?->format('Y-m-d');
            $formatted['actual_end_date'] = $activity->actual_end_date?->format('Y-m-d');
            $formatted['notes'] = $activity->notes;

            if ($activity->relationLoaded('phase') && $activity->phase) {
                $formatted['phase'] = [
                    'id' => $activity->phase->id,
                    'name' => $activity->phase->name,
                    'color' => $activity->phase->color,
                ];
            }

            if ($activity->relationLoaded('stages')) {
                $formatted['stages'] = $activity->stages->map(function($stage) {
                    return [
                        'id' => $stage->id,
                        'name' => $stage->name,
                        'progress' => $stage->progress,
                        'status' => $stage->status,
                    ];
                })->toArray();
            }

            if ($activity->relationLoaded('employees')) {
                $formatted['assigned_employees'] = $activity->employees->map(function($emp) {
                    return [
                        'employee_id' => $emp->employee_id,
                        'name' => $emp->basicData->full_name ?? 'N/A',
                        'role' => $emp->pivot->role,
                        'is_active' => $emp->pivot->is_active,
                    ];
                })->toArray();
            }
        }

        return $formatted;
    }
}
