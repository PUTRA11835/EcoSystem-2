<?php

namespace App\Http\Controllers;

use App\Models\DeliveryProject;
use App\Models\ActivityStage;
use App\Models\DeliveryProjectPlanning;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DeliveryProjectStageManagementController extends Controller
{
    /**
     * Create new stage
     */
    public function store(Request $request, DeliveryProject $project)
    {
        $validated = $request->validate([
            'planning_id' => 'required|exists:delivery_project_planning,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'weight' => 'nullable|numeric|min:0|max:100',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date|after_or_equal:planned_start_date',
            'color' => 'nullable|string|max:7',
        ]);

        try {
            return DB::transaction(function () use ($validated, $project) {
                $planning = DeliveryProjectPlanning::findOrFail($validated['planning_id']);
                
                // Validate total weight
                if (isset($validated['weight'])) {
                    $currentTotal = ActivityStage::where('planning_id', $planning->id)
                        ->sum('weight');
                    
                    if (($currentTotal + $validated['weight']) > 100.01) {
                        throw new \Exception("Total weight would exceed 100%. Current: {$currentTotal}%, trying to add: {$validated['weight']}%");
                    }
                }

                $maxSequence = ActivityStage::where('planning_id', $planning->id)
                    ->max('order_sequence') ?? 0;

                $stage = ActivityStage::create([
                    'planning_id' => $planning->id,
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'weight' => $validated['weight'] ?? 0,
                    'planned_start_date' => isset($validated['planned_start_date']) 
                        ? Carbon::parse($validated['planned_start_date']) 
                        : null,
                    'planned_end_date' => isset($validated['planned_end_date']) 
                        ? Carbon::parse($validated['planned_end_date']) 
                        : null,
                    'color' => $validated['color'] ?? '#06b6d4',
                    'order_sequence' => $maxSequence + 1,
                    'progress' => 0,
                    'status' => 'not_started',
                ]);

                Log::info('✅ Stage created', [
                    'stage_id' => $stage->id,
                    'planning_id' => $planning->id,
                    'name' => $stage->name
                ]);

                // Update parent group
                $planning->updateGroupStatus();

                return response()->json([
                    'success' => true,
                    'message' => 'Stage created successfully',
                    'data' => $stage->fresh()
                ], 201);
            });

        } catch (\Exception $e) {
            Log::error('Error creating stage', [
                'error' => $e->getMessage(),
                'data' => $validated
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create stage'
            ], 500);
        }
    }

    /**
     * Get stage details
     */
    public function show(DeliveryProject $project, ActivityStage $stage)
    {
        try {
            $stage->load([
                'planning',
                'projectActivities' => function($query) {
                    $query->orderBy('order_sequence');
                }
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $stage->id,
                    'planning_id' => $stage->planning_id,
                    'name' => $stage->name,
                    'description' => $stage->description,
                    'weight' => $stage->weight,
                    'planned_start_date' => $stage->planned_start_date?->format('Y-m-d'),
                    'planned_end_date' => $stage->planned_end_date?->format('Y-m-d'),
                    'actual_start_date' => $stage->actual_start_date?->format('Y-m-d'),
                    'actual_end_date' => $stage->actual_end_date?->format('Y-m-d'),
                    'color' => $stage->color,
                    'progress' => $stage->progress,
                    'status' => $stage->status,
                    'order_sequence' => $stage->order_sequence,
                    'activities_count' => $stage->projectActivities->count(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting stage', [
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
    public function update(Request $request, DeliveryProject $project, ActivityStage $stage)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'weight' => 'nullable|numeric|min:0|max:100',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date|after_or_equal:planned_start_date',
            'actual_start_date' => 'nullable|date',
            'actual_end_date' => 'nullable|date|after_or_equal:actual_start_date',
            'color' => 'nullable|string|max:7',
            'status' => 'nullable|in:not_started,in_progress,completed,delayed',
            'progress' => 'nullable|numeric|min:0|max:100',
        ]);

        try {
            return DB::transaction(function () use ($validated, $stage) {
                // Validate weight if changed
                if (isset($validated['weight']) && $validated['weight'] != $stage->weight) {
                    $currentTotal = ActivityStage::where('planning_id', $stage->planning_id)
                        ->where('id', '!=', $stage->id)
                        ->sum('weight');
                    
                    if (($currentTotal + $validated['weight']) > 100.01) {
                        throw new \Exception("Total weight would exceed 100%. Current (excluding this): {$currentTotal}%, trying to set: {$validated['weight']}%");
                    }
                }

                // Update fields
                if (isset($validated['name'])) $stage->name = $validated['name'];
                if (isset($validated['description'])) $stage->description = $validated['description'];
                if (isset($validated['weight'])) $stage->weight = $validated['weight'];
                if (isset($validated['color'])) $stage->color = $validated['color'];
                if (isset($validated['status'])) $stage->status = $validated['status'];
                if (isset($validated['progress'])) $stage->progress = $validated['progress'];
                
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
                if ($stage->planning) {
                    $stage->planning->updateGroupStatus();
                }

                Log::info('✅ Stage updated', [
                    'stage_id' => $stage->id,
                    'changes' => $validated
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Stage updated successfully',
                    'data' => $stage->fresh()
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error updating stage', [
                'stage_id' => $stage->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update stage'
            ], 500);
        }
    }

    /**
     * Delete stage
     */
    public function destroy(DeliveryProject $project, ActivityStage $stage)
    {
        try {
            return DB::transaction(function () use ($stage) {
                // Check if stage has activities
                if ($stage->projectActivities()->count() > 0) {
                    throw new \Exception('Cannot delete stage with activities. Remove activities first.');
                }

                $planningId = $stage->planning_id;
                $stage->delete();

                // Update parent group
                $planning = DeliveryProjectPlanning::find($planningId);
                if ($planning) {
                    $planning->updateGroupStatus();
                }

                Log::info('✅ Stage deleted', [
                    'stage_id' => $stage->id,
                    'planning_id' => $planningId
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Stage deleted successfully'
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error deleting stage', [
                'stage_id' => $stage->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete stage'
            ], 422);
        }
    }

    /**
     * Reorder stages
     */
    public function reorder(Request $request, DeliveryProject $project, ActivityStage $stage)
    {
        $validated = $request->validate([
            'new_sequence' => 'required|integer|min:1',
        ]);

        try {
            return DB::transaction(function () use ($stage, $validated) {
                $oldSequence = $stage->order_sequence;
                $newSequence = $validated['new_sequence'];

                if ($oldSequence == $newSequence) {
                    return response()->json([
                        'success' => true,
                        'message' => 'No change needed'
                    ]);
                }

                // Get all stages in same planning group
                $stages = ActivityStage::where('planning_id', $stage->planning_id)
                    ->orderBy('order_sequence')
                    ->get();

                // Reorder
                if ($newSequence > $oldSequence) {
                    // Moving down
                    foreach ($stages as $s) {
                        if ($s->id == $stage->id) continue;
                        
                        if ($s->order_sequence > $oldSequence && $s->order_sequence <= $newSequence) {
                            $s->order_sequence--;
                            $s->save();
                        }
                    }
                } else {
                    // Moving up
                    foreach ($stages as $s) {
                        if ($s->id == $stage->id) continue;
                        
                        if ($s->order_sequence >= $newSequence && $s->order_sequence < $oldSequence) {
                            $s->order_sequence++;
                            $s->save();
                        }
                    }
                }

                $stage->order_sequence = $newSequence;
                $stage->save();

                Log::info('✅ Stage reordered', [
                    'stage_id' => $stage->id,
                    'old_sequence' => $oldSequence,
                    'new_sequence' => $newSequence
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Stage reordered successfully'
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error reordering stage', [
                'stage_id' => $stage->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder stage'
            ], 500);
        }
    }
}