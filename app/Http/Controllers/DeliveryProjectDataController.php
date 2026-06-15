<?php

namespace App\Http\Controllers;

use App\Models\DeliveryProject;
use App\Models\DeliveryProjectPhase;
use App\Models\DeliveryProjectPlanning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DeliveryProjectDataController extends Controller
{
    /**
     * Get hierarchical data untuk table view
     */
    public function getTableData(DeliveryProject $project)
    {
        Log::info('📊 Getting table data', ['delivery_projects_id' => $project->id]);
        
        // One-to-Many relationship (phases belong to project)
        $phases = $project->phases()
            ->where('is_visible', true)
            ->orderBy('order_sequence', 'asc')
            ->get();
        
        $data = [];
        
        foreach ($phases as $phase) {
            $groups = $this->getPhaseGroupsHierarchical($project, $phase);
            
            $phaseDates = $this->calculatePhaseDates($groups);
            $phaseActualDates = $this->calculatePhaseActualDates($groups);
            $phaseProgress = $this->calculatePhaseProgress($groups, $phase->weight);
            $phaseStatus = $this->calculatePhaseStatus($groups, $phaseProgress);

            $data[] = [
                'phase' => [
                    'id' => $phase->id,
                    'name' => $phase->name,
                    'color' => $phase->color ?? '#6366f1',
                    'orientation' => $phase->orientation,
                    'weight' => $phase->weight,
                    'start_date' => $phaseDates['start'] ? $phaseDates['start']->format('d M Y') : '-',
                    'end_date' => $phaseDates['end'] ? $phaseDates['end']->format('d M Y') : '-',
                    'actual_start_date' => $phaseActualDates['start'] ? $phaseActualDates['start']->format('d M Y') : '-',
                    'actual_end_date' => $phaseActualDates['end'] ? $phaseActualDates['end']->format('d M Y') : '-',
                    'duration_in_days' => $phaseDates['duration'],
                    'progress' => $phaseProgress,
                    'status' => $phaseStatus['status'],
                    'status_text' => $phaseStatus['text'],
                    'status_badge' => $phaseStatus['badge'],
                ],
                'groups' => $groups
            ];
        }
        
        return response()->json($data);
    }

    /**
     * Get Gantt data
     */
    public function getGanttData($projectId)
    {
        try {
            $project = DeliveryProject::findOrFail($projectId);
            
            // One-to-Many relationship (phases belong to project)
            $phases = $project->phases()
                ->where('orientation', 'vertical')
                ->where('is_visible', true)
                ->with([
                    'plannings' => function($query) use ($projectId) {
                        $query->where('delivery_projects_id', $projectId)
                            ->where('is_group', true)
                            ->whereNull('parent_id')
                            ->with($this->getGanttRelations())
                            ->orderBy('order_sequence');
                    }
                ])
                ->orderBy('order_sequence')
                ->get();

            $allDates = [];
            $verticalGroups = [];
            
            foreach ($phases as $phase) {
                $phaseTasks = [];
                
                foreach ($phase->plannings as $group) {
                    $phaseTasks[] = $this->formatGroupForGantt($group, $allDates);
                }
                
                $phaseWeight = $phase->weight ?? 0;
                $phaseProgress = $this->calculatePhaseProgressFromGroups($phaseTasks);
                
                $verticalGroups[] = [
                    'id' => $phase->id,
                    'name' => $phase->name,
                    'color' => $phase->color ?? '#6366f1',
                    'weight' => $phaseWeight,
                    'progress' => $phaseProgress,
                    'tasks' => $phaseTasks,
                ];
            }
            
            if (empty($allDates)) {
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->addMonths(3)->endOfMonth();
            } else {
                sort($allDates);
                $startDate = Carbon::parse($allDates[0])->subWeek();
                $endDate = Carbon::parse($allDates[count($allDates) - 1])->addWeek();
            }

            return response()->json([
                'success' => true,
                'vertical_groups' => $verticalGroups,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting gantt data', [
                'delivery_projects_id' => $projectId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load gantt data'
            ], 500);
        }
    }

    /**
     * Get S-Curve data
     */
    public function getSCurveData($projectId)
    {
        try {
            $project = DeliveryProject::findOrFail($projectId);
            
            // One-to-Many relationship (phases belong to project)
            $phases = $project->phases()
                ->where('orientation', 'vertical')
                ->where('is_visible', true)
                ->with([
                    'plannings' => function($query) use ($projectId) {
                        $query->where('delivery_projects_id', $projectId)
                            ->where('is_group', true)
                            ->whereNull('parent_id')
                            ->with($this->getSCurveRelations())
                            ->orderBy('order_sequence');
                    }
                ])
                ->orderBy('order_sequence')
                ->get();

            $allDates = [];
            $dataPoints = [];
            
            foreach ($phases as $phase) {
                foreach ($phase->plannings as $group) {
                    $this->collectSCurveDates($group, $allDates, $dataPoints);
                }
            }

            if (empty($allDates)) {
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->addMonths(3)->endOfMonth();
            } else {
                sort($allDates);
                $startDate = Carbon::parse($allDates[0])->startOfWeek();
                $endDate = Carbon::parse($allDates[count($allDates) - 1])->endOfWeek();
            }

            $weeklyData = $this->generateWeeklyData($startDate, $endDate, $dataPoints);
            $statistics = $this->calculateSCurveStatistics($dataPoints);

            return response()->json([
                'success' => true,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'weekly_data' => $weeklyData,
                'statistics' => $statistics,
                'phases' => $this->formatPhasesForSCurve($phases),
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting S-curve data', [
                'delivery_projects_id' => $projectId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load S-curve data'
            ], 500);
        }
    }

    // Helper methods for data processing
    private function getGanttRelations()
    {
        return [
            'children' => function($query) {
                $query->where('is_group', true)
                    ->with(['children', 'stages'])
                    ->orderBy('order_sequence');
            },
            'stages' => function($q) {
                $q->orderBy('order_sequence')
                    ->with([
                        'projectActivities' => function($qq) {
                            $qq->orderBy('order_sequence');
                        }
                    ]);
            }
        ];
    }

    private function getSCurveRelations()
    {
        return [
            'children' => function($childQuery) {
                $childQuery->where('is_group', true)
                    ->with(['children', 'stages'])
                    ->orderBy('order_sequence');
            },
            'stages' => function($stageQuery) {
                $stageQuery->orderBy('order_sequence')
                    ->with([
                        'projectActivities' => function($actQuery) {
                            $actQuery->orderBy('order_sequence');
                        }
                    ]);
            }
        ];
    }

    private function getPhaseGroupsHierarchical(DeliveryProject $project, DeliveryProjectPhase $phase)
    {
        $groups = DeliveryProjectPlanning::where('delivery_projects_id', $project->id)
            ->where('phase_id', $phase->id)
            ->where('is_group', true)
            ->whereNull('parent_id')
            ->with([
                'children' => function($query) {
                    $query->where('is_group', true)
                        ->with([
                            'children',
                            'stages' => function($sq) {
                                $sq->orderBy('order_sequence')
                                    ->with(['projectActivities' => function($aq) {
                                        $aq->orderBy('order_sequence');
                                    }]);
                            },
                            // ✅ Load directActivities for sub-groups too
                            'directActivities' => function($dq) {
                                $dq->with('activity');
                            }
                        ])
                        ->orderBy('order_sequence');
                },
                'stages' => function($q) {
                    $q->orderBy('order_sequence')
                        ->with([
                            'projectActivities' => function($qq) {
                                $qq->orderBy('order_sequence');
                            }
                        ]);
                },
                // ✅ Load activities directly under group (without stage)
                'directActivities' => function($q) {
                    $q->with('activity');
                }
            ])
            ->orderBy('order_sequence')
            ->get();

        // Load relationships manually
        foreach ($groups as $group) {
            if ($group->stages) {
                foreach ($group->stages as $stage) {
                    if ($stage->activities) {
                        foreach ($stage->activities as $activity) {
                            if (method_exists($activity, 'children') && !$activity->relationLoaded('children')) {
                                $activity->load('children');
                            }

                            if ($activity->activity_id && method_exists($activity, 'activity') && !$activity->relationLoaded('activity')) {
                                $activity->load('activity');
                            }
                        }
                    }
                }
            }
        }

        $result = [];
        foreach ($groups as $group) {
            $result[] = $this->formatGroupRecursive($group);
        }

        return $result;
    }

    private function formatGroupRecursive($group, $level = 0)
    {
        $calculatedDates = $this->calculateGroupDates($group);
        $calculatedActualDates = $this->calculateGroupActualDates($group);
        $calculatedProgress = $this->calculateGroupProgress($group);
        $calculatedStatus = $this->calculateGroupStatus($group);

        $formatted = [
            'id' => $group->id,
            'type' => 'group',
            'name' => $group->name,
            'is_group' => true,
            'phase_id' => $group->phase_id,
            'parent_id' => $group->parent_id,
            'level' => $level,
            'weight' => $group->calculated_weight,
            'progress_percentage' => $calculatedProgress,
            'status' => $calculatedStatus['status'],
            'status_text' => $calculatedStatus['text'],
            'status_badge' => $calculatedStatus['badge'],
            'start_date' => $calculatedDates['start'] ? $calculatedDates['start']->format('d M Y') : '-',
            'end_date' => $calculatedDates['end'] ? $calculatedDates['end']->format('d M Y') : '-',
            'actual_start_date' => $calculatedActualDates['start'] ? $calculatedActualDates['start']->format('d M Y') : '-',
            'actual_end_date' => $calculatedActualDates['end'] ? $calculatedActualDates['end']->format('d M Y') : '-',
            'duration_in_days' => $calculatedDates['duration'],
            'notes' => $group->notes,
            'sub_groups' => [],
            'stages' => [],
            'activities' => [] // ✅ Activities directly under group (without stage)
        ];

        if ($group->children && $group->children->isNotEmpty()) {
            foreach ($group->children->where('is_group', true) as $subGroup) {
                $formatted['sub_groups'][] = $this->formatGroupRecursive($subGroup, $level + 1);
            }
        }

        if ($group->stages && $group->stages->isNotEmpty()) {
            foreach ($group->stages as $stage) {
                $formatted['stages'][] = $this->formatStageWithActivities($stage);
            }
        }

        // ✅ Include activities directly under group (without stage)
        // Force load directActivities if not already loaded
        if (!$group->relationLoaded('directActivities')) {
            $group->load(['directActivities' => function($q) {
                $q->with('activity');
            }]);
        }

        if ($group->directActivities && $group->directActivities->isNotEmpty()) {
            Log::info('📋 Found directActivities for group', [
                'group_id' => $group->id,
                'group_name' => $group->name,
                'count' => $group->directActivities->count()
            ]);

            foreach ($group->directActivities as $planningActivity) {
                if ($planningActivity->activity) {
                    $formatted['activities'][] = $this->formatActivityForHierarchy($planningActivity->activity, 0);
                } else {
                    // Fallback: format from planning record if no linked activity
                    $formatted['activities'][] = $this->formatActivityForHierarchy($planningActivity, 0);
                }
            }
        }

        return $formatted;
    }

    private function formatStageWithActivities($stage)
    {
        $stageData = [
            'id' => $stage->id,
            'type' => 'stage',
            'name' => $stage->name,
            'description' => $stage->description,
            'weight' => $stage->weight,
            'progress' => $stage->calculated_progress ?? $stage->progress ?? 0,
            'status' => $stage->status ?? 'not_started',
            'status_text' => $stage->status_label ?? 'Not Started',
            'status_badge' => $this->getStatusBadgeClass($stage->status ?? 'not_started'),
            'start_date' => $stage->planned_start_date ? $stage->planned_start_date->format('d M Y') : '-',
            'end_date' => $stage->planned_end_date ? $stage->planned_end_date->format('d M Y') : '-',
            'actual_start_date' => $stage->actual_start_date ? $stage->actual_start_date->format('d M Y') : '-',
            'actual_end_date' => $stage->actual_end_date ? $stage->actual_end_date->format('d M Y') : '-',
            'duration_in_days' => $stage->duration_days ?? null,
            'color' => $stage->color ?? '#06b6d4',
            'planning_id' => $stage->planning_id,
            'activities' => []
        ];

        if ($stage->projectActivities && $stage->projectActivities->isNotEmpty()) {
            foreach ($stage->projectActivities as $activity) {
                $stageData['activities'][] = $this->formatActivityForHierarchy($activity, 0);
            }
        } elseif ($stage->activities && $stage->activities->isNotEmpty()) {
            foreach ($stage->activities as $activity) {
                $stageData['activities'][] = $this->formatActivityForHierarchy($activity, 0);
            }
        }

        return $stageData;
    }

    private function formatActivityForHierarchy($activity, $level = 0)
    {
        $isProjectActivity = $activity instanceof \App\Models\DeliveryProjectActivity;
        
        $formatted = [
            'id' => $activity->id,
            'type' => 'activity',
            'parent_id' => $activity->parent_id ?? null,
            'stage_id' => $activity->stage_id ?? null,
            'level' => $level,
            'name' => $activity->name,
            'weight' => $activity->weight ?? 0,
            'notes' => $activity->notes ?? null,
            'children' => []
        ];

        if ($isProjectActivity) {
            $formatted['progress_percentage'] = $activity->progress_percentage ?? 0;
            $formatted['start_date'] = $activity->start_date ? $activity->start_date->format('d M Y') : '-';
            $formatted['end_date'] = $activity->end_date ? $activity->end_date->format('d M Y') : '-';
            $formatted['actual_start_date'] = $activity->actual_start_date ? $activity->actual_start_date->format('d M Y') : null;
            $formatted['actual_end_date'] = $activity->actual_end_date ? $activity->actual_end_date->format('d M Y') : null;
            $formatted['status'] = $activity->status ?? 'not_started';

            $duration = null;
            if ($activity->start_date && $activity->end_date) {
                $duration = $activity->start_date->diffInDays($activity->end_date) + 1;
            }
            $formatted['duration_in_days'] = $duration;

            // Actual duration (calendar days)
            $actualDuration = null;
            if ($activity->actual_start_date && $activity->actual_end_date) {
                $actualDuration = $activity->actual_start_date->diffInDays($activity->actual_end_date) + 1;
            }
            $formatted['actual_duration_in_days'] = $actualDuration;

            $formatted['status_text'] = ucwords(str_replace('_', ' ', $activity->status ?? 'not_started'));
            $formatted['status_badge'] = $this->getStatusBadgeClass($activity->status ?? 'not_started');

            $formatted['is_overdue'] = false;
            if ($activity->end_date && $activity->status !== 'completed') {
                $formatted['is_overdue'] = $activity->end_date->isPast();
            }

            $formatted['module'] = $activity->module;
            $formatted['object'] = $activity->object;
            $formatted['deliverable'] = $activity->deliverable;
            $formatted['complexity'] = $activity->complexity;
            $formatted['receive_type'] = $activity->receive_type;
            $formatted['new_requirement'] = $activity->new_requirement;
        } else {
            $formatted['progress_percentage'] = $activity->calculated_progress ?? $activity->progress_percentage ?? 0;
            $formatted['start_date'] = $activity->start_date ? $activity->start_date->format('d M Y') : '-';
            $formatted['end_date'] = $activity->end_date ? $activity->end_date->format('d M Y') : '-';
            $formatted['actual_start_date'] = $activity->actual_start_date ? $activity->actual_start_date->format('d M Y') : null;
            $formatted['actual_end_date'] = $activity->actual_end_date ? $activity->actual_end_date->format('d M Y') : null;
            $formatted['duration_in_days'] = $activity->duration_in_days ?? null;
            $formatted['status'] = $activity->status ?? 'not_started';
            $formatted['status_text'] = $activity->status_text ?? ucwords(str_replace('_', ' ', $activity->status ?? 'not_started'));
            $formatted['status_badge'] = $activity->status_badge ?? $this->getStatusBadgeClass($activity->status ?? 'not_started');
            $formatted['is_overdue'] = $activity->is_overdue ?? false;

            // Get extended fields from linked activity if exists
            if ($activity->activity_id && $activity->activity) {
                $formatted['module'] = $activity->activity->module ?? null;
                $formatted['object'] = $activity->activity->object ?? null;
                $formatted['deliverable'] = $activity->activity->deliverable ?? null;
                $formatted['complexity'] = $activity->activity->complexity ?? null;
                $formatted['receive_type'] = $activity->activity->receive_type ?? null;
                $formatted['new_requirement'] = $activity->activity->new_requirement ?? false;
            } else {
                $formatted['module'] = null;
                $formatted['object'] = null;
                $formatted['deliverable'] = null;
                $formatted['complexity'] = null;
                $formatted['receive_type'] = null;
                $formatted['new_requirement'] = false;
            }

            if ($activity->children && $activity->children->isNotEmpty()) {
                foreach ($activity->children as $child) {
                    $formatted['children'][] = $this->formatActivityForHierarchy($child, $level + 1);
                }
            }
        }

        return $formatted;
    }

    private function getStatusBadgeClass($status)
    {
        $badges = [
            'not_started' => 'bg-gray-100 text-gray-800',
            'in_progress' => 'bg-blue-100 text-blue-800',
            'completed' => 'bg-green-100 text-green-800',
            'delayed' => 'bg-red-100 text-red-800',
            'on_hold' => 'bg-yellow-100 text-yellow-800',
        ];
        
        return $badges[$status] ?? $badges['not_started'];
    }

    private function calculateGroupDates($group)
    {
        $allDates = collect();
        
        if ($group->stages) {
            foreach ($group->stages as $stage) {
                if ($stage->planned_start_date) {
                    $allDates->push(['type' => 'start', 'date' => $stage->planned_start_date]);
                }
                if ($stage->planned_end_date) {
                    $allDates->push(['type' => 'end', 'date' => $stage->planned_end_date]);
                }
            }
        }
        
        if ($group->children) {
            foreach ($group->children->where('is_group', true) as $subGroup) {
                $subDates = $this->calculateGroupDates($subGroup);
                if ($subDates['start']) {
                    $allDates->push(['type' => 'start', 'date' => $subDates['start']]);
                }
                if ($subDates['end']) {
                    $allDates->push(['type' => 'end', 'date' => $subDates['end']]);
                }
            }
        }
        
        if ($allDates->isEmpty()) {
            return [
                'start' => null,
                'end' => null,
                'duration' => null
            ];
        }
        
        $startDate = $allDates->where('type', 'start')->pluck('date')->min();
        $endDate = $allDates->where('type', 'end')->pluck('date')->max();
        
        $duration = null;
        if ($startDate && $endDate) {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);
            $duration = $start->diffInDays($end) + 1;
        }
        
        return [
            'start' => $startDate ? Carbon::parse($startDate) : null,
            'end' => $endDate ? Carbon::parse($endDate) : null,
            'duration' => $duration
        ];
    }

    /**
     * Aggregate ACTUAL start/end dates for a group from its stages & sub-groups.
     * Mirrors calculateGroupDates() but reads actual_* columns.
     */
    private function calculateGroupActualDates($group)
    {
        $allDates = collect();

        if ($group->stages) {
            foreach ($group->stages as $stage) {
                if ($stage->actual_start_date) {
                    $allDates->push(['type' => 'start', 'date' => $stage->actual_start_date]);
                }
                if ($stage->actual_end_date) {
                    $allDates->push(['type' => 'end', 'date' => $stage->actual_end_date]);
                }
            }
        }

        if ($group->children) {
            foreach ($group->children->where('is_group', true) as $subGroup) {
                $subDates = $this->calculateGroupActualDates($subGroup);
                if ($subDates['start']) {
                    $allDates->push(['type' => 'start', 'date' => $subDates['start']]);
                }
                if ($subDates['end']) {
                    $allDates->push(['type' => 'end', 'date' => $subDates['end']]);
                }
            }
        }

        if ($allDates->isEmpty()) {
            return ['start' => null, 'end' => null, 'duration' => null];
        }

        $startDate = $allDates->where('type', 'start')->pluck('date')->min();
        $endDate = $allDates->where('type', 'end')->pluck('date')->max();

        $duration = null;
        if ($startDate && $endDate) {
            $duration = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1;
        }

        return [
            'start' => $startDate ? Carbon::parse($startDate) : null,
            'end' => $endDate ? Carbon::parse($endDate) : null,
            'duration' => $duration
        ];
    }

    private function calculateGroupProgress($group)
    {
        $totalWeight = 0;
        $weightedProgress = 0;

        // Stage-based activities (Phase → Group → Stage → Activity)
        if ($group->stages && $group->stages->isNotEmpty()) {
            foreach ($group->stages as $stage) {
                $weight = (float)($stage->weight ?? 0);
                $progress = (float)($stage->calculated_progress ?? $stage->progress ?? 0);

                $totalWeight += $weight;
                $weightedProgress += ($progress * $weight);
            }
        }

        // Sub-groups (nested groups)
        if ($group->children && $group->children->where('is_group', true)->isNotEmpty()) {
            foreach ($group->children->where('is_group', true) as $subGroup) {
                $weight = (float)($subGroup->calculated_weight ?? $subGroup->weight ?? 0);
                $progress = $this->calculateGroupProgress($subGroup);

                $totalWeight += $weight;
                $weightedProgress += ($progress * $weight);
            }
        }

        // Direct activities (Phase → Group → Activity, no stage)
        if ($group->directActivities && $group->directActivities->isNotEmpty()) {
            foreach ($group->directActivities as $planningActivity) {
                $weight = (float)($planningActivity->weight ?? 0);
                // Prefer the linked DeliveryProjectActivity's progress (always up-to-date)
                $progress = $planningActivity->activity
                    ? (float)($planningActivity->activity->progress_percentage ?? $planningActivity->progress_percentage ?? 0)
                    : (float)($planningActivity->progress_percentage ?? 0);

                $totalWeight += $weight;
                $weightedProgress += ($progress * $weight);
            }
        }

        if ($totalWeight == 0) {
            $allProgress = collect();

            if ($group->stages) {
                $allProgress = $allProgress->merge($group->stages->pluck('progress'));
            }
            if ($group->children) {
                foreach ($group->children->where('is_group', true) as $subGroup) {
                    $allProgress->push($this->calculateGroupProgress($subGroup));
                }
            }
            if ($group->directActivities) {
                foreach ($group->directActivities as $planningActivity) {
                    $p = $planningActivity->activity
                        ? (float)($planningActivity->activity->progress_percentage ?? $planningActivity->progress_percentage ?? 0)
                        : (float)($planningActivity->progress_percentage ?? 0);
                    $allProgress->push($p);
                }
            }

            return $allProgress->isNotEmpty() ? round($allProgress->avg(), 2) : 0;
        }

        return round($weightedProgress / $totalWeight, 2);
    }

    private function calculateGroupStatus($group)
    {
        $progress = $this->calculateGroupProgress($group);

        $allStatuses = collect();

        if ($group->stages) {
            $allStatuses = $allStatuses->merge($group->stages->pluck('status'));
        }
        if ($group->children) {
            foreach ($group->children->where('is_group', true) as $subGroup) {
                $childStatus = $this->calculateGroupStatus($subGroup);
                $allStatuses->push($childStatus['status']);
            }
        }
        // Direct activities' statuses
        if ($group->directActivities) {
            foreach ($group->directActivities as $planningActivity) {
                $s = $planningActivity->activity
                    ? ($planningActivity->activity->status ?? $planningActivity->status ?? 'not_started')
                    : ($planningActivity->status ?? 'not_started');
                $allStatuses->push($s);
            }
        }
        
        $status = 'not_started';
        $text = 'Not Started';
        $badge = 'bg-gray-100 text-gray-800';
        
        if ($progress == 0 && $allStatuses->isEmpty()) {
            $status = 'not_started';
            $text = 'Not Started';
            $badge = 'bg-gray-100 text-gray-800';
        } elseif ($progress >= 100) {
            $status = 'completed';
            $text = 'Completed';
            $badge = 'bg-green-100 text-green-800';
        } elseif ($allStatuses->contains('delayed')) {
            $status = 'delayed';
            $text = 'Delayed';
            $badge = 'bg-red-100 text-red-800';
        } elseif ($progress > 0) {
            $status = 'in_progress';
            $text = 'In Progress';
            $badge = 'bg-blue-100 text-blue-800';
        }
        
        return [
            'status' => $status,
            'text' => $text,
            'badge' => $badge
        ];
    }

    private function calculatePhaseDates($groups)
    {
        if (empty($groups)) {
            return ['start' => null, 'end' => null, 'duration' => null];
        }
        
        $allDates = collect();
        
        foreach ($groups as $group) {
            if (!empty($group['start_date']) && $group['start_date'] !== '-') {
                try {
                    $allDates->push(['type' => 'start', 'date' => Carbon::parse($group['start_date'])]);
                } catch (\Exception $e) {
                    // Skip invalid date
                }
            }
            
            if (!empty($group['end_date']) && $group['end_date'] !== '-') {
                try {
                    $allDates->push(['type' => 'end', 'date' => Carbon::parse($group['end_date'])]);
                } catch (\Exception $e) {
                    // Skip invalid date
                }
            }
        }
        
        if ($allDates->isEmpty()) {
            return ['start' => null, 'end' => null, 'duration' => null];
        }
        
        $startDate = $allDates->where('type', 'start')->pluck('date')->min();
        $endDate = $allDates->where('type', 'end')->pluck('date')->max();
        
        $duration = null;
        if ($startDate && $endDate) {
            $duration = $startDate->diffInDays($endDate) + 1;
        }
        
        return [
            'start' => $startDate,
            'end' => $endDate,
            'duration' => $duration
        ];
    }

    /**
     * Aggregate ACTUAL start/end dates for a phase from its formatted groups.
     * Mirrors calculatePhaseDates() but reads the actual_* keys.
     */
    private function calculatePhaseActualDates($groups)
    {
        if (empty($groups)) {
            return ['start' => null, 'end' => null, 'duration' => null];
        }

        $allDates = collect();

        foreach ($groups as $group) {
            if (!empty($group['actual_start_date']) && $group['actual_start_date'] !== '-') {
                try {
                    $allDates->push(['type' => 'start', 'date' => Carbon::parse($group['actual_start_date'])]);
                } catch (\Exception $e) {
                    // Skip invalid date
                }
            }

            if (!empty($group['actual_end_date']) && $group['actual_end_date'] !== '-') {
                try {
                    $allDates->push(['type' => 'end', 'date' => Carbon::parse($group['actual_end_date'])]);
                } catch (\Exception $e) {
                    // Skip invalid date
                }
            }
        }

        if ($allDates->isEmpty()) {
            return ['start' => null, 'end' => null, 'duration' => null];
        }

        $startDate = $allDates->where('type', 'start')->pluck('date')->min();
        $endDate = $allDates->where('type', 'end')->pluck('date')->max();

        return ['start' => $startDate, 'end' => $endDate, 'duration' => null];
    }

    private function calculatePhaseProgress($groups, $phaseWeight)
    {
        if (empty($groups)) {
            return 0;
        }

        $weightedProgress = 0;

        foreach ($groups as $group) {
            $weight = (float)($group['weight'] ?? 0);
            $progress = (float)($group['progress_percentage'] ?? 0);
            $weightedProgress += ($progress * $weight);
        }

        // Formula: Σ(activity_weight × activity_progress) / phase_weight
        // Divides by the configured phase weight, not sum of group weights.
        // This ensures progress reflects how much of the total planned phase scope is done.
        if ($phaseWeight > 0) {
            return round($weightedProgress / $phaseWeight, 2);
        }

        // Fallback: no configured phase weight, use sum of group weights
        $totalGroupWeight = collect($groups)->sum('weight');
        if ($totalGroupWeight > 0) {
            return round($weightedProgress / $totalGroupWeight, 2);
        }

        return 0;
    }

    private function calculatePhaseStatus($groups, $progress)
    {
        $allStatuses = collect($groups)->pluck('status');
        
        if ($progress == 0) {
            return [
                'status' => 'not_started',
                'text' => 'Not Started',
                'badge' => 'bg-gray-100 text-gray-800'
            ];
        } elseif ($progress >= 100) {
            return [
                'status' => 'completed',
                'text' => 'Completed',
                'badge' => 'bg-green-100 text-green-800'
            ];
        } elseif ($allStatuses->contains('delayed')) {
            return [
                'status' => 'delayed',
                'text' => 'Delayed',
                'badge' => 'bg-red-100 text-red-800'
            ];
        } else {
            return [
                'status' => 'in_progress',
                'text' => 'In Progress',
                'badge' => 'bg-blue-100 text-blue-800'
            ];
        }
    }

    private function formatGroupForGantt($group, &$allDates, $level = 0)
    {
        $calculatedDates = $this->calculateGroupDatesForGantt($group, $allDates);
        
        $formatted = [
            'id' => $group->id,
            'name' => $group->name,
            'start' => $calculatedDates['start'],
            'end' => $calculatedDates['end'],
            'progress' => round($group->calculated_progress ?? $group->progress_percentage ?? 0), 
            'status' => $group->status ?? 'not_started',
            'status_color' => $this->getStatusColor($group->status ?? 'not_started'),
            'custom_class' => $group->status ?? 'not_started',
            'level' => $level,
            'is_group' => true,
            'sub_groups' => [],
            'stages' => [],
        ];
        
        if ($group->children && $group->children->isNotEmpty()) {
            foreach ($group->children->where('is_group', true) as $subGroup) {
                $formatted['sub_groups'][] = $this->formatGroupForGantt($subGroup, $allDates, $level + 1);
            }
        }
        
        if ($group->stages && $group->stages->isNotEmpty()) {
            foreach ($group->stages as $stage) {
                $formatted['stages'][] = $this->formatStageForGantt($stage, $allDates);
            }
        }
        
        return $formatted;
    }

    private function calculateGroupDatesForGantt($group, &$allDates)
    {
        $dates = collect();
        
        if ($group->stages) {
            foreach ($group->stages as $stage) {
                if ($stage->planned_start_date) {
                    $this->addDate($allDates, $stage->planned_start_date);
                    $dates->push($stage->planned_start_date);
                }
                if ($stage->planned_end_date) {
                    $this->addDate($allDates, $stage->planned_end_date);
                    $dates->push($stage->planned_end_date);
                }
            }
        }
        
        if ($group->children) {
            foreach ($group->children->where('is_group', true) as $subGroup) {
                $subDates = $this->calculateGroupDatesForGantt($subGroup, $allDates);
                if ($subDates['start']) {
                    $dates->push(Carbon::parse($subDates['start']));
                }
                if ($subDates['end']) {
                    $dates->push(Carbon::parse($subDates['end']));
                }
            }
        }
        
        if ($dates->isEmpty()) {
            return [
                'start' => null,
                'end' => null,
            ];
        }
        
        $startDate = $dates->min();
        $endDate = $dates->max();
        
        return [
            'start' => $startDate ? Carbon::parse($startDate)->format('Y-m-d') : null,
            'end' => $endDate ? Carbon::parse($endDate)->format('Y-m-d') : null,
        ];
    }

    private function formatStageForGantt($stage, &$allDates)
    {
        $this->addDate($allDates, $stage->planned_start_date);
        $this->addDate($allDates, $stage->planned_end_date);
        
        $stageActivities = [];
        
        if ($stage->projectActivities) {
            foreach ($stage->projectActivities as $activity) {
                $stageActivities[] = $this->formatProjectActivityForGantt($activity, $allDates);
            }
        }
        
        return [
            'id' => $stage->id,
            'name' => $stage->name,
            'planned_start_date' => $stage->planned_start_date ? $stage->planned_start_date->format('Y-m-d') : null,
            'planned_end_date' => $stage->planned_end_date ? $stage->planned_end_date->format('Y-m-d') : null,
            'progress' => round($stage->progress ?? 0), 
            'status' => $stage->status ?? 'not_started',
            'color' => $stage->color ?? '#06b6d4',
            'activities' => $stageActivities,
        ];
    }

    private function formatProjectActivityForGantt($activity, &$allDates)
    {
        $this->addDate($allDates, $activity->start_date);
        $this->addDate($allDates, $activity->end_date);
        
        return [
            'id' => $activity->id,
            'name' => $activity->name,
            'start' => $activity->start_date ? $activity->start_date->format('Y-m-d') : null,
            'end' => $activity->end_date ? $activity->end_date->format('Y-m-d') : null,
            'progress' => round($activity->progress_percentage ?? 0),
            'status' => $activity->status ?? 'not_started',
            'status_color' => $this->getStatusColor($activity->status ?? 'not_started'),
            'custom_class' => $activity->status ?? 'not_started',
            'module' => $activity->module,
            'object' => $activity->object,
            'deliverable' => $activity->deliverable,
        ];
    }

    private function addDate(&$dateArray, $date)
    {
        if ($date) {
            $dateStr = $date instanceof Carbon ? $date->format('Y-m-d') : Carbon::parse($date)->format('Y-m-d');
            if (!in_array($dateStr, $dateArray)) {
                $dateArray[] = $dateStr;
            }
        }
    }

    private function getStatusColor($status)
    {
        $colors = [
            'not_started' => '#9ca3af',
            'in_progress' => '#3b82f6',
            'completed' => '#10b981',
            'delayed' => '#ef4444',
            'on_hold' => '#f59e0b',
        ];
        
        return $colors[$status] ?? $colors['not_started'];
    }

    private function calculatePhaseProgressFromGroups($groups)
    {
        if (empty($groups)) {
            return 0;
        }
        
        $totalWeight = 0;
        $weightedProgress = 0;
        
        foreach ($groups as $group) {
            $weight = $group['weight'] ?? 0;
            $progress = $group['progress'] ?? 0;
            
            $totalWeight += $weight;
            $weightedProgress += ($progress * $weight);
        }
        
        if ($totalWeight == 0) {
            $progressSum = 0;
            $count = 0;
            foreach ($groups as $group) {
                $progressSum += ($group['progress'] ?? 0);
                $count++;
            }
            return $count > 0 ? round($progressSum / $count) : 0;
        }
        
        return round($weightedProgress / $totalWeight);
    }

    private function collectSCurveDates($group, &$allDates, &$dataPoints)
    {
        if ($group->stages) {
            foreach ($group->stages as $stage) {
                if ($stage->planned_start_date) {
                    $allDates[] = $stage->planned_start_date->format('Y-m-d');
                }
                if ($stage->planned_end_date) {
                    $allDates[] = $stage->planned_end_date->format('Y-m-d');
                }
                
                if ($stage->actual_start_date) {
                    $allDates[] = $stage->actual_start_date->format('Y-m-d');
                }
                if ($stage->actual_end_date) {
                    $allDates[] = $stage->actual_end_date->format('Y-m-d');
                }

                $dataPoints[] = [
                    'type' => 'stage',
                    'id' => $stage->id,
                    'name' => $stage->name,
                    'weight' => $stage->weight,
                    'progress' => $stage->progress ?? 0,
                    'planned_start' => $stage->planned_start_date,
                    'planned_end' => $stage->planned_end_date,
                    'actual_start' => $stage->actual_start_date,
                    'actual_end' => $stage->actual_end_date,
                ];

                if ($stage->projectActivities) {
                    foreach ($stage->projectActivities as $activity) {
                        if ($activity->start_date) {
                            $allDates[] = $activity->start_date->format('Y-m-d');
                        }
                        if ($activity->end_date) {
                            $allDates[] = $activity->end_date->format('Y-m-d');
                        }
                        if ($activity->actual_start_date) {
                            $allDates[] = $activity->actual_start_date->format('Y-m-d');
                        }
                        if ($activity->actual_end_date) {
                            $allDates[] = $activity->actual_end_date->format('Y-m-d');
                        }

                        $dataPoints[] = [
                            'type' => 'activity',
                            'id' => $activity->id,
                            'name' => $activity->name,
                            'weight' => $activity->weight,
                            'progress' => $activity->progress_percentage ?? 0,
                            'planned_start' => $activity->start_date,
                            'planned_end' => $activity->end_date,
                            'actual_start' => $activity->actual_start_date,
                            'actual_end' => $activity->actual_end_date,
                        ];
                    }
                }
            }
        }

        if ($group->children) {
            foreach ($group->children->where('is_group', true) as $subGroup) {
                $this->collectSCurveDates($subGroup, $allDates, $dataPoints);
            }
        }
    }

    private function generateWeeklyData($startDate, $endDate, $dataPoints)
    {
        $weeklyData = [];
        $current = $startDate->copy();
        
        $totalWeight = collect($dataPoints)->sum('weight');
        
        while ($current <= $endDate) {
            $weekEnd = $current->copy()->endOfWeek();
            
            $plannedProgress = $this->calculateCumulativeProgress(
                $dataPoints, 
                $weekEnd, 
                'planned',
                $totalWeight
            );
            
            $actualProgress = $this->calculateCumulativeProgress(
                $dataPoints, 
                $weekEnd, 
                'actual',
                $totalWeight
            );
            
            $weeklyData[] = [
                'week_start' => $current->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d'),
                'week_label' => $current->format('d M'),
                'week_number' => 'W' . $current->weekOfYear,
                'date_label' => $current->format('d M'),
                'planned_cumulative' => round($plannedProgress, 2),
                'actual_cumulative' => round($actualProgress, 2),
                'variance' => round($actualProgress - $plannedProgress, 2),
            ];
            
            $current->addWeek();
        }
        
        return $weeklyData;
    }

    private function calculateCumulativeProgress($dataPoints, $targetDate, $type, $totalWeight)
    {
        if ($totalWeight == 0) return 0;
        
        $cumulativeWeight = 0;
        
        foreach ($dataPoints as $point) {
            $startDate = $type === 'planned' ? $point['planned_start'] : $point['actual_start'];
            $endDate = $type === 'planned' ? $point['planned_end'] : $point['actual_end'];
            
            if (!$startDate || !$endDate) continue;
            
            if (Carbon::parse($startDate)->lte($targetDate)) {
                $weight = $point['weight'];
                
                if (Carbon::parse($endDate)->lte($targetDate)) {
                    $cumulativeWeight += $weight;
                } else {
                    $totalDays = Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate));
                    $daysPassed = Carbon::parse($startDate)->diffInDays($targetDate);
                    
                    if ($totalDays > 0) {
                        $completion = min(($daysPassed / $totalDays) * 100, 100);
                        $cumulativeWeight += ($weight * $completion / 100);
                    }
                }
            }
        }
        
        return ($cumulativeWeight / $totalWeight) * 100;
    }

    private function calculateSCurveStatistics($dataPoints)
    {
        $total = count($dataPoints);
        $completed = 0;
        $onTrack = 0;
        $delayed = 0;
        $notStarted = 0;
        
        $totalWeight = 0;
        $completedWeight = 0;
        
        foreach ($dataPoints as $point) {
            $weight = $point['weight'];
            $totalWeight += $weight;
            
            if ($point['progress'] >= 100) {
                $completed++;
                $completedWeight += $weight;
            } elseif (!$point['actual_start']) {
                $notStarted++;
            } elseif ($point['actual_end'] && $point['planned_end']) {
                if (Carbon::parse($point['actual_end'])->gt(Carbon::parse($point['planned_end']))) {
                    $delayed++;
                } else {
                    $onTrack++;
                }
            } else {
                $onTrack++;
            }
        }
        
        return [
            'total_tasks' => $total,
            'completed' => $completed,
            'on_track' => $onTrack,
            'delayed' => $delayed,
            'not_started' => $notStarted,
            'overall_progress' => $totalWeight > 0 ? round(($completedWeight / $totalWeight) * 100, 1) : 0,
        ];
    }

    private function formatPhasesForSCurve($phases)
    {
        return $phases->map(function($phase) {
            return [
                'id' => $phase->id,
                'name' => $phase->name,
                'color' => $phase->color ?? '#6366f1',
                'weight' => $phase->weight ?? 0,
            ];
        });
    }
}