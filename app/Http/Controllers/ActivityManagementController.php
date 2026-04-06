<?php

namespace App\Http\Controllers;

use App\Models\DeliveryProject;
use App\Models\DeliveryProjectPlanning;
use App\Models\DeliveryProjectActivity;
use App\Models\ActivityStage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ActivityManagementController extends Controller
{
    /**
     * Parse date from various formats (dd/mm/yyyy or yyyy-mm-dd)
     */
    private function parseDate($dateString)
    {
        if (empty($dateString)) {
            return null;
        }

        // Check if it's in dd/mm/yyyy format
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateString)) {
            return Carbon::createFromFormat('d/m/Y', $dateString);
        }

        // Otherwise parse as standard format
        return Carbon::parse($dateString);
    }

    /**
     * Create new activity/group
     */
    public function store(Request $request, DeliveryProject $project)
    {
        Log::info('=== CREATE ACTIVITY REQUEST ===', [
            'delivery_projects_id' => $project->id,
            'all_input' => $request->all()
        ]);

        try {
            // Auto-resolve phase_id dari stage jika tidak ada
            $phaseId = $request->input('phase_id');
            
            if (!$phaseId && $request->has('stage_id')) {
                $stage = ActivityStage::find($request->input('stage_id'));
                if ($stage && $stage->planning) {
                    $phaseId = $stage->planning->phase_id;
                    Log::info('📍 Auto-resolved phase_id from stage', [
                        'stage_id' => $request->input('stage_id'),
                        'resolved_phase_id' => $phaseId
                    ]);
                }
            }

            $validated = $request->validate([
                'phase_id' => 'required_without:stage_id|nullable|exists:delivery_project_phases,id',
                'parent_id' => 'nullable|exists:delivery_project_planning,id',
                'stage_id' => 'nullable|exists:activity_stages,id',
                'is_group' => 'boolean',
                'name' => 'required|string|max:255',
                'weight' => 'nullable|numeric|min:0|max:100',
                'start_date' => 'required_if:is_group,false|nullable|date',
                'end_date' => 'required_if:is_group,false|nullable|date|after_or_equal:start_date',
                'notes' => 'nullable|string',
                'module' => 'nullable|string|max:255',
                'object' => 'nullable|string|max:255',
                'complexity' => 'nullable|in:low,medium,high',
                'receive_type' => 'nullable|string|max:255',
                'new_requirement' => 'boolean',
                'deliverable' => 'nullable|string',
            ]);

            if ($phaseId) {
                $validated['phase_id'] = $phaseId;
            }

            Log::info('Validation passed', ['validated' => $validated]);

            return DB::transaction(function () use ($validated, $project, $request) {
                $isGroup = $validated['is_group'] ?? false;
                
                // Validate weight jika ada stage_id
                if (!$isGroup && isset($validated['stage_id']) && isset($validated['weight'])) {
                    $stage = ActivityStage::find($validated['stage_id']);
                    if ($stage) {
                        // Check current total from ProjectActivity table (new structure)
                        $currentTotal = DeliveryProjectActivity::where('stage_id', $stage->id)
                            ->sum('weight');

                        Log::info('Weight validation', [
                            'stage_id' => $stage->id,
                            'current_total' => $currentTotal,
                            'new_weight' => $validated['weight'],
                            'sum' => $currentTotal + $validated['weight']
                        ]);

                        if (($currentTotal + $validated['weight']) > 100.01) {
                            throw new \Exception('Total weight would exceed 100%. Current: ' . number_format($currentTotal, 2) . '%, trying to add: ' . $validated['weight'] . '%');
                        }
                    }
                }
                
                Log::info('Creating item', [
                    'is_group' => $isGroup,
                    'name' => $validated['name'],
                    'phase_id' => $validated['phase_id'],
                    'stage_id' => $validated['stage_id'] ?? null
                ]);

                $level = 0;
                if (!empty($validated['parent_id'])) {
                    $parent = DeliveryProjectPlanning::find($validated['parent_id']);
                    if ($parent) {
                        $level = $parent->level + 1;
                    }
                }

                $query = DeliveryProjectPlanning::where('delivery_projects_id', $project->id);
                
                if (!empty($validated['stage_id'])) {
                    $query->where('stage_id', $validated['stage_id']);
                } elseif (!empty($validated['parent_id'])) {
                    $query->where('parent_id', $validated['parent_id']);
                } else {
                    $query->whereNull('parent_id');
                }
                
                $maxSequence = $query->max('order_sequence') ?? 0;

                $planning = new DeliveryProjectPlanning();
                $planning->delivery_projects_id = $project->id;
                $planning->phase_id = $validated['phase_id'];
                $planning->parent_id = $validated['parent_id'] ?? null;
                $planning->stage_id = $validated['stage_id'] ?? null;
                $planning->is_group = $isGroup;
                $planning->level = $level;
                $planning->order_sequence = $maxSequence + 1;
                $planning->status = 'not_started';
                $planning->progress_percentage = 0;
                $planning->notes = $validated['notes'] ?? null;

                if ($isGroup) {
                    $planning->group_name = $validated['name'];
                    $planning->start_date = null;
                    $planning->end_date = null;
                    $planning->weight = 0;
                    $planning->save();
                } else {
                    $maxActivitySequence = DeliveryProjectActivity::where('delivery_projects_id', $project->id)
                        ->where('delivery_project_phase_id', $validated['phase_id'])
                        ->max('order_sequence') ?? 0;

                    $activity = DeliveryProjectActivity::create([
                        'delivery_projects_id' => $project->id,
                        'delivery_project_phase_id' => $validated['phase_id'],
                        'stage_id' => $validated['stage_id'] ?? null,
                        'name' => $validated['name'],
                        'description' => $validated['notes'] ?? '',
                        'order_sequence' => $maxActivitySequence + 1,
                        'start_date' => $this->parseDate($validated['start_date']),
                        'end_date' => $this->parseDate($validated['end_date']),
                        'weight' => $validated['weight'] ?? 0,
                        'progress_percentage' => 0,
                        'status' => 'not_started',
                        'module' => $validated['module'] ?? null,
                        'object' => $validated['object'] ?? null,
                        'complexity' => $validated['complexity'] ?? null,
                        'receive_type' => $validated['receive_type'] ?? null,
                        'new_requirement' => $validated['new_requirement'] ?? false,
                        'deliverable' => $validated['deliverable'] ?? null,
                        'notes' => $validated['notes'] ?? null,
                    ]);

                    Log::info('✅ Activity created in project_activities', [
                        'activity_id' => $activity->id,
                        'name' => $activity->name,
                    ]);

                    $planning->start_date = $activity->start_date;
                    $planning->end_date = $activity->end_date;
                    $planning->weight = $activity->weight;
                    $planning->activity_id = $activity->id;
                    $planning->save();
                }

                // Update stage dates & progress
                if ($planning->stage_id) {
                    $stage = ActivityStage::find($planning->stage_id);
                    if ($stage) {
                        $stage->updateDatesFromActivities();
                        $stage->updateProgressFromActivities();
                        
                        $group = $stage->group;
                        if ($group) {
                            $group->updateGroupStatus();
                        }
                    }
                } elseif ($planning->parent_id) {
                    $parent = $planning->parent;
                    if ($parent && $parent->is_group) {
                        $parent->updateGroupStatus();
                    }
                }

                Log::info('=== CREATE SUCCESS ===', [
                    'planning_id' => $planning->id,
                    'activity_id' => $planning->activity_id ?? null,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => $isGroup ? 'Group created successfully' : 'Activity created successfully',
                    'data' => [
                        'id' => $planning->id,
                        'activity_id' => $planning->activity_id ?? null,
                        'name' => $planning->name,
                        'is_group' => $planning->is_group,
                    ]
                ]);
            });

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('=== VALIDATION ERROR ===', ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', array_map(fn($msgs) => implode(', ', $msgs), $e->errors()))
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('=== CREATE ERROR ===', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get activity details
     */
    public function show(Request $request, DeliveryProject $project, $activityId)
    {
        try {
            Log::info('=== GET ACTIVITY START ===', [
                'delivery_projects_id' => $project->id,
                'activity_id' => $activityId,
                'type' => $request->query('type')
            ]);

            // ✅ If type=activity, search ProjectActivity first to avoid ID conflicts
            if ($request->query('type') === 'activity') {
                Log::info('Type=activity specified, searching ProjectActivity first...');
                $activity = DeliveryProjectActivity::with('stage')->find($activityId);

                if ($activity) {
                    $responseData = [
                        'success' => true,
                        'id' => $activity->id,
                        'name' => $activity->name,
                        'is_group' => false,
                        'parent_id' => null,
                        'stage_id' => $activity->stage_id,
                        'phase_id' => $activity->stage->planning->phase_id ?? null,
                        'start_date' => $activity->start_date?->format('Y-m-d'),
                        'end_date' => $activity->end_date?->format('Y-m-d'),
                        'actual_start_date' => $activity->actual_start_date?->format('Y-m-d'),
                        'actual_end_date' => $activity->actual_end_date?->format('Y-m-d'),
                        'weight' => $activity->weight,
                        'status' => $activity->status,
                        'progress_percentage' => $activity->progress_percentage,
                        'notes' => $activity->notes,
                        'module' => $activity->module,
                        'object' => $activity->object,
                        'deliverable' => $activity->deliverable,
                        'complexity' => $activity->complexity,
                        'receive_type' => $activity->receive_type,
                        'new_requirement' => $activity->new_requirement,
                    ];

                    return response()->json($responseData);
                }

                throw new \Exception("Activity not found with ID: {$activityId}");
            }

            // Default behavior: check ProjectPlanning first (for groups/planning items)
            $planning = DeliveryProjectPlanning::with(['activity', 'stage'])->find($activityId);

            if (!$planning) {
                Log::info('Planning not found, searching in ProjectActivity...');
                $activity = DeliveryProjectActivity::with('stage')->find($activityId);

                if (!$activity) {
                    throw new \Exception("Activity not found with ID: {$activityId}");
                }

                $responseData = [
                    'success' => true,
                    'id' => $activity->id,
                    'name' => $activity->name,
                    'is_group' => false,
                    'parent_id' => null,
                    'stage_id' => $activity->stage_id,
                    'phase_id' => $activity->stage->planning->phase_id ?? null,
                    'start_date' => $activity->start_date?->format('Y-m-d'),
                    'end_date' => $activity->end_date?->format('Y-m-d'),
                    'actual_start_date' => $activity->actual_start_date?->format('Y-m-d'),
                    'actual_end_date' => $activity->actual_end_date?->format('Y-m-d'),
                    'weight' => $activity->weight,
                    'status' => $activity->status,
                    'progress_percentage' => $activity->progress_percentage,
                    'notes' => $activity->notes,
                    'module' => $activity->module,
                    'object' => $activity->object,
                    'deliverable' => $activity->deliverable,
                    'complexity' => $activity->complexity,
                    'receive_type' => $activity->receive_type,
                    'new_requirement' => $activity->new_requirement,
                ];

                return response()->json($responseData);
            }

            $phaseId = $planning->phase_id;

            $responseData = [
                'success' => true,
                'id' => $planning->id,
                'name' => $planning->name,
                'is_group' => $planning->is_group,
                'parent_id' => $planning->parent_id,
                'stage_id' => $planning->stage_id,
                'phase_id' => $phaseId,
                'start_date' => $planning->start_date?->format('Y-m-d'),
                'end_date' => $planning->end_date?->format('Y-m-d'),
                'actual_start_date' => $planning->actual_start_date?->format('Y-m-d'),
                'actual_end_date' => $planning->actual_end_date?->format('Y-m-d'),
                'weight' => $planning->weight,
                'status' => $planning->status,
                'progress_percentage' => $planning->progress_percentage,
                'notes' => $planning->notes,
            ];

            // Include stages if this is a group
            if ($planning->is_group) {
                $stages = ActivityStage::where('planning_id', $planning->id)
                    ->orderBy('order_sequence')
                    ->get()
                    ->map(function($stage) {
                        return [
                            'id' => $stage->id,
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
                        ];
                    });

                $responseData['data'] = $stages;

                // Calculate total weight for validation
                $responseData['validation'] = [
                    'total_weight' => $stages->sum('weight'),
                    'remaining_weight' => 100 - $stages->sum('weight'),
                ];
            }

            // Get extended fields from linked activity
            if ($planning->activity_id && $planning->activity) {
                $responseData['module'] = $planning->activity->module;
                $responseData['object'] = $planning->activity->object;
                $responseData['deliverable'] = $planning->activity->deliverable;
                $responseData['complexity'] = $planning->activity->complexity;
                $responseData['receive_type'] = $planning->activity->receive_type;
                $responseData['new_requirement'] = $planning->activity->new_requirement;
            }

            return response()->json($responseData);

        } catch (\Exception $e) {
            Log::error('=== GET ACTIVITY ERROR ===', [
                'activity_id' => $activityId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Activity not found: ' . $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update activity
     */
    public function update(Request $request, DeliveryProject $project, $activityId)
    {
        try {
            Log::info('=== UPDATE ACTIVITY START ===', [
                'delivery_projects_id' => $project->id,
                'activity_id' => $activityId,
                'type' => $request->query('type'),
                'data' => $request->all()
            ]);

            $validated = $request->validate([
                'name' => 'nullable|string|max:255',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'actual_start_date' => 'nullable|date',
                'actual_end_date' => 'nullable|date|after_or_equal:actual_start_date',
                'status' => 'nullable|in:not_started,in_progress,monitoring,completed,delayed',
                'progress_percentage' => 'nullable|numeric|min:0|max:100',
                'weight' => 'nullable|numeric|min:0|max:100',
                'stage_id' => 'nullable|exists:activity_stages,id',
                'notes' => 'nullable|string',
                'module' => 'nullable|string|max:255',
                'object' => 'nullable|string|max:255',
                'complexity' => 'nullable|in:low,medium,high',
                'receive_type' => 'nullable|string|max:255',
                'new_requirement' => 'boolean',
                'deliverable' => 'nullable|string',
            ]);

            $isActivityType = $request->query('type') === 'activity';

            return DB::transaction(function () use ($validated, $activityId, $isActivityType) {
                // ✅ If type=activity, search ProjectActivity first to avoid ID conflicts
                if ($isActivityType) {
                    $activity = DeliveryProjectActivity::find($activityId);

                    if ($activity) {
                        Log::info('Updating DeliveryProjectActivity directly (type=activity)', ['id' => $activityId]);

                        if (isset($validated['name'])) $activity->name = $validated['name'];
                        if (isset($validated['start_date'])) $activity->start_date = $this->parseDate($validated['start_date']);
                        if (isset($validated['end_date'])) $activity->end_date = $this->parseDate($validated['end_date']);
                        if (isset($validated['actual_start_date'])) {
                            $activity->actual_start_date = $this->parseDate($validated['actual_start_date']);
                        }
                        if (isset($validated['actual_end_date'])) {
                            $activity->actual_end_date = $this->parseDate($validated['actual_end_date']);
                        }
                        if (isset($validated['status'])) $activity->status = $validated['status'];
                        if (isset($validated['progress_percentage'])) $activity->progress_percentage = $validated['progress_percentage'];
                        if (isset($validated['weight'])) $activity->weight = $validated['weight'];
                        if (isset($validated['notes'])) $activity->notes = $validated['notes'];

                        // Extended fields
                        if (isset($validated['module'])) $activity->module = $validated['module'];
                        if (isset($validated['object'])) $activity->object = $validated['object'];
                        if (isset($validated['complexity'])) $activity->complexity = $validated['complexity'];
                        if (isset($validated['receive_type'])) $activity->receive_type = $validated['receive_type'];
                        if (isset($validated['new_requirement'])) $activity->new_requirement = $validated['new_requirement'];
                        if (isset($validated['deliverable'])) $activity->deliverable = $validated['deliverable'];

                        $activity->save();

                        if ($activity->stage_id) {
                            $stage = ActivityStage::find($activity->stage_id);
                            if ($stage) {
                                $stage->updateDatesFromActivities();
                                $stage->updateProgressFromActivities();

                                $group = $stage->group;
                                if ($group) {
                                    $group->updateGroupStatus();
                                }
                            }
                        }

                        return response()->json([
                            'success' => true,
                            'message' => 'Activity updated successfully',
                            'data' => $activity->fresh(['stage'])
                        ]);
                    }

                    throw new \Exception("Activity not found with ID: {$activityId}");
                }

                // Default behavior: check ProjectPlanning first
                $planning = DeliveryProjectPlanning::find($activityId);

                if ($planning) {
                    $oldStageId = $planning->stage_id;
                    
                    // Update planning basic fields
                    if (isset($validated['start_date'])) {
                        $planning->start_date = $this->parseDate($validated['start_date']);
                    }
                    if (isset($validated['end_date'])) {
                        $planning->end_date = $this->parseDate($validated['end_date']);
                    }
                    if (isset($validated['actual_start_date'])) {
                        $planning->actual_start_date = $this->parseDate($validated['actual_start_date']);
                    }
                    if (isset($validated['actual_end_date'])) {
                        $planning->actual_end_date = $this->parseDate($validated['actual_end_date']);
                    }
                    if (isset($validated['status'])) {
                        $planning->status = $validated['status'];
                    }
                    if (isset($validated['progress_percentage'])) {
                        $planning->progress_percentage = $validated['progress_percentage'];
                    }
                    if (isset($validated['weight'])) {
                        $planning->weight = $validated['weight'];
                    }
                    if (isset($validated['stage_id'])) {
                        $planning->stage_id = $validated['stage_id'];
                    }
                    if (isset($validated['notes'])) {
                        $planning->notes = $validated['notes'];
                    }

                    if (isset($validated['name'])) {
                        if ($planning->is_group) {
                            $planning->group_name = $validated['name'];
                        } else {
                            // Update name directly on planning or linked activity
                            $planning->name = $validated['name'];
                        }
                    }

                    $planning->save();

                    // Update linked ProjectActivity if exists
                    if ($planning->activity_id && $planning->activity) {
                        $activity = $planning->activity;
                        
                        if (isset($validated['name'])) $activity->name = $validated['name'];
                        if (isset($validated['start_date'])) $activity->start_date = $this->parseDate($validated['start_date']);
                        if (isset($validated['end_date'])) $activity->end_date = $this->parseDate($validated['end_date']);
                        if (isset($validated['actual_start_date'])) {
                            $activity->actual_start_date = $this->parseDate($validated['actual_start_date']);
                        }
                        if (isset($validated['actual_end_date'])) {
                            $activity->actual_end_date = $this->parseDate($validated['actual_end_date']);
                        }
                        if (isset($validated['status'])) $activity->status = $validated['status'];
                        if (isset($validated['progress_percentage'])) $activity->progress_percentage = $validated['progress_percentage'];
                        if (isset($validated['weight'])) $activity->weight = $validated['weight'];
                        if (isset($validated['notes'])) $activity->notes = $validated['notes'];
                        
                        // Extended fields
                        if (isset($validated['module'])) $activity->module = $validated['module'];
                        if (isset($validated['object'])) $activity->object = $validated['object'];
                        if (isset($validated['complexity'])) $activity->complexity = $validated['complexity'];
                        if (isset($validated['receive_type'])) $activity->receive_type = $validated['receive_type'];
                        if (isset($validated['new_requirement'])) $activity->new_requirement = $validated['new_requirement'];
                        if (isset($validated['deliverable'])) $activity->deliverable = $validated['deliverable'];
                        
                        $activity->save();
                    }

                    // Update stages if moved
                    if ($oldStageId && $oldStageId != $planning->stage_id) {
                        $oldStage = ActivityStage::find($oldStageId);
                        if ($oldStage) {
                            $oldStage->updateDatesFromActivities();
                            $oldStage->updateProgressFromActivities();
                        }
                    }

                    if ($planning->stage_id) {
                        $stage = ActivityStage::find($planning->stage_id);
                        if ($stage) {
                            $stage->updateDatesFromActivities();
                            $stage->updateProgressFromActivities();
                            
                            $group = $stage->group;
                            if ($group) {
                                $group->updateGroupStatus();
                            }
                        }
                    }

                    return response()->json([
                        'success' => true,
                        'message' => 'Updated successfully',
                        'data' => $planning->fresh(['activity', 'stage'])
                    ]);

                } else {
                    $activity = DeliveryProjectActivity::find($activityId);
                    
                    if (!$activity) {
                        throw new \Exception("Activity not found with ID: {$activityId}");
                    }

                    // Update ProjectActivity directly
                    if (isset($validated['name'])) $activity->name = $validated['name'];
                    if (isset($validated['start_date'])) $activity->start_date = $this->parseDate($validated['start_date']);
                    if (isset($validated['end_date'])) $activity->end_date = $this->parseDate($validated['end_date']);
                    if (isset($validated['actual_start_date'])) {
                        $activity->actual_start_date = $this->parseDate($validated['actual_start_date']);
                    }
                    if (isset($validated['actual_end_date'])) {
                        $activity->actual_end_date = $this->parseDate($validated['actual_end_date']);
                    }
                    if (isset($validated['status'])) $activity->status = $validated['status'];
                    if (isset($validated['progress_percentage'])) $activity->progress_percentage = $validated['progress_percentage'];
                    if (isset($validated['weight'])) $activity->weight = $validated['weight'];
                    if (isset($validated['notes'])) $activity->notes = $validated['notes'];
                    
                    // Extended fields
                    if (isset($validated['module'])) $activity->module = $validated['module'];
                    if (isset($validated['object'])) $activity->object = $validated['object'];
                    if (isset($validated['complexity'])) $activity->complexity = $validated['complexity'];
                    if (isset($validated['receive_type'])) $activity->receive_type = $validated['receive_type'];
                    if (isset($validated['new_requirement'])) $activity->new_requirement = $validated['new_requirement'];
                    if (isset($validated['deliverable'])) $activity->deliverable = $validated['deliverable'];
                    
                    $activity->save();

                    if ($activity->stage_id) {
                        $stage = ActivityStage::find($activity->stage_id);
                        if ($stage) {
                            $stage->updateDatesFromActivities();
                            $stage->updateProgressFromActivities();
                            
                            $group = $stage->group;
                            if ($group) {
                                $group->updateGroupStatus();
                            }
                        }
                    }

                    return response()->json([
                        'success' => true,
                        'message' => 'Updated successfully',
                        'data' => $activity->fresh(['stage'])
                    ]);
                }
            });

        } catch (\Exception $e) {
            Log::error('=== UPDATE ACTIVITY ERROR ===', [
                'activity_id' => $activityId,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete activity
     */
    public function destroy(DeliveryProject $project, $activityId)
    {
        if (is_null($activityId) || $activityId === 'null' || !is_numeric($activityId) || $activityId <= 0) {
            Log::error("Invalid activity ID for deletion: '{$activityId}'", [
                'delivery_projects_id' => $project->id
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid ID provided'
            ], 400);
        }

        try {
            Log::info('=== DELETE ACTIVITY START ===', [
                'delivery_projects_id' => $project->id,
                'activity_id' => $activityId
            ]);

            // Try to find in ProjectPlanning first
            $planning = DeliveryProjectPlanning::find($activityId);

            if (!$planning) {
                Log::info('Planning not found, searching in ProjectActivity...');
                $projectActivity = DeliveryProjectActivity::find($activityId);

                if (!$projectActivity) {
                    Log::error('Activity not found in both tables', ['id' => $activityId]);
                    return response()->json([
                        'success' => false,
                        'message' => 'Activity not found'
                    ], 404);
                }

                return DB::transaction(function () use ($projectActivity) {
                    $stageId = $projectActivity->stage_id;

                    $projectActivity->delete();

                    Log::info('ProjectActivity deleted', ['id' => $projectActivity->id]);

                    // Update stage progress if exists
                    if ($stageId) {
                        $stage = ActivityStage::find($stageId);
                        if ($stage) {
                            $stage->updateProgressFromActivities();

                            $group = $stage->group;
                            if ($group) {
                                $group->updateGroupStatus();
                            }
                        }
                    }

                    return response()->json([
                        'success' => true,
                        'message' => 'Activity deleted'
                    ]);
                });
            }

            // Handle ProjectPlanning deletion
            return DB::transaction(function () use ($planning) {
                if ($planning->is_group) {
                    if ($planning->children()->count() > 0) {
                        throw new \Exception('Cannot delete group with children. Empty it first.');
                    }
                    if ($planning->stages()->count() > 0) {
                        throw new \Exception('Cannot delete group with stages. Delete stages first.');
                    }
                }

                $stageId = $planning->stage_id;
                $parentId = $planning->parent_id;

                $planning->delete();

                Log::info('ProjectPlanning deleted', ['id' => $planning->id]);

                if ($stageId) {
                    $stage = ActivityStage::find($stageId);
                    if ($stage) {
                        $stage->updateProgressFromActivities();

                        $group = $stage->group;
                        if ($group) {
                            $group->updateGroupStatus();
                        }
                    }
                }

                if ($parentId) {
                    $parent = DeliveryProjectPlanning::find($parentId);
                    if ($parent) {
                        $parent->updateGroupStatus();
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => $planning->is_group ? 'Group deleted' : 'Activity deleted'
                ]);
            });

        } catch (\Exception $e) {
            Log::error('=== DELETE ACTIVITY ERROR ===', [
                'activity_id' => $activityId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get assigned members for an activity
     */
    public function getAssignedMembers(DeliveryProject $project, $activityId)
    {
        try {
            $activity = DeliveryProjectActivity::with(['assignedEmployees.basicData'])->find($activityId);

            if (!$activity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Activity not found'
                ], 404);
            }

            $members = $activity->assignedEmployees->map(function ($employee) {
                return [
                    'employee_id' => $employee->employee_id,
                    'name' => $employee->basicData?->full_name ?? $employee->eci ?? 'N/A',
                    'position' => $employee->basicData?->position ?? 'N/A',
                    'role' => $employee->pivot->role,
                    'assigned_date' => $employee->pivot->assigned_date,
                    'notes' => $employee->pivot->notes,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $members
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting assigned members', [
                'activity_id' => $activityId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get assigned members'
            ], 500);
        }
    }

    /**
     * Assign member to activity
     */
    public function assignMember(Request $request, DeliveryProject $project, $activityId)
    {
        try {
            $validated = $request->validate([
                'employee_id' => 'required|exists:employee,employee_id',
                'role' => 'nullable|string|max:100',
                'notes' => 'nullable|string',
            ]);

            $activity = DeliveryProjectActivity::find($activityId);

            if (!$activity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Activity not found'
                ], 404);
            }

            // Check if already assigned
            if ($activity->assignedEmployees()->where('employee.employee_id', $validated['employee_id'])->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee is already assigned to this activity'
                ], 422);
            }

            // Attach the employee
            $activity->assignedEmployees()->attach($validated['employee_id'], [
                'role' => $validated['role'] ?? 'Member',
                'assigned_date' => now()->toDateString(),
                'notes' => $validated['notes'] ?? null,
            ]);

            Log::info('Member assigned to activity', [
                'activity_id' => $activityId,
                'employee_id' => $validated['employee_id']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Member assigned successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error assigning member', [
                'activity_id' => $activityId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign member: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update assigned member role/notes
     */
    public function updateAssignedMember(Request $request, DeliveryProject $project, $activityId, $employeeId)
    {
        try {
            $validated = $request->validate([
                'role' => 'nullable|string|max:100',
                'notes' => 'nullable|string',
            ]);

            $activity = DeliveryProjectActivity::find($activityId);

            if (!$activity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Activity not found'
                ], 404);
            }

            $activity->assignedEmployees()->updateExistingPivot($employeeId, [
                'role' => $validated['role'],
                'notes' => $validated['notes'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Member updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating assigned member', [
                'activity_id' => $activityId,
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update member'
            ], 500);
        }
    }

    /**
     * Unassign member from activity
     */
    public function unassignMember(DeliveryProject $project, $activityId, $employeeId)
    {
        try {
            $activity = DeliveryProjectActivity::find($activityId);

            if (!$activity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Activity not found'
                ], 404);
            }

            $activity->assignedEmployees()->detach($employeeId);

            Log::info('Member unassigned from activity', [
                'activity_id' => $activityId,
                'employee_id' => $employeeId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Member removed successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error unassigning member', [
                'activity_id' => $activityId,
                'employee_id' => $employeeId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove member'
            ], 500);
        }
    }
}