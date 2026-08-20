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
                $phaseRange = $this->calculateGanttRangeFromTasks($phaseTasks);

                $verticalGroups[] = [
                    'id' => $phase->id,
                    'name' => $phase->name,
                    'color' => $phase->color ?? '#6366f1',
                    'weight' => $phaseWeight,
                    'progress' => $phaseProgress,
                    // Rollup phase: dipakai untuk bar ringkasan di baris phase
                    'start' => $phaseRange['start'],
                    'end' => $phaseRange['end'],
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
                    $this->collectSCurveDates($group, $allDates, $dataPoints, [
                        'id' => $phase->id,
                        'name' => $phase->name,
                        'order' => $phase->order_sequence,
                    ]);
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
            $summary = $this->buildSCurveSummary($weeklyData, $dataPoints, $phases);

            return response()->json([
                'success' => true,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
                'weekly_data' => $weeklyData,
                'statistics' => $statistics,
                'summary' => $summary,
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
                    ->with(['children', 'stages', 'directActivities'])
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
            // Aktivitas tanpa stage — bentuk data Project Planning saat ini
            'directActivities' => function($q) {
                $q->orderBy('order_sequence');
            },
        ];
    }

    private function getSCurveRelations()
    {
        return [
            'children' => function($childQuery) {
                $childQuery->where('is_group', true)
                    ->with(['children', 'stages', 'directActivities'])
                    ->orderBy('order_sequence');
            },
            'stages' => function($stageQuery) {
                $stageQuery->orderBy('order_sequence')
                    ->with([
                        'projectActivities' => function($actQuery) {
                            $actQuery->orderBy('order_sequence');
                        }
                    ]);
            },
            // Aktivitas yang menempel langsung ke group (tanpa stage) —
            // ini bentuk data yang dipakai Project Planning saat ini.
            'directActivities' => function($actQuery) {
                $actQuery->orderBy('order_sequence');
            },
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
        $stageDates = $this->calculateStageDates($stage, false);
        $stageActualDates = $this->calculateStageDates($stage, true);

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
            'start_date' => $stageDates['start'] ? $stageDates['start']->format('d M Y') : '-',
            'end_date' => $stageDates['end'] ? $stageDates['end']->format('d M Y') : '-',
            'actual_start_date' => $stageActualDates['start'] ? $stageActualDates['start']->format('d M Y') : '-',
            'actual_end_date' => $stageActualDates['end'] ? $stageActualDates['end']->format('d M Y') : '-',
            'duration_in_days' => $stageDates['duration'] ?? $stage->duration_days ?? null,
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

    /**
     * Ubah nilai apa pun (Carbon|string|null) jadi Carbon, atau null kalau tidak valid.
     */
    private function toDate($value)
    {
        if (empty($value)) {
            return null;
        }

        try {
            return $value instanceof \Carbon\CarbonInterface ? $value : Carbon::parse($value);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function durationBetween($start, $end)
    {
        if (!$start || !$end) {
            return null;
        }

        return Carbon::parse($start)->diffInDays(Carbon::parse($end)) + 1;
    }

    /**
     * Ambil sumber tanggal yang BENAR-BENAR ditampilkan untuk satu activity.
     * Baris planning yang tertaut ke DeliveryProjectActivity menampilkan tanggal milik
     * activity tertaut (lihat formatActivityForHierarchy()), jadi agregasi parent
     * harus membaca sumber yang sama supaya angkanya konsisten.
     */
    private function resolveActivityDateSource($activity)
    {
        if ($activity instanceof \App\Models\DeliveryProjectActivity) {
            return $activity;
        }

        if (!empty($activity->activity_id)) {
            if (!$activity->relationLoaded('activity')) {
                $activity->load('activity');
            }
            if ($activity->activity) {
                return $activity->activity;
            }
        }

        return $activity;
    }

    /**
     * Dorong tanggal plan/actual dari sekumpulan activity ke koleksi agregasi.
     */
    private function pushActivityDates($activities, $allDates, bool $actual = false)
    {
        foreach ($activities ?? [] as $activity) {
            $source = $this->resolveActivityDateSource($activity);

            $start = $this->toDate($actual ? ($source->actual_start_date ?? null) : ($source->start_date ?? null));
            $end = $this->toDate($actual ? ($source->actual_end_date ?? null) : ($source->end_date ?? null));

            if ($start) {
                $allDates->push(['type' => 'start', 'date' => $start]);
            }
            if ($end) {
                $allDates->push(['type' => 'end', 'date' => $end]);
            }
        }
    }

    /**
     * Activity milik satu stage, mengikuti urutan prioritas formatStageWithActivities().
     */
    private function resolveStageActivities($stage)
    {
        $activities = $stage->projectActivities ?? collect();

        if ($activities->isEmpty()) {
            $activities = $stage->activities ?? collect();
        }

        return $activities;
    }

    /**
     * Tanggal STAGE = tanggal termuda (start) & tertua (end) dari activity di dalamnya.
     * Kalau stage belum punya activity, pakai tanggal yang tersimpan di stage.
     */
    private function calculateStageDates($stage, bool $actual = false)
    {
        $allDates = collect();
        $this->pushActivityDates($this->resolveStageActivities($stage), $allDates, $actual);

        if ($allDates->isEmpty()) {
            $start = $this->toDate($actual ? $stage->actual_start_date : $stage->planned_start_date);
            $end = $this->toDate($actual ? $stage->actual_end_date : $stage->planned_end_date);

            return [
                'start' => $start,
                'end' => $end,
                'duration' => $this->durationBetween($start, $end),
            ];
        }

        $start = $allDates->where('type', 'start')->pluck('date')->min();
        $end = $allDates->where('type', 'end')->pluck('date')->max();

        return [
            'start' => $start ? Carbon::parse($start) : null,
            'end' => $end ? Carbon::parse($end) : null,
            'duration' => $this->durationBetween($start, $end),
        ];
    }

    /**
     * Tanggal GROUP dihitung naik mengikuti hierarki:
     * activity langsung di bawah group + stage (yang sudah menurunkan dari activity-nya)
     * + sub-group (rekursif). Start = paling awal, End = paling akhir.
     */
    private function aggregateGroupDates($group, bool $actual = false)
    {
        $allDates = collect();

        foreach ($group->stages ?? [] as $stage) {
            $stageDates = $this->calculateStageDates($stage, $actual);
            if ($stageDates['start']) {
                $allDates->push(['type' => 'start', 'date' => $stageDates['start']]);
            }
            if ($stageDates['end']) {
                $allDates->push(['type' => 'end', 'date' => $stageDates['end']]);
            }
        }

        // Activity yang menempel langsung ke group (tanpa stage)
        $this->pushActivityDates($this->resolveDirectActivities($group), $allDates, $actual);

        if ($group->children) {
            foreach ($group->children->where('is_group', true) as $subGroup) {
                $subDates = $this->aggregateGroupDates($subGroup, $actual);
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

        return [
            'start' => $startDate ? Carbon::parse($startDate) : null,
            'end' => $endDate ? Carbon::parse($endDate) : null,
            'duration' => $this->durationBetween($startDate, $endDate),
        ];
    }

    private function calculateGroupDates($group)
    {
        return $this->aggregateGroupDates($group, false);
    }

    private function calculateGroupActualDates($group)
    {
        return $this->aggregateGroupDates($group, true);
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
            'activities' => [],
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

        foreach ($this->resolveDirectActivities($group) as $activity) {
            $formatted['activities'][] = $this->formatProjectActivityForGantt($activity, $allDates);
        }

        return $formatted;
    }

    /**
     * Aktivitas yang menggantung langsung di group (tanpa stage).
     */
    private function resolveDirectActivities($group)
    {
        if (!$group->relationLoaded('directActivities')) {
            $group->load(['directActivities' => function($q) {
                $q->orderBy('order_sequence')->with('activity');
            }]);
        }

        return $group->directActivities ?? collect();
    }

    private function calculateGroupDatesForGantt($group, &$allDates)
    {
        $dates = collect();
        
        if ($group->stages) {
            foreach ($group->stages as $stage) {
                $stageDates = $this->calculateStageDates($stage, false);
                if ($stageDates['start']) {
                    $this->addDate($allDates, $stageDates['start']);
                    $dates->push($stageDates['start']);
                }
                if ($stageDates['end']) {
                    $this->addDate($allDates, $stageDates['end']);
                    $dates->push($stageDates['end']);
                }
            }
        }
        
        foreach ($this->resolveDirectActivities($group) as $activity) {
            if ($activity->start_date) {
                $this->addDate($allDates, $activity->start_date);
                $dates->push($activity->start_date);
            }
            if ($activity->end_date) {
                $this->addDate($allDates, $activity->end_date);
                $dates->push($activity->end_date);
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
        // Tanggal stage diturunkan dari activity di dalamnya (lihat calculateStageDates)
        $stageDates = $this->calculateStageDates($stage, false);
        $this->addDate($allDates, $stageDates['start']);
        $this->addDate($allDates, $stageDates['end']);
        
        $stageActivities = [];
        
        if ($stage->projectActivities) {
            foreach ($stage->projectActivities as $activity) {
                $stageActivities[] = $this->formatProjectActivityForGantt($activity, $allDates);
            }
        }
        
        return [
            'id' => $stage->id,
            'name' => $stage->name,
            'planned_start_date' => $stageDates['start'] ? $stageDates['start']->format('Y-m-d') : null,
            'planned_end_date' => $stageDates['end'] ? $stageDates['end']->format('Y-m-d') : null,
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

    /**
     * Rentang tanggal gabungan dari sekumpulan task gantt (start/end 'Y-m-d').
     * Dipakai untuk bar ringkasan di baris PHASE: start paling awal, end paling akhir.
     */
    private function calculateGanttRangeFromTasks($tasks)
    {
        $starts = [];
        $ends = [];

        foreach ($tasks as $task) {
            if (!empty($task['start'])) {
                $starts[] = $task['start'];
            }
            if (!empty($task['end'])) {
                $ends[] = $task['end'];
            }
        }

        if (empty($starts) && empty($ends)) {
            return ['start' => null, 'end' => null];
        }

        return [
            'start' => !empty($starts) ? min($starts) : null,
            'end' => !empty($ends) ? max($ends) : null,
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

    private function collectSCurveDates($group, &$allDates, &$dataPoints, $phase = null)
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
                    'phase_id' => $phase['id'] ?? null,
                    'phase_name' => $phase['name'] ?? null,
                    'phase_order' => $phase['order'] ?? 0,
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
                            'phase_id' => $phase['id'] ?? null,
                            'phase_name' => $phase['name'] ?? null,
                            'phase_order' => $phase['order'] ?? 0,
                        ];
                    }
                }
            }
        }

        // Aktivitas langsung di bawah group (tanpa stage)
        if (!$group->relationLoaded('directActivities')) {
            $group->load(['directActivities' => function($q) {
                $q->orderBy('order_sequence');
            }]);
        }

        foreach ($group->directActivities as $activity) {
            $this->addDate($allDates, $activity->start_date);
            $this->addDate($allDates, $activity->end_date);
            $this->addDate($allDates, $activity->actual_start_date);
            $this->addDate($allDates, $activity->actual_end_date);

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
                'phase_id' => $phase['id'] ?? null,
                'phase_name' => $phase['name'] ?? null,
                'phase_order' => $phase['order'] ?? 0,
            ];
        }

        if ($group->children) {
            foreach ($group->children->where('is_group', true) as $subGroup) {
                $this->collectSCurveDates($subGroup, $allDates, $dataPoints, $phase);
            }
        }
    }

    private function generateWeeklyData($startDate, $endDate, $dataPoints)
    {
        $weeklyData = [];
        $current = $startDate->copy();

        $totalWeight = collect($dataPoints)->sum('weight');
        $weekIndex = 0;

        while ($current <= $endDate) {
            $weekIndex++;
            // Rentang selalu 7 hari penuh dari titik mulai. endOfWeek() memotong
            // minggu pertama jika tanggal mulai bukan awal minggu kalender,
            // sehingga ada hari yang tidak tercakup minggu manapun.
            $weekEnd = $current->copy()->addDays(6)->endOfDay();
            
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
                'week_index' => $weekIndex,
                'week_start' => $current->format('Y-m-d'),
                'week_end' => $weekEnd->format('Y-m-d'),
                'week_label' => $current->format('d M'),
                'week_number' => 'W' . $current->weekOfYear,
                'date_label' => $current->format('d M'),
                'planned_cumulative' => round($plannedProgress, 2),
                'actual_cumulative' => round($actualProgress, 2),
                'variance' => round($actualProgress - $plannedProgress, 2),
                'is_latest' => false,
            ];

            $current->addWeek();
        }

        return $this->markLatestWeek($weeklyData);
    }

    /**
     * Tandai minggu berjalan (minggu yang memuat hari ini). Kalau proyek belum
     * mulai -> minggu pertama; kalau sudah lewat -> minggu terakhir.
     */
    private function markLatestWeek(array $weeklyData)
    {
        if (empty($weeklyData)) {
            return $weeklyData;
        }

        $today = Carbon::now()->startOfDay();
        $latestIdx = null;

        foreach ($weeklyData as $idx => $week) {
            if ($today->between(Carbon::parse($week['week_start']), Carbon::parse($week['week_end'])->endOfDay())) {
                $latestIdx = $idx;
                break;
            }
        }

        if ($latestIdx === null) {
            $latestIdx = $today->lt(Carbon::parse($weeklyData[0]['week_start']))
                ? 0
                : count($weeklyData) - 1;
        }

        $weeklyData[$latestIdx]['is_latest'] = true;

        return $weeklyData;
    }

    /**
     * Ringkasan gaya laporan Project Progress: Plan vs Actual pada minggu berjalan,
     * deviasi, fase yang sedang berjalan, dan penyebab deviasi.
     */
    private function buildSCurveSummary(array $weeklyData, array $dataPoints, $phases)
    {
        $latest = collect($weeklyData)->firstWhere('is_latest', true) ?? collect($weeklyData)->last();

        $plan = $latest ? (float) $latest['planned_cumulative'] : 0;
        $actual = $latest ? (float) $latest['actual_cumulative'] : 0;

        return [
            'latest_week_index' => $latest['week_index'] ?? null,
            'latest_week_label' => $latest['week_label'] ?? null,
            'latest_week_start' => $latest['week_start'] ?? null,
            'plan' => round($plan, 1),
            'actual' => round($actual, 1),
            'deviation' => round($actual - $plan, 1),
            'current_phase' => $this->resolveCurrentPhase($dataPoints, $phases),
            'deviation_notes' => $this->resolveDeviationNotes($dataPoints),
        ];
    }

    /**
     * Fase berjalan = fase pertama (urut order_sequence) yang progresnya belum 100%.
     */
    private function resolveCurrentPhase(array $dataPoints, $phases)
    {
        $byPhase = [];

        foreach ($dataPoints as $point) {
            $phaseId = $point['phase_id'] ?? null;
            if (!$phaseId) continue;

            if (!isset($byPhase[$phaseId])) {
                $byPhase[$phaseId] = ['weight' => 0, 'weighted_progress' => 0];
            }

            $weight = (float) ($point['weight'] ?? 0);
            $byPhase[$phaseId]['weight'] += $weight;
            $byPhase[$phaseId]['weighted_progress'] += $weight * (float) ($point['progress'] ?? 0);
        }

        $lastNamed = null;

        foreach ($phases as $phase) {
            $bucket = $byPhase[$phase->id] ?? null;
            if (!$bucket || $bucket['weight'] <= 0) continue;

            $lastNamed = $phase->name;
            $progress = $bucket['weighted_progress'] / $bucket['weight'];

            if ($progress < 100) {
                return $phase->name;
            }
        }

        return $lastNamed ?? '-';
    }

    /**
     * Penyebab deviasi: task yang selesai terlambat atau sudah lewat jadwal
     * tapi belum 100%. Diambil maksimal 3 nama, terlama duluan.
     */
    private function resolveDeviationNotes(array $dataPoints)
    {
        $today = Carbon::now()->startOfDay();
        $late = [];

        foreach ($dataPoints as $point) {
            $plannedEnd = $point['planned_end'] ?? null;
            if (!$plannedEnd) continue;

            $plannedEnd = Carbon::parse($plannedEnd);
            $progress = (float) ($point['progress'] ?? 0);
            $actualEnd = !empty($point['actual_end']) ? Carbon::parse($point['actual_end']) : null;

            if ($actualEnd && $actualEnd->gt($plannedEnd)) {
                $late[] = ['name' => $point['name'], 'days' => $plannedEnd->diffInDays($actualEnd)];
            } elseif (!$actualEnd && $progress < 100 && $plannedEnd->lt($today)) {
                $late[] = ['name' => $point['name'], 'days' => $plannedEnd->diffInDays($today)];
            }
        }

        usort($late, fn ($a, $b) => $b['days'] <=> $a['days']);

        return collect($late)
            ->take(3)
            ->map(fn ($item) => 'Keterlambatan ' . $item['name'] . ' (' . $item['days'] . ' hari)')
            ->values()
            ->all();
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