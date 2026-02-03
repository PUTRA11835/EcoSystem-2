<?php

namespace App\Http\Controllers;

use App\Models\ActivityStage;
use App\Models\ProjectPlanning;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ActivityStageController extends Controller
{
    /**
     * ✅ Get all stages for a GROUP
     * Sekarang stage milik group, bukan activity
     */
    public function index(ProjectPlanning $planning)
    {
        // ✅ Validasi: Hanya group yang boleh punya stages
        if (!$planning->is_group) {
            return response()->json([
                'success' => false,
                'message' => 'Only groups can have stages. This is an activity.'
            ], 400);
        }

        $stages = $planning->stages()
            ->with(['activities' => function($query) {
                $query->orderBy('order_sequence');
            }])
            ->orderBy('order_sequence')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $stages,
            'validation' => [
                'total_weight' => $stages->sum('weight'),
                'is_valid' => $planning->validateStagesWeight(),
                'message' => $planning->getStagesWeightValidationMessage()
            ]
        ]);
    }

    /**
     * ✅ Create new stage under a group
     */
    public function store(Request $request, ProjectPlanning $planning)
    {
        // ✅ Validasi: Hanya group yang boleh punya stages
        if (!$planning->is_group) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot add stages to an activity. Stages belong to groups.'
            ], 400);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'planned_start_date' => 'required|date',
            'planned_end_date' => 'required|date|after_or_equal:planned_start_date',
            'weight' => 'required|numeric|min:0|max:100',
            'color' => 'nullable|string|max:7',
            'custom_fields' => 'nullable|array',
        ]);

        try {
            return DB::transaction(function () use ($validated, $planning) {
                // Get max sequence
                $maxSequence = $planning->stages()->max('order_sequence') ?? -1;

                // Auto-generate color if not provided
                if (empty($validated['color'])) {
                    $validated['color'] = ActivityStage::getAutoColor($maxSequence + 1);
                }

                // Create stage
                $stage = $planning->stages()->create([
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'planned_start_date' => Carbon::parse($validated['planned_start_date']),
                    'planned_end_date' => Carbon::parse($validated['planned_end_date']),
                    'weight' => $validated['weight'],
                    'progress' => 0,
                    'status' => 'not_started',
                    'color' => $validated['color'],
                    'custom_fields' => $validated['custom_fields'] ?? [],
                    'order_sequence' => $maxSequence + 1,
                ]);

                Log::info('Stage created successfully', [
                    'stage_id' => $stage->id,
                    'group_id' => $planning->id,
                    'name' => $stage->name
                ]);

                // Update parent group status
                $planning->updateGroupStatus();

                return response()->json([
                    'success' => true,
                    'message' => 'Stage created successfully',
                    'data' => $stage->fresh(['activities']),
                    'validation' => [
                        'total_weight' => $planning->stages()->sum('weight'),
                        'is_valid' => $planning->validateStagesWeight(),
                        'message' => $planning->getStagesWeightValidationMessage()
                    ]
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Error creating stage', [
                'planning_id' => $planning->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create stage: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ Get single stage details
     */
    public function show(ActivityStage $stage)
    {
        try {
            $stage->load(['activities', 'group']);
            
            // ✅ Count activities
            $activitiesCount = $stage->activities()->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $stage->id,
                    'name' => $stage->name,
                    'description' => $stage->description,
                    'planned_start_date' => $stage->planned_start_date?->format('Y-m-d'),
                    'planned_end_date' => $stage->planned_end_date?->format('Y-m-d'),
                    'actual_start_date' => $stage->actual_start_date?->format('Y-m-d'),
                    'actual_end_date' => $stage->actual_end_date?->format('Y-m-d'),
                    'weight' => $stage->weight,
                    'progress' => $stage->progress,
                    'status' => $stage->status,
                    'color' => $stage->color,
                    'order_sequence' => $stage->order_sequence,
                    'activities_count' => $activitiesCount, // ✅ BARU
                    'group_name' => $stage->group?->name,
                    'has_activities' => $activitiesCount > 0, // ✅ BARU (untuk disable dates)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching stage', [
                'stage_id' => $stage->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Stage not found: ' . $e->getMessage()
            ], 404);
        }
    }

    /**
     * ✅ Update stage
     */
    public function update(Request $request, ActivityStage $stage)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date|after_or_equal:planned_start_date',
            'actual_start_date' => 'nullable|date',  // ✅ ADD THIS
            'actual_end_date' => 'nullable|date|after_or_equal:actual_start_date',  // ✅ ADD THIS
            'progress' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|in:not_started,in_progress,completed,delayed',
            'weight' => 'nullable|numeric|min:0|max:100',
            'color' => 'nullable|string|max:7',
            'custom_fields' => 'nullable|array',
        ]);

        try {
            return DB::transaction(function () use ($validated, $stage, $request) {
                // Update fields
                if (isset($validated['name'])) {
                    $stage->name = $validated['name'];
                }
                if (isset($validated['description'])) {
                    $stage->description = $validated['description'];
                }
                if (isset($validated['planned_start_date'])) {
                    $stage->planned_start_date = Carbon::parse($validated['planned_start_date']);
                }
                if (isset($validated['planned_end_date'])) {
                    $stage->planned_end_date = Carbon::parse($validated['planned_end_date']);
                }
                
                // ✅ CRITICAL FIX: Save actual dates properly
                if ($request->has('actual_start_date')) {
                    $stage->actual_start_date = $validated['actual_start_date'] 
                        ? Carbon::parse($validated['actual_start_date']) 
                        : null;
                }
                if ($request->has('actual_end_date')) {
                    $stage->actual_end_date = $validated['actual_end_date'] 
                        ? Carbon::parse($validated['actual_end_date']) 
                        : null;
                }
                
                if (isset($validated['progress'])) {
                    $stage->progress = $validated['progress'];
                }
                if (isset($validated['weight'])) {
                    $stage->weight = $validated['weight'];
                }
                if (isset($validated['color'])) {
                    $stage->color = $validated['color'];
                }
                if (isset($validated['custom_fields'])) {
                    $stage->custom_fields = $validated['custom_fields'];
                }

                // Auto-update status based on progress
                if (isset($validated['progress'])) {
                    if ($stage->progress == 0) {
                        $stage->status = 'not_started';
                    } elseif ($stage->progress >= 100) {
                        $stage->status = 'completed';
                    } elseif ($stage->progress > 0) {
                        $stage->status = 'in_progress';
                    }
                }

                // Manual status override
                if (isset($validated['status'])) {
                    $stage->status = $validated['status'];
                }

                // Check if delayed
                if ($stage->planned_end_date && Carbon::now()->gt($stage->planned_end_date) && 
                    $stage->status != 'completed') {
                    $stage->status = 'delayed';
                }

                $stage->save();

                Log::info('✅ Stage updated with actual dates', [
                    'stage_id' => $stage->id,
                    'name' => $stage->name,
                    'actual_start_date' => $stage->actual_start_date?->format('Y-m-d'),
                    'actual_end_date' => $stage->actual_end_date?->format('Y-m-d'),
                ]);

                // Update parent group
                $group = $stage->group;
                if ($group) {
                    $group->updateGroupStatus();
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Stage updated successfully',
                    'data' => $stage->fresh(['activities']),
                    'validation' => [
                        'total_weight' => $group ? $group->stages()->sum('weight') : 0,
                        'is_valid' => $group ? $group->validateStagesWeight() : true,
                        'message' => $group ? $group->getStagesWeightValidationMessage() : ''
                    ]
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Error updating stage', [
                'stage_id' => $stage->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update stage: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ Delete stage
     */
    public function destroy(ActivityStage $stage)
    {
        try {
            return DB::transaction(function () use ($stage) {
                $groupId = $stage->planning_id;
                $stageName = $stage->name;

                // ✅ Check if stage has activities
                if ($stage->activities()->count() > 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot delete stage with activities. Please move or delete activities first.'
                    ], 422);
                }

                $stage->delete();

                Log::info('Stage deleted successfully', [
                    'stage_id' => $stage->id,
                    'name' => $stageName
                ]);

                // Reorder remaining stages
                $group = ProjectPlanning::find($groupId);
                if ($group) {
                    $group->stages()->orderBy('order_sequence')->get()->each(function ($s, $index) {
                        $s->order_sequence = $index;
                        $s->saveQuietly();
                    });

                    // Update group status
                    $group->updateGroupStatus();
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Stage deleted successfully',
                    'validation' => [
                        'total_weight' => $group ? $group->stages()->sum('weight') : 0,
                        'is_valid' => $group ? $group->validateStagesWeight() : true,
                        'message' => $group ? $group->getStagesWeightValidationMessage() : ''
                    ]
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Error deleting stage', [
                'stage_id' => $stage->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete stage: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ Reorder stages
     */
    public function reorder(Request $request, ProjectPlanning $planning)
    {
        if (!$planning->is_group) {
            return response()->json([
                'success' => false,
                'message' => 'Can only reorder stages for groups'
            ], 400);
        }

        $validated = $request->validate([
            'stages' => 'required|array',
            'stages.*.id' => 'required|exists:activity_stages,id',
            'stages.*.sequence' => 'required|integer|min:0',
        ]);

        try {
            return DB::transaction(function () use ($validated, $planning) {
                foreach ($validated['stages'] as $stageData) {
                    $stage = ActivityStage::find($stageData['id']);
                    
                    if ($stage->planning_id !== $planning->id) {
                        throw new \Exception('Invalid stage ID');
                    }
                    
                    $stage->order_sequence = $stageData['sequence'];
                    $stage->saveQuietly();
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Stages reordered successfully',
                    'data' => $planning->stages()->orderBy('order_sequence')->get()
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Error reordering stages', [
                'planning_id' => $planning->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder stages: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ Bulk create stages
     */
    public function bulkCreate(Request $request, ProjectPlanning $planning)
    {
        if (!$planning->is_group) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot add stages to an activity'
            ], 400);
        }

        $validated = $request->validate([
            'stages' => 'required|array|min:1',
            'stages.*.name' => 'required|string|max:255',
            'stages.*.planned_start_date' => 'required|date',
            'stages.*.planned_end_date' => 'required|date',
            'stages.*.weight' => 'required|numeric|min:0|max:100',
        ]);

        try {
            return DB::transaction(function () use ($validated, $planning) {
                $createdStages = [];
                $sequence = $planning->stages()->max('order_sequence') ?? -1;

                foreach ($validated['stages'] as $index => $stageData) {
                    $stage = $planning->stages()->create([
                        'name' => $stageData['name'],
                        'planned_start_date' => Carbon::parse($stageData['planned_start_date']),
                        'planned_end_date' => Carbon::parse($stageData['planned_end_date']),
                        'weight' => $stageData['weight'],
                        'progress' => 0,
                        'status' => 'not_started',
                        'color' => ActivityStage::getAutoColor($sequence + $index + 1),
                        'order_sequence' => $sequence + $index + 1,
                    ]);

                    $createdStages[] = $stage;
                }

                // Update group status
                $planning->updateGroupStatus();

                return response()->json([
                    'success' => true,
                    'message' => count($createdStages) . ' stages created successfully',
                    'data' => $createdStages,
                    'validation' => [
                        'total_weight' => $planning->stages()->sum('weight'),
                        'is_valid' => $planning->validateStagesWeight(),
                        'message' => $planning->getStagesWeightValidationMessage()
                    ]
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Error bulk creating stages', [
                'planning_id' => $planning->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create stages: ' . $e->getMessage()
            ], 500);
        }
    }
}