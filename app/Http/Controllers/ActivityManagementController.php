<?php

namespace App\Http\Controllers;

use App\Models\DeliveryProject;
use App\Models\DeliveryProjectPhase;
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
                'actual_start_date' => 'nullable|date',
                'actual_end_date' => 'nullable|date',
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

            // Phase weight validation (before DB transaction so we can return 422)
            $isGroupCheck = $validated['is_group'] ?? false;
            if (!$isGroupCheck && isset($validated['phase_id']) && isset($validated['weight']) && $validated['weight'] > 0) {
                $phaseRecord = DeliveryProjectPhase::find($validated['phase_id']);
                if ($phaseRecord && $phaseRecord->weight > 0) {
                    $usedWeight = (float) DeliveryProjectPlanning::where('delivery_projects_id', $project->id)
                        ->where('phase_id', $validated['phase_id'])
                        ->where('is_group', false)
                        ->sum('weight');
                    if (($usedWeight + (float)$validated['weight']) > $phaseRecord->weight + 0.01) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Bobot melebihi batas fase "' . $phaseRecord->name . '". Batas: ' . $phaseRecord->weight . '%, Sudah terpakai: ' . round($usedWeight, 2) . '%, Sisa: ' . round($phaseRecord->weight - $usedWeight, 2) . '%.',
                        ], 422);
                    }
                }
            }

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
                            throw new \Exception('Total weight would exceed 100%. Current' . number_format($currentTotal, 2) . '%, trying to add' . $validated['weight'] . '%');
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
                        'actual_start_date' => isset($validated['actual_start_date']) ? $this->parseDate($validated['actual_start_date']) : null,
                        'actual_end_date' => isset($validated['actual_end_date']) ? $this->parseDate($validated['actual_end_date']) : null,
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
                    $planning->actual_start_date = $activity->actual_start_date;
                    $planning->actual_end_date = $activity->actual_end_date;
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
                            $group->recalculateGroupWeight();
                        }
                    }
                } elseif ($planning->parent_id) {
                    $parent = DeliveryProjectPlanning::find($planning->parent_id);
                    if ($parent && $parent->is_group) {
                        $parent->recalculateGroupWeight();
                        // ✅ FIX: juga update progress & status group dari direct activities
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
                ], 201);
            });

        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('=== VALIDATION ERROR ===', ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', array_map(fn($msgs) => implode(', ', $msgs), $e->errors()))
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('=== CREATE ERROR ===', [
                'message' => 'An error occurred',
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create the activity. Please try again.'
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
                        // ✅ null-safe: direct group activities have stage_id = null (PHP 8.2 compat)
                        'phase_id' => $activity->stage?->planning?->phase_id ?? null,
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
                    // ✅ null-safe: direct group activities have stage_id = null (PHP 8.2 compat)
                    'phase_id' => $activity->stage?->planning?->phase_id ?? null,
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
                'message' => 'Activity not found'
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
                'status' => 'nullable|in:not_started,in_progress,completed,delayed',
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

            // Validation: progress = 100 requires actual_end_date
            if (isset($validated['progress_percentage']) && (float)$validated['progress_percentage'] >= 100) {
                $hasActualEnd = !empty($validated['actual_end_date']);
                if (!$hasActualEnd) {
                    $existingActualEnd = null;
                    if ($isActivityType) {
                        $existingActualEnd = DeliveryProjectActivity::where('id', $activityId)->value('actual_end_date');
                    } else {
                        $existingActualEnd = DeliveryProjectPlanning::where('id', $activityId)->value('actual_end_date');
                    }
                    if (!$existingActualEnd) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Actual End Date wajib diisi saat progress 100%.',
                        ], 422);
                    }
                }
            }

            return DB::transaction(function () use ($validated, $activityId, $isActivityType, $project) {
                // ✅ If type=activity, search ProjectActivity first to avoid ID conflicts
                if ($isActivityType) {
                    $activity = DeliveryProjectActivity::find($activityId);

                    if ($activity) {
                        Log::info('Updating DeliveryProjectActivity directly (type=activity)', ['id' => $activityId]);

                        // Phase weight validation on update
                        if (isset($validated['weight']) && $validated['weight'] > 0) {
                            $phaseId = null;
                            if ($activity->stage_id) {
                                $stg = ActivityStage::with('planning')->find($activity->stage_id);
                                $phaseId = $stg?->planning?->phase_id;
                            } else {
                                $pr = DeliveryProjectPlanning::where('activity_id', $activity->id)->first();
                                $phaseId = $pr?->phase_id;
                            }
                            if ($phaseId) {
                                $phaseRecord = DeliveryProjectPhase::find($phaseId);
                                if ($phaseRecord && $phaseRecord->weight > 0) {
                                    $usedWeight = (float) DeliveryProjectPlanning::where('delivery_projects_id', $project->id)
                                        ->where('phase_id', $phaseId)
                                        ->where('is_group', false)
                                        ->where('activity_id', '!=', $activityId)
                                        ->sum('weight');
                                    if (($usedWeight + (float)$validated['weight']) > $phaseRecord->weight + 0.01) {
                                        throw new \Exception('Bobot melebihi batas fase "' . $phaseRecord->name . '". Batas: ' . $phaseRecord->weight . '%, Sudah terpakai (excl. ini): ' . round($usedWeight, 2) . '%, Sisa: ' . round($phaseRecord->weight - $usedWeight, 2) . '%.');
                                    }
                                }
                            }
                        }

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

                        // Sync the linked planning record so calculateGroupProgress() reads fresh data
                        $planningRecord = DeliveryProjectPlanning::where('activity_id', $activity->id)->first();
                        if ($planningRecord) {
                            $syncFields = [];
                            if (isset($validated['progress_percentage'])) {
                                $syncFields['progress_percentage'] = $validated['progress_percentage'];
                            }
                            if (isset($validated['status'])) {
                                $syncFields['status'] = $validated['status'];
                            }
                            if (isset($validated['weight'])) {
                                $syncFields['weight'] = $validated['weight'];
                            }
                            if (!empty($syncFields)) {
                                $planningRecord->fill($syncFields)->saveQuietly();
                            }
                        }

                        if ($activity->stage_id) {
                            $stage = ActivityStage::find($activity->stage_id);
                            if ($stage) {
                                $stage->updateDatesFromActivities();
                                $stage->updateProgressFromActivities();

                                $group = $stage->group;
                                if ($group) {
                                    $group->updateGroupStatus();
                                    $group->recalculateGroupWeight();
                                }
                            }
                        }

                        // Recalculate parent group weight AND progress for Phase→Group→Activity path
                        if ($planningRecord && $planningRecord->parent_id) {
                            $parentGroup = DeliveryProjectPlanning::find($planningRecord->parent_id);
                            if ($parentGroup && $parentGroup->is_group) {
                                $parentGroup->recalculateGroupWeight();
                                // ✅ FIX: juga update progress & status group dari direct activities
                                $parentGroup->updateGroupStatus();
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
                                $group->recalculateGroupWeight();
                            }
                        }
                    }

                    // Recalculate parent group weight AND progress for Phase→Group→Activity path
                    if (!$planning->stage_id && $planning->parent_id) {
                        $parentGroup = DeliveryProjectPlanning::find($planning->parent_id);
                        if ($parentGroup && $parentGroup->is_group) {
                            $parentGroup->recalculateGroupWeight();
                            // ✅ FIX: juga update progress & status group dari direct activities
                            $parentGroup->updateGroupStatus();
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
                'message' => $e->getMessage() ?: 'Failed to update',
            ], 422);
        }
    }

    /**
     * Get phase weight usage info for a given phase
     */
    public function getPhaseWeightInfo(Request $request, DeliveryProject $project, $phaseId)
    {
        $phase = DeliveryProjectPhase::findOrFail($phaseId);
        $phaseWeight = (float) ($phase->weight ?? 0);

        $excludeActivityId = $request->query('exclude_activity_id');

        $query = DeliveryProjectPlanning::where('delivery_projects_id', $project->id)
            ->where('phase_id', $phaseId)
            ->where('is_group', false);

        if ($excludeActivityId) {
            $query->where(function ($q) use ($excludeActivityId) {
                $q->where('activity_id', '!=', $excludeActivityId)
                  ->orWhereNull('activity_id');
            });
        }

        $usedWeight = (float) $query->sum('weight');
        $remainingWeight = $phaseWeight > 0 ? max(0, $phaseWeight - $usedWeight) : null;

        return response()->json([
            'phase_name'       => $phase->name,
            'phase_weight'     => round($phaseWeight, 2),
            'used_weight'      => round($usedWeight, 2),
            'remaining_weight' => $remainingWeight !== null ? round($remainingWeight, 2) : null,
        ]);
    }

    /**
     * Delete activity or group
     */
    public function destroy(Request $request, DeliveryProject $project, $activityId)
    {
        if (is_null($activityId) || $activityId === 'null' || !is_numeric($activityId) || $activityId <= 0) {
            Log::error("Invalid activity ID for deletion '{$activityId}'", [
                'delivery_projects_id' => $project->id
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Invalid ID provided'
            ], 400);
        }

        $isGroupRequest = $request->boolean('is_group');

        try {
            Log::info('=== DELETE START ===', [
                'delivery_projects_id' => $project->id,
                'id' => $activityId,
                'is_group' => $isGroupRequest,
            ]);

            // ─── GROUP DELETION ───────────────────────────────────────────────
            if ($isGroupRequest) {
                $planning = DeliveryProjectPlanning::find($activityId);

                if (!$planning || !$planning->is_group) {
                    return response()->json(['success' => false, 'message' => 'Group not found'], 404);
                }

                return DB::transaction(function () use ($planning) {
                    if ($planning->children()->count() > 0) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Cannot delete group with items inside. Empty it first.'
                        ], 422);
                    }
                    if ($planning->stages()->count() > 0) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Cannot delete group with stages. Delete stages first.'
                        ], 422);
                    }

                    $parentId = $planning->parent_id;
                    $planning->delete();

                    Log::info('Group planning deleted', ['id' => $planning->id]);

                    if ($parentId) {
                        $parent = DeliveryProjectPlanning::find($parentId);
                        if ($parent && $parent->is_group) {
                            $parent->recalculateGroupWeight();
                        }
                    }

                    return response()->json(['success' => true, 'message' => 'Group deleted']);
                });
            }

            // ─── ACTIVITY DELETION ────────────────────────────────────────────
            // Try delivery_project_activities FIRST (most common path from frontend)
            $projectActivity = DeliveryProjectActivity::find($activityId);

            if ($projectActivity) {
                return DB::transaction(function () use ($projectActivity) {
                    $stageId = $projectActivity->stage_id;

                    // Delete the planning record first to remove FK reference
                    $planningRecord = DeliveryProjectPlanning::where('activity_id', $projectActivity->id)->first();
                    $parentGroupId = $planningRecord?->parent_id;

                    if ($planningRecord) {
                        $planningRecord->delete();
                        Log::info('Linked planning record deleted', ['planning_id' => $planningRecord->id]);
                    }

                    $projectActivity->delete();
                    Log::info('DeliveryProjectActivity deleted', ['id' => $projectActivity->id]);

                    if ($stageId) {
                        $stage = ActivityStage::find($stageId);
                        if ($stage) {
                            $stage->updateProgressFromActivities();
                            $group = $stage->group;
                            if ($group) {
                                $group->updateGroupStatus();
                                $group->recalculateGroupWeight();
                            }
                        }
                    }

                    if ($parentGroupId) {
                        $parentGroup = DeliveryProjectPlanning::find($parentGroupId);
                        if ($parentGroup && $parentGroup->is_group) {
                            $parentGroup->recalculateGroupWeight();
                            // ✅ FIX: juga update progress & status group dari direct activities
                            $parentGroup->updateGroupStatus();
                        }
                    }

                    return response()->json(['success' => true, 'message' => 'Activity deleted']);
                });
            }

            // Fallback: ID might be a planning record ID (activity type)
            $planning = DeliveryProjectPlanning::where('id', $activityId)->where('is_group', false)->first();

            if (!$planning) {
                Log::error('Item not found in either table', ['id' => $activityId]);
                return response()->json(['success' => false, 'message' => 'Activity not found'], 404);
            }

            return DB::transaction(function () use ($planning) {
                $stageId = $planning->stage_id;
                $parentId = $planning->parent_id;
                $linkedActivityId = $planning->activity_id;

                $planning->delete();
                Log::info('Activity planning deleted', ['planning_id' => $planning->id]);

                if ($linkedActivityId) {
                    DeliveryProjectActivity::where('id', $linkedActivityId)->delete();
                    Log::info('Linked DeliveryProjectActivity deleted', ['activity_id' => $linkedActivityId]);
                }

                if ($stageId) {
                    $stage = ActivityStage::find($stageId);
                    if ($stage) {
                        $stage->updateProgressFromActivities();
                        $group = $stage->group;
                        if ($group) {
                            $group->updateGroupStatus();
                            $group->recalculateGroupWeight();
                        }
                    }
                }

                if ($parentId) {
                    $parent = DeliveryProjectPlanning::find($parentId);
                    if ($parent && $parent->is_group) {
                        $parent->recalculateGroupWeight();
                        // ✅ FIX: juga update progress & status group dari direct activities
                        $parent->updateGroupStatus();
                    }
                }

                return response()->json(['success' => true, 'message' => 'Activity deleted']);
            });

        } catch (\Exception $e) {
            Log::error('=== DELETE ERROR ===', [
                'id' => $activityId,
                'is_group' => $isGroupRequest,
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete: ' . $e->getMessage()
            ], 500);
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
                'message' => 'Failed to assign member'
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