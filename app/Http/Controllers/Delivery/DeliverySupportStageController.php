<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\DeliverySupport;
use App\Models\DeliverySupportActivity;
use App\Models\DeliverySupportActivityStage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * DeliverySupportStageController
 *
 * Manages stages within support activities
 */
class DeliverySupportStageController extends Controller
{
    /**
     * Get all stages for an activity
     */
    public function index(DeliverySupport $support, DeliverySupportActivity $activity)
    {
        try {
            $stages = DeliverySupportActivityStage::where('activity_id', $activity->id)
                ->orderBy('order_sequence')
                ->get()
                ->map(fn($s) => $this->formatStage($s));

            return response()->json([
                'success' => true,
                'data' => $stages
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting support stages', [
                'activity_id' => $activity->id,
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
    public function store(Request $request, DeliverySupport $support, DeliverySupportActivity $activity)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date|after_or_equal:planned_start_date',
            'weight' => 'nullable|numeric|min:0|max:100',
            'color' => 'nullable|string|max:7',
        ]);

        try {
            // Check weight limit
            if (isset($validated['weight'])) {
                $currentTotal = DeliverySupportActivityStage::where('activity_id', $activity->id)
                    ->sum('weight');
                if (($currentTotal + $validated['weight']) > 100.01) {
                    return response()->json([
                        'success' => false,
                        'message' => "Total weight would exceed 100%. Current: {$currentTotal}%, trying to add: {$validated['weight']}%"
                    ], 422);
                }
            }

            $maxSequence = DeliverySupportActivityStage::where('activity_id', $activity->id)
                ->max('order_sequence') ?? 0;

            $stage = DeliverySupportActivityStage::create([
                'activity_id' => $activity->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'planned_start_date' => isset($validated['planned_start_date'])
                    ? Carbon::parse($validated['planned_start_date']) : null,
                'planned_end_date' => isset($validated['planned_end_date'])
                    ? Carbon::parse($validated['planned_end_date']) : null,
                'weight' => $validated['weight'] ?? 0,
                'color' => $validated['color'] ?? null,
                'order_sequence' => $maxSequence + 1,
                'status' => 'not_started',
                'progress' => 0,
            ]);

            Log::info('Support stage created', [
                'stage_id' => $stage->id,
                'activity_id' => $activity->id,
                'name' => $stage->name
            ]);

            // Update activity progress
            $this->updateActivityProgress($activity);

            return response()->json([
                'success' => true,
                'message' => 'Stage created successfully',
                'data' => $this->formatStage($stage)
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating support stage', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create stage'
            ], 500);
        }
    }

    /**
     * Get stage details
     */
    public function show(DeliverySupport $support, DeliverySupportActivityStage $stage)
    {
        try {
            $stage->load(['activity']);

            return response()->json([
                'success' => true,
                'data' => $this->formatStage($stage, true)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Stage not found'
            ], 404);
        }
    }

    /**
     * Update stage
     */
    public function update(Request $request, DeliverySupport $support, DeliverySupportActivityStage $stage)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date|after_or_equal:planned_start_date',
            'actual_start_date' => 'nullable|date',
            'actual_end_date' => 'nullable|date|after_or_equal:actual_start_date',
            'weight' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|in:not_started,in_progress,completed,delayed,on_hold',
            'progress' => 'nullable|numeric|min:0|max:100',
            'color' => 'nullable|string|max:7',
        ]);

        try {
            // Check weight if changing
            if (isset($validated['weight']) && $validated['weight'] != $stage->weight) {
                $currentTotal = DeliverySupportActivityStage::where('activity_id', $stage->activity_id)
                    ->where('id', '!=', $stage->id)
                    ->sum('weight');
                if (($currentTotal + $validated['weight']) > 100.01) {
                    return response()->json([
                        'success' => false,
                        'message' => "Total weight would exceed 100%. Current (excluding this): {$currentTotal}%"
                    ], 422);
                }
            }

            $stage->fill($validated);

            if (isset($validated['planned_start_date'])) {
                $stage->planned_start_date = $validated['planned_start_date']
                    ? Carbon::parse($validated['planned_start_date']) : null;
            }
            if (isset($validated['planned_end_date'])) {
                $stage->planned_end_date = $validated['planned_end_date']
                    ? Carbon::parse($validated['planned_end_date']) : null;
            }
            if (isset($validated['actual_start_date'])) {
                $stage->actual_start_date = $validated['actual_start_date']
                    ? Carbon::parse($validated['actual_start_date']) : null;
            }
            if (isset($validated['actual_end_date'])) {
                $stage->actual_end_date = $validated['actual_end_date']
                    ? Carbon::parse($validated['actual_end_date']) : null;
            }

            $stage->save();

            Log::info('Support stage updated', ['stage_id' => $stage->id]);

            // Update activity progress
            if ($stage->activity) {
                $this->updateActivityProgress($stage->activity);
            }

            return response()->json([
                'success' => true,
                'message' => 'Stage updated successfully',
                'data' => $this->formatStage($stage->fresh())
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating support stage', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update stage'
            ], 500);
        }
    }

    /**
     * Delete stage
     */
    public function destroy(DeliverySupport $support, DeliverySupportActivityStage $stage)
    {
        try {
            $activityId = $stage->activity_id;

            $stage->delete();

            Log::info('Support stage deleted', ['stage_id' => $stage->id]);

            // Update activity progress
            $activity = DeliverySupportActivity::find($activityId);
            if ($activity) {
                $this->updateActivityProgress($activity);
            }

            return response()->json([
                'success' => true,
                'message' => 'Stage deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting support stage', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete stage'
            ], 500);
        }
    }

    /**
     * Reorder stages
     */
    public function reorder(Request $request, DeliverySupport $support, DeliverySupportActivity $activity)
    {
        $validated = $request->validate([
            'stages' => 'required|array',
            'stages.*.id' => 'required|exists:delivery_support_activity_stages,id',
            'stages.*.order_sequence' => 'required|integer|min:1',
        ]);

        try {
            return DB::transaction(function () use ($validated, $activity) {
                foreach ($validated['stages'] as $item) {
                    DeliverySupportActivityStage::where('id', $item['id'])
                        ->where('activity_id', $activity->id)
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
     * Batch update stages (for modal batch submission)
     */
    public function batchUpdate(Request $request, DeliverySupport $support, DeliverySupportActivity $activity)
    {
        $validated = $request->validate([
            'stages' => 'required|array',
            'stages.*.id' => 'nullable',
            'stages.*.name' => 'required|string|max:255',
            'stages.*.description' => 'nullable|string',
            'stages.*.weight' => 'nullable|numeric|min:0|max:100',
            'stages.*.color' => 'nullable|string|max:7',
            'stages.*.order_sequence' => 'required|integer|min:0',
            'stages.*._action' => 'nullable|in:create,update,delete',
        ]);

        try {
            return DB::transaction(function () use ($validated, $activity, $support) {
                $result = ['created' => 0, 'updated' => 0, 'deleted' => 0];

                // First pass: delete
                foreach ($validated['stages'] as $stageData) {
                    if (($stageData['_action'] ?? '') === 'delete' && !empty($stageData['id'])) {
                        $stage = DeliverySupportActivityStage::find($stageData['id']);
                        if ($stage && $stage->activity_id == $activity->id) {
                            $stage->delete();
                            $result['deleted']++;
                        }
                    }
                }

                // Second pass: update
                foreach ($validated['stages'] as $stageData) {
                    if (($stageData['_action'] ?? '') === 'update' && !empty($stageData['id'])) {
                        $stage = DeliverySupportActivityStage::find($stageData['id']);
                        if ($stage && $stage->activity_id == $activity->id) {
                            $stage->update([
                                'name' => $stageData['name'],
                                'description' => $stageData['description'] ?? null,
                                'weight' => $stageData['weight'] ?? 0,
                                'color' => $stageData['color'] ?? null,
                                'order_sequence' => $stageData['order_sequence'],
                            ]);
                            $result['updated']++;
                        }
                    }
                }

                // Third pass: create
                foreach ($validated['stages'] as $stageData) {
                    if (($stageData['_action'] ?? '') === 'create') {
                        DeliverySupportActivityStage::create([
                            'activity_id' => $activity->id,
                            'name' => $stageData['name'],
                            'description' => $stageData['description'] ?? null,
                            'weight' => $stageData['weight'] ?? 0,
                            'color' => $stageData['color'] ?? null,
                            'order_sequence' => $stageData['order_sequence'],
                            'status' => 'not_started',
                            'progress' => 0,
                        ]);
                        $result['created']++;
                    }
                }

                // Update activity progress
                $this->updateActivityProgress($activity);

                // Update support progress
                $support->updateCalculatedProgress();

                return response()->json([
                    'success' => true,
                    'message' => "Stages updated: {$result['created']} created, {$result['updated']} updated, {$result['deleted']} deleted",
                    'data' => $result
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error batch updating stages', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update stages'
            ], 500);
        }
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Update activity progress from stages
     */
    protected function updateActivityProgress(DeliverySupportActivity $activity)
    {
        $stages = $activity->stages;

        if ($stages->isEmpty()) {
            return;
        }

        $totalWeight = $stages->sum('weight');

        if ($totalWeight > 0) {
            $weightedProgress = $stages->sum(function ($stage) {
                return ($stage->weight / 100) * $stage->progress;
            });
            $activity->progress_percentage = round($weightedProgress, 2);
        } else {
            $activity->progress_percentage = round($stages->avg('progress'), 2);
        }

        // Determine status
        if ($activity->progress_percentage >= 100) {
            $activity->status = 'completed';
        } elseif ($activity->progress_percentage > 0) {
            $activity->status = 'in_progress';
        } else {
            $activity->status = 'not_started';
        }

        $activity->save();

        // Update support progress
        if ($activity->support) {
            $activity->support->updateCalculatedProgress();
        }
    }

    /**
     * Format stage for response
     */
    protected function formatStage(DeliverySupportActivityStage $stage, $includeDetails = false): array
    {
        $formatted = [
            'id' => $stage->id,
            'activity_id' => $stage->activity_id,
            'name' => $stage->name,
            'order_sequence' => $stage->order_sequence,
            'weight' => $stage->weight,
            'status' => $stage->status,
            'progress' => $stage->progress,
            'color' => $stage->color,
            'planned_start_date' => $stage->planned_start_date?->format('Y-m-d'),
            'planned_end_date' => $stage->planned_end_date?->format('Y-m-d'),
        ];

        if ($includeDetails) {
            $formatted['description'] = $stage->description;
            $formatted['actual_start_date'] = $stage->actual_start_date?->format('Y-m-d');
            $formatted['actual_end_date'] = $stage->actual_end_date?->format('Y-m-d');
            $formatted['custom_fields'] = $stage->custom_fields;

            if ($stage->relationLoaded('activity') && $stage->activity) {
                $formatted['activity'] = [
                    'id' => $stage->activity->id,
                    'name' => $stage->activity->name,
                ];
            }
        }

        return $formatted;
    }
}
