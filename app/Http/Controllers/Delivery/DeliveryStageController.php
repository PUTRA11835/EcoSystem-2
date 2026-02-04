<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\DeliveryGroup;
use App\Models\DeliveryStage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * DeliveryStageController
 *
 * Mengelola tahapan (stages) dalam grup.
 * Stage adalah OPTIONAL - activity bisa langsung di grup.
 */
class DeliveryStageController extends Controller
{
    /**
     * Get all stages for a group
     */
    public function index(Project $project, DeliveryGroup $group)
    {
        try {
            $stages = DeliveryStage::forGroup($group->id)
                ->ordered()
                ->with(['activities' => function($query) {
                    $query->orderBy('order_sequence');
                }])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $stages->map(fn($s) => $this->formatStage($s))
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting delivery stages', [
                'group_id' => $group->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load stages'
            ], 500);
        }
    }

    /**
     * Create new stage
     */
    public function store(Request $request, Project $project, DeliveryGroup $group)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:30',
            'description' => 'nullable|string',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date|after_or_equal:planned_start_date',
            'weight' => 'nullable|numeric|min:0|max:100',
            'color' => 'nullable|string|max:7',
        ]);

        try {
            return DB::transaction(function () use ($validated, $project, $group) {
                // Validate weight sum
                if (isset($validated['weight'])) {
                    $currentTotal = DeliveryStage::forGroup($group->id)->sum('weight');

                    if (($currentTotal + $validated['weight']) > 100.01) {
                        throw new \Exception(
                            "Total weight would exceed 100%. Current: {$currentTotal}%, trying to add: {$validated['weight']}%"
                        );
                    }
                }

                $maxSequence = DeliveryStage::forGroup($group->id)->max('order_sequence') ?? 0;

                $stage = DeliveryStage::create([
                    'group_id' => $group->id,
                    'project_id' => $project->id,
                    'name' => $validated['name'],
                    'code' => $validated['code'] ?? null,
                    'description' => $validated['description'] ?? null,
                    'order_sequence' => $maxSequence + 1,
                    'color' => $validated['color'] ?? '#F59E0B',
                    'planned_start_date' => isset($validated['planned_start_date'])
                        ? Carbon::parse($validated['planned_start_date'])
                        : null,
                    'planned_end_date' => isset($validated['planned_end_date'])
                        ? Carbon::parse($validated['planned_end_date'])
                        : null,
                    'weight' => $validated['weight'] ?? 0,
                    'status' => 'not_started',
                    'progress_percentage' => 0,
                ]);

                Log::info('✅ Delivery stage created', [
                    'stage_id' => $stage->id,
                    'group_id' => $group->id,
                    'name' => $stage->name
                ]);

                // Update parent group
                $group->updateProgress();
                $group->updateDates();

                return response()->json([
                    'success' => true,
                    'message' => 'Stage created successfully',
                    'data' => $this->formatStage($stage->fresh())
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error creating delivery stage', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create stage: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get stage details
     */
    public function show(Project $project, DeliveryStage $stage)
    {
        try {
            $stage->load([
                'group',
                'activities' => function($query) {
                    $query->orderBy('order_sequence');
                }
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->formatStage($stage, true)
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting delivery stage', [
                'stage_id' => $stage->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Stage not found'
            ], 404);
        }
    }

    /**
     * Update stage
     */
    public function update(Request $request, Project $project, DeliveryStage $stage)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'code' => 'nullable|string|max:30',
            'description' => 'nullable|string',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date|after_or_equal:planned_start_date',
            'actual_start_date' => 'nullable|date',
            'actual_end_date' => 'nullable|date|after_or_equal:actual_start_date',
            'weight' => 'nullable|numeric|min:0|max:100',
            'color' => 'nullable|string|max:7',
            'status' => 'nullable|in:not_started,in_progress,completed,delayed,on_hold',
            'progress_percentage' => 'nullable|numeric|min:0|max:100',
        ]);

        try {
            return DB::transaction(function () use ($validated, $stage) {
                // Validate weight if changed
                if (isset($validated['weight']) && $validated['weight'] != $stage->weight) {
                    $currentTotal = DeliveryStage::forGroup($stage->group_id)
                        ->where('id', '!=', $stage->id)
                        ->sum('weight');

                    if (($currentTotal + $validated['weight']) > 100.01) {
                        throw new \Exception(
                            "Total weight would exceed 100%. Current (excluding this): {$currentTotal}%, trying to set: {$validated['weight']}%"
                        );
                    }
                }

                // Update fields
                if (isset($validated['name'])) $stage->name = $validated['name'];
                if (isset($validated['code'])) $stage->code = $validated['code'];
                if (isset($validated['description'])) $stage->description = $validated['description'];
                if (isset($validated['weight'])) $stage->weight = $validated['weight'];
                if (isset($validated['color'])) $stage->color = $validated['color'];
                if (isset($validated['status'])) $stage->status = $validated['status'];
                if (isset($validated['progress_percentage'])) $stage->progress_percentage = $validated['progress_percentage'];

                if (isset($validated['planned_start_date'])) {
                    $stage->planned_start_date = $validated['planned_start_date']
                        ? Carbon::parse($validated['planned_start_date'])
                        : null;
                }
                if (isset($validated['planned_end_date'])) {
                    $stage->planned_end_date = $validated['planned_end_date']
                        ? Carbon::parse($validated['planned_end_date'])
                        : null;
                }
                if (isset($validated['actual_start_date'])) {
                    $stage->actual_start_date = $validated['actual_start_date']
                        ? Carbon::parse($validated['actual_start_date'])
                        : null;
                }
                if (isset($validated['actual_end_date'])) {
                    $stage->actual_end_date = $validated['actual_end_date']
                        ? Carbon::parse($validated['actual_end_date'])
                        : null;
                }

                $stage->save();

                // Update parent group
                $stage->group->updateProgress();
                $stage->group->updateDates();

                Log::info('✅ Delivery stage updated', [
                    'stage_id' => $stage->id,
                    'changes' => $validated
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Stage updated successfully',
                    'data' => $this->formatStage($stage->fresh())
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error updating delivery stage', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update stage: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete stage
     */
    public function destroy(Project $project, DeliveryStage $stage)
    {
        try {
            // Check for activities
            if ($stage->activities()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete stage with activities. Remove activities first.'
                ], 422);
            }

            return DB::transaction(function () use ($stage) {
                $groupId = $stage->group_id;

                $stage->delete();

                Log::info('✅ Delivery stage deleted', ['stage_id' => $stage->id]);

                // Update parent group
                $group = DeliveryGroup::find($groupId);
                if ($group) {
                    $group->updateProgress();
                    $group->updateDates();
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Stage deleted successfully'
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error deleting delivery stage', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete stage: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reorder stages
     */
    public function reorder(Request $request, Project $project, DeliveryGroup $group)
    {
        $validated = $request->validate([
            'stages' => 'required|array',
            'stages.*.id' => 'required|exists:delivery_stages,id',
            'stages.*.order_sequence' => 'required|integer|min:1',
        ]);

        try {
            return DB::transaction(function () use ($validated, $group) {
                foreach ($validated['stages'] as $item) {
                    DeliveryStage::where('id', $item['id'])
                        ->where('group_id', $group->id)
                        ->update(['order_sequence' => $item['order_sequence']]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Stages reordered successfully'
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error reordering stages', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder stages'
            ], 500);
        }
    }

    /**
     * Recalculate stage progress from activities
     */
    public function recalculateProgress(Project $project, DeliveryStage $stage)
    {
        try {
            $stage->updateProgressFromActivities();
            $stage->updateDatesFromActivities();

            return response()->json([
                'success' => true,
                'message' => 'Progress recalculated',
                'data' => [
                    'progress_percentage' => $stage->fresh()->progress_percentage,
                    'status' => $stage->fresh()->status,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error recalculating stage progress', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to recalculate progress'
            ], 500);
        }
    }

    /**
     * Move stage to different group
     */
    public function move(Request $request, Project $project, DeliveryStage $stage)
    {
        $validated = $request->validate([
            'group_id' => 'required|exists:delivery_groups,id',
        ]);

        try {
            return DB::transaction(function () use ($validated, $stage) {
                $oldGroupId = $stage->group_id;
                $newGroupId = $validated['group_id'];

                if ($oldGroupId === $newGroupId) {
                    return response()->json([
                        'success' => true,
                        'message' => 'No change needed'
                    ]);
                }

                // Get new group
                $newGroup = DeliveryGroup::findOrFail($newGroupId);

                // Update stage
                $stage->group_id = $newGroupId;
                $stage->project_id = $newGroup->project_id;

                // Get new order sequence
                $maxSequence = DeliveryStage::forGroup($newGroupId)->max('order_sequence') ?? 0;
                $stage->order_sequence = $maxSequence + 1;

                $stage->save();

                // Update old group
                $oldGroup = DeliveryGroup::find($oldGroupId);
                if ($oldGroup) {
                    $oldGroup->updateProgress();
                    $oldGroup->updateDates();
                }

                // Update new group
                $newGroup->updateProgress();
                $newGroup->updateDates();

                Log::info('✅ Stage moved', [
                    'stage_id' => $stage->id,
                    'old_group' => $oldGroupId,
                    'new_group' => $newGroupId
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Stage moved successfully',
                    'data' => $this->formatStage($stage->fresh())
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error moving stage', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to move stage: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Format stage for response
     */
    protected function formatStage(DeliveryStage $stage, $includeDetails = false): array
    {
        $formatted = [
            'id' => $stage->id,
            'group_id' => $stage->group_id,
            'project_id' => $stage->project_id,
            'name' => $stage->name,
            'code' => $stage->code,
            'order_sequence' => $stage->order_sequence,
            'weight' => $stage->weight,
            'status' => $stage->status,
            'status_text' => $stage->status_text,
            'status_badge' => $stage->status_badge,
            'progress_percentage' => $stage->progress_percentage,
            'color' => $stage->display_color,
            'planned_start_date' => $stage->planned_start_date?->format('Y-m-d'),
            'planned_end_date' => $stage->planned_end_date?->format('Y-m-d'),
            'duration_in_days' => $stage->duration_in_days,
            'activities_count' => $stage->activities()->count(),
        ];

        if ($includeDetails) {
            $formatted['description'] = $stage->description;
            $formatted['actual_start_date'] = $stage->actual_start_date?->format('Y-m-d');
            $formatted['actual_end_date'] = $stage->actual_end_date?->format('Y-m-d');
            $formatted['actual_duration'] = $stage->actual_duration;

            // Include group info
            if ($stage->relationLoaded('group') && $stage->group) {
                $formatted['group'] = [
                    'id' => $stage->group->id,
                    'name' => $stage->group->name,
                ];
            }

            // Include activities
            if ($stage->relationLoaded('activities')) {
                $formatted['activities'] = $stage->activities->map(function($activity) {
                    return [
                        'id' => $activity->id,
                        'name' => $activity->name,
                        'code' => $activity->code,
                        'weight' => $activity->weight,
                        'status' => $activity->status,
                        'progress_percentage' => $activity->progress_percentage,
                        'planned_start_date' => $activity->planned_start_date?->format('Y-m-d'),
                        'planned_end_date' => $activity->planned_end_date?->format('Y-m-d'),
                        'order_sequence' => $activity->order_sequence,
                    ];
                })->toArray();
            }
        }

        return $formatted;
    }
}
