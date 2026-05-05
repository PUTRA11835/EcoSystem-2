<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\DeliveryProject;
use App\Models\DeliveryActivity;
use App\Models\DeliveryGroup;
use App\Models\DeliveryStage;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * DeliveryActivityController
 *
 * Mengelola aktivitas dalam delivery planning.
 * Mendukung flexible parent: Stage atau Group langsung.
 */
class DeliveryActivityController extends Controller
{
    /**
     * Get all activities for a stage
     */
    public function indexByStage(DeliveryProject $project, DeliveryStage $stage)
    {
        try {
            $activities = DeliveryActivity::forStage($stage->id)
                ->ordered()
                ->with('assignedEmployees')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $activities->map(fn($a) => $this->formatActivity($a))
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting activities for stage', [
                'stage_id' => $stage->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load activities'
            ], 500);
        }
    }

    /**
     * Get all direct activities for a group (tanpa stage)
     */
    public function indexByGroup(DeliveryProject $project, DeliveryGroup $group)
    {
        try {
            $activities = DeliveryActivity::directlyInGroup($group->id)
                ->ordered()
                ->with('assignedEmployees')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $activities->map(fn($a) => $this->formatActivity($a))
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting activities for group', [
                'group_id' => $group->id,
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
     * Mendukung parent_type: stage atau group
     */
    public function store(Request $request, DeliveryProject $project)
    {
        $validated = $request->validate([
            'parent_type' => 'required|in:stage,group',
            'stage_id' => 'required_if:parent_type,stage|nullable|exists:delivery_stages,id',
            'group_id' => 'required_if:parent_type,group|nullable|exists:delivery_groups,id',
            'phase_id' => 'nullable|exists:delivery_phases,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'planned_start_date' => 'required|date',
            'planned_end_date' => 'required|date|after_or_equal:planned_start_date',
            'weight' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        try {
            return DB::transaction(function () use ($validated, $project) {
                $parentType = $validated['parent_type'];
                $phaseId = $validated['phase_id'] ?? null;
                $groupId = null;

                if ($parentType === 'stage') {
                    $stage = DeliveryStage::findOrFail($validated['stage_id']);
                    $group = $stage->group;
                    $groupId = $stage->group_id;
                    $phaseId = $phaseId ?? $group->phase_id;

                    if (isset($validated['weight'])) {
                        $currentTotal = DeliveryActivity::forStage($stage->id)->sum('weight');
                        if (($currentTotal + $validated['weight']) > 100.01) {
                            throw new \Exception(
                                "Total weight would exceed 100%. Current: {$currentTotal}%, trying to add: {$validated['weight']}%"
                            );
                        }
                    }

                    $maxSequence = DeliveryActivity::forStage($stage->id)->max('order_sequence') ?? 0;

                } else {
                    $group = DeliveryGroup::findOrFail($validated['group_id']);
                    $groupId = $group->id;
                    $phaseId = $phaseId ?? $group->phase_id;

                    if (isset($validated['weight'])) {
                        $currentTotal = DeliveryActivity::directlyInGroup($group->id)->sum('weight');
                        if (($currentTotal + $validated['weight']) > 100.01) {
                            throw new \Exception(
                                "Total weight would exceed 100%. Current: {$currentTotal}%, trying to add: {$validated['weight']}%"
                            );
                        }
                    }

                    $maxSequence = DeliveryActivity::directlyInGroup($group->id)->max('order_sequence') ?? 0;
                }

                $activity = DeliveryActivity::create([
                    'delivery_projects_id' => $project->id,
                    'phase_id' => $phaseId,
                    'parent_type' => $parentType,
                    'stage_id' => $parentType === 'stage' ? $validated['stage_id'] : null,
                    'group_id' => $groupId,
                    'name' => $validated['name'],
                    'code' => $validated['code'] ?? null,
                    'description' => $validated['description'] ?? null,
                    'order_sequence' => $maxSequence + 1,
                    'planned_start_date' => Carbon::parse($validated['planned_start_date']),
                    'planned_end_date' => Carbon::parse($validated['planned_end_date']),
                    'weight' => $validated['weight'] ?? 0,
                    'status' => 'not_started',
                    'progress_percentage' => 0,
                    'notes' => $validated['notes'] ?? null,
                ]);

                Log::info('Delivery activity created', [
                    'activity_id' => $activity->id,
                    'parent_type' => $parentType,
                    'name' => $activity->name
                ]);

                if ($parentType === 'stage' && isset($stage)) {
                    $stage->updateProgressFromActivities();
                    $stage->updateDatesFromActivities();
                    $stage->group->updateProgress();
                } else {
                    $group->updateProgress();
                    $group->updateDates();
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Activity created successfully',
                    'data' => $this->formatActivity($activity->fresh())
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error creating delivery activity', [
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
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
    public function show(DeliveryProject $project, DeliveryActivity $activity)
    {
        try {
            $activity->load(['stage', 'group', 'phase', 'assignedEmployees']);

            return response()->json([
                'success' => true,
                'data' => $this->formatActivity($activity, true)
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting delivery activity', [
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
    public function update(Request $request, DeliveryProject $project, DeliveryActivity $activity)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'code' => 'nullable|string|max:50',
            'description' => 'nullable|string',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date|after_or_equal:planned_start_date',
            'actual_start_date' => 'nullable|date',
            'actual_end_date' => 'nullable|date|after_or_equal:actual_start_date',
            'weight' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|in:not_started,in_progress,completed,delayed,on_hold,cancelled',
            'progress_percentage' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        try {
            return DB::transaction(function () use ($validated, $activity) {
                if (isset($validated['weight']) && $validated['weight'] != $activity->weight) {
                    if ($activity->parent_type === 'stage') {
                        $currentTotal = DeliveryActivity::forStage($activity->stage_id)
                            ->where('id', '!=', $activity->id)
                            ->sum('weight');
                    } else {
                        $currentTotal = DeliveryActivity::directlyInGroup($activity->group_id)
                            ->where('id', '!=', $activity->id)
                            ->sum('weight');
                    }

                    if (($currentTotal + $validated['weight']) > 100.01) {
                        throw new \Exception(
                            "Total weight would exceed 100%. Current (excluding this): {$currentTotal}%, trying to set: {$validated['weight']}%"
                        );
                    }
                }

                if (isset($validated['name'])) $activity->name = $validated['name'];
                if (isset($validated['code'])) $activity->code = $validated['code'];
                if (isset($validated['description'])) $activity->description = $validated['description'];
                if (isset($validated['weight'])) $activity->weight = $validated['weight'];
                if (isset($validated['status'])) $activity->status = $validated['status'];
                if (isset($validated['progress_percentage'])) $activity->progress_percentage = $validated['progress_percentage'];
                if (isset($validated['notes'])) $activity->notes = $validated['notes'];

                if (isset($validated['planned_start_date'])) {
                    $activity->planned_start_date = Carbon::parse($validated['planned_start_date']);
                }
                if (isset($validated['planned_end_date'])) {
                    $activity->planned_end_date = Carbon::parse($validated['planned_end_date']);
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

                Log::info('Delivery activity updated', [
                    'activity_id' => $activity->id,
                    'changes' => $validated
                ]);

                if ($activity->parent_type === 'stage' && $activity->stage) {
                    $activity->stage->updateProgressFromActivities();
                    $activity->stage->updateDatesFromActivities();
                    $activity->stage->group->updateProgress();
                } elseif ($activity->group) {
                    $activity->group->updateProgress();
                    $activity->group->updateDates();
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Activity updated successfully',
                    'data' => $this->formatActivity($activity->fresh())
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error updating delivery activity', [
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
    public function destroy(DeliveryProject $project, DeliveryActivity $activity)
    {
        try {
            return DB::transaction(function () use ($activity) {
                $parentType = $activity->parent_type;
                $stageId = $activity->stage_id;
                $groupId = $activity->group_id;

                $activity->assignedEmployees()->detach();

                $activity->delete();

                Log::info('Delivery activity deleted', ['activity_id' => $activity->id]);

                if ($parentType === 'stage' && $stageId) {
                    $stage = DeliveryStage::find($stageId);
                    if ($stage) {
                        $stage->updateProgressFromActivities();
                        $stage->updateDatesFromActivities();
                        $stage->group->updateProgress();
                    }
                } elseif ($groupId) {
                    $group = DeliveryGroup::find($groupId);
                    if ($group) {
                        $group->updateProgress();
                        $group->updateDates();
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Activity deleted successfully'
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error deleting delivery activity', [
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
     * Move activity to different parent
     */
    public function move(Request $request, DeliveryProject $project, DeliveryActivity $activity)
    {
        $validated = $request->validate([
            'parent_type' => 'required|in:stage,group',
            'stage_id' => 'required_if:parent_type,stage|nullable|exists:delivery_stages,id',
            'group_id' => 'required_if:parent_type,group|nullable|exists:delivery_groups,id',
        ]);

        try {
            return DB::transaction(function () use ($validated, $activity) {
                $oldParentType = $activity->parent_type;
                $oldStageId = $activity->stage_id;
                $oldGroupId = $activity->group_id;

                $newParentType = $validated['parent_type'];

                $activity->parent_type = $newParentType;

                if ($newParentType === 'stage') {
                    $newStage = DeliveryStage::findOrFail($validated['stage_id']);
                    $activity->stage_id = $newStage->id;
                    $activity->group_id = $newStage->group_id;

                    $maxSequence = DeliveryActivity::forStage($newStage->id)->max('order_sequence') ?? 0;
                } else {
                    $newGroup = DeliveryGroup::findOrFail($validated['group_id']);
                    $activity->stage_id = null;
                    $activity->group_id = $newGroup->id;

                    $maxSequence = DeliveryActivity::directlyInGroup($newGroup->id)->max('order_sequence') ?? 0;
                }

                $activity->order_sequence = $maxSequence + 1;
                $activity->save();

                Log::info('Activity moved', [
                    'activity_id' => $activity->id,
                    'old_parent_type' => $oldParentType,
                    'new_parent_type' => $newParentType
                ]);

                if ($oldParentType === 'stage' && $oldStageId) {
                    $oldStage = DeliveryStage::find($oldStageId);
                    if ($oldStage) {
                        $oldStage->updateProgressFromActivities();
                        $oldStage->updateDatesFromActivities();
                        $oldStage->group->updateProgress();
                    }
                } elseif ($oldGroupId) {
                    $oldGroup = DeliveryGroup::find($oldGroupId);
                    if ($oldGroup) {
                        $oldGroup->updateProgress();
                        $oldGroup->updateDates();
                    }
                }

                if ($newParentType === 'stage' && isset($newStage)) {
                    $newStage->updateProgressFromActivities();
                    $newStage->updateDatesFromActivities();
                    $newStage->group->updateProgress();
                } elseif (isset($newGroup)) {
                    $newGroup->updateProgress();
                    $newGroup->updateDates();
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Activity moved successfully',
                    'data' => $this->formatActivity($activity->fresh())
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error moving activity', [
                'activity_id' => $activity->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to move activity'
            ], 500);
        }
    }

    /**
     * Reorder activities
     */
    public function reorder(Request $request, DeliveryProject $project)
    {
        $validated = $request->validate([
            'activities' => 'required|array',
            'activities.*.id' => 'required|exists:delivery_activities,id',
            'activities.*.order_sequence' => 'required|integer|min:1',
        ]);

        try {
            return DB::transaction(function () use ($validated, $project) {
                foreach ($validated['activities'] as $item) {
                    DeliveryActivity::where('id', $item['id'])
                        ->where('delivery_projects_id', $project->id)
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
    public function bulkUpdateProgress(Request $request, DeliveryProject $project)
    {
        $validated = $request->validate([
            'activities' => 'required|array',
            'activities.*.id' => 'required|exists:delivery_activities,id',
            'activities.*.progress_percentage' => 'required|numeric|min:0|max:100',
        ]);

        try {
            return DB::transaction(function () use ($validated, $project) {
                $updatedStages = [];
                $updatedGroups = [];

                foreach ($validated['activities'] as $item) {
                    $activity = DeliveryActivity::where('id', $item['id'])
                        ->where('project_id', $project->id)
                        ->first();

                    if ($activity) {
                        $activity->progress_percentage = $item['progress_percentage'];
                        $activity->status = $this->determineStatus($item['progress_percentage']);
                        $activity->save();

                        if ($activity->parent_type === 'stage') {
                            $updatedStages[$activity->stage_id] = true;
                        } else {
                            $updatedGroups[$activity->group_id] = true;
                        }
                    }
                }

                // Update affected stages
                foreach (array_keys($updatedStages) as $stageId) {
                    $stage = DeliveryStage::find($stageId);
                    if ($stage) {
                        $stage->updateProgressFromActivities();
                        $updatedGroups[$stage->group_id] = true;
                    }
                }

                // Update affected groups
                foreach (array_keys($updatedGroups) as $groupId) {
                    $group = DeliveryGroup::find($groupId);
                    if ($group) {
                        $group->updateProgress();
                    }
                }

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
    public function getAssignedEmployees(DeliveryProject $project, DeliveryActivity $activity)
    {
        try {
            $employees = $activity->assignedEmployees()
                ->get()
                ->map(function ($employee) {
                    return [
                        'employee_id' => $employee->employee_id,
                        'name' => $employee->full_name ?? 'N/A',
                        'position' => $employee->position ?? 'N/A',
                        'department' => $employee->department ?? 'N/A',
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
    public function assignEmployee(Request $request, DeliveryProject $project, DeliveryActivity $activity)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employee,employee_id',
            'role' => 'nullable|in:lead,member,reviewer,support',
            'allocation_percentage' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        try {
            if ($activity->assignedEmployees()->where('employee.employee_id', $validated['employee_id'])->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee is already assigned to this activity'
                ], 422);
            }

            $activity->assignedEmployees()->attach($validated['employee_id'], [
                'role' => $validated['role'] ?? 'member',
                'allocation_percentage' => $validated['allocation_percentage'] ?? 100,
                'assigned_date' => now(),
                'is_active' => true,
                'notes' => $validated['notes'] ?? null,
            ]);

            Log::info('Employee assigned to activity', [
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
    public function updateAssignment(Request $request, DeliveryProject $project, DeliveryActivity $activity, $employeeId)
    {
        $validated = $request->validate([
            'role' => 'nullable|in:lead,member,reviewer,support',
            'allocation_percentage' => 'nullable|numeric|min:0|max:100',
            'is_active' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        try {
            $activity->assignedEmployees()->updateExistingPivot($employeeId, $validated);

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
    public function unassignEmployee(DeliveryProject $project, DeliveryActivity $activity, $employeeId)
    {
        try {
            $activity->assignedEmployees()->detach($employeeId);

            Log::info('Employee unassigned from activity', [
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
    protected function formatActivity(DeliveryActivity $activity, $includeDetails = false): array
    {
        $formatted = [
            'id' => $activity->id,
            'delivery_projects_id' => $activity->project_id,
            'phase_id' => $activity->phase_id,
            'parent_type' => $activity->parent_type,
            'stage_id' => $activity->stage_id,
            'group_id' => $activity->group_id,
            'name' => $activity->name,
            'code' => $activity->code,
            'order_sequence' => $activity->order_sequence,
            'weight' => $activity->weight,
            'status' => $activity->status,
            'status_text' => $activity->status_text,
            'status_badge' => $activity->status_badge,
            'progress_percentage' => $activity->progress_percentage,
            'planned_start_date' => $activity->planned_start_date?->format('Y-m-d'),
            'planned_end_date' => $activity->planned_end_date?->format('Y-m-d'),
            'duration_in_days' => $activity->duration_in_days,
            'is_overdue' => $activity->is_overdue,
            'assignees_count' => $activity->relationLoaded('assignedEmployees')
                ? $activity->assignedEmployees->count()
                : 0,
        ];

        if ($includeDetails) {
            $formatted['description'] = $activity->description;
            $formatted['actual_start_date'] = $activity->actual_start_date?->format('Y-m-d');
            $formatted['actual_end_date'] = $activity->actual_end_date?->format('Y-m-d');
            $formatted['actual_duration'] = $activity->actual_duration;
            $formatted['notes'] = $activity->notes;
            $formatted['parent_name'] = $activity->parent_name;
            $formatted['hierarchy_path'] = $activity->hierarchy_path;

            if ($activity->relationLoaded('stage') && $activity->stage) {
                $formatted['stage'] = [
                    'id' => $activity->stage->id,
                    'name' => $activity->stage->name,
                ];
            }

            if ($activity->relationLoaded('group') && $activity->group) {
                $formatted['group'] = [
                    'id' => $activity->group->id,
                    'name' => $activity->group->name,
                ];
            }

            if ($activity->relationLoaded('assignedEmployees')) {
                $formatted['assigned_employees'] = $activity->assignedEmployees->map(function($emp) {
                    return [
                        'employee_id' => $emp->employee_id,
                        'name' => $emp->full_name ?? 'N/A',
                        'role' => $emp->pivot->role,
                        'is_active' => $emp->pivot->is_active,
                    ];
                })->toArray();
            }
        }

        return $formatted;
    }
}
