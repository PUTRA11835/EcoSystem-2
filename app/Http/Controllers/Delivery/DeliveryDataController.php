<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\DeliveryProject;
use App\Models\DeliveryPhase;
use App\Models\DeliveryGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * DeliveryDataController
 *
 * Menyediakan data untuk berbagai view: Table, Gantt, S-Curve.
 * Simplified structure (tanpa junction table).
 */
class DeliveryDataController extends Controller
{
    /**
     * Get hierarchical data untuk table view
     */
    public function getTableData(DeliveryProject $project)
    {
        try {
            $phases = DeliveryPhase::where('delivery_projects_id', $project->id)
                ->ordered()
                ->get();

            $data = [];

            foreach ($phases as $phase) {
                $groups = $this->getPhaseGroupsHierarchical($project, $phase);

                $phaseDates = $this->calculatePhaseDates($groups);
                $phaseProgress = $this->calculatePhaseProgress($groups);
                $phaseStatus = $this->calculateStatus($phaseProgress);

                $data[] = [
                    'phase' => [
                        'id' => $phase->id,
                        'name' => $phase->name,
                        'code' => $phase->code,
                        'color' => $phase->color,
                        'weight' => $phase->weight,
                        'start_date' => $phaseDates['start']?->format('d M Y') ?? '-',
                        'end_date' => $phaseDates['end']?->format('d M Y') ?? '-',
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

        } catch (\Exception $e) {
            Log::error('Error getting delivery table data', [
                'delivery_projects_id' => $project->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load data'
            ], 500);
        }
    }

    /**
     * Get Gantt chart data
     */
    public function getGanttData(DeliveryProject $project)
    {
        try {
            $phases = DeliveryPhase::where('delivery_projects_id', $project->id)
                ->ordered()
                ->get();

            $allDates = [];
            $phasesData = [];

            foreach ($phases as $phase) {
                $groups = DeliveryGroup::where('delivery_projects_id', $project->id)
                    ->where('phase_id', $phase->id)
                    ->rootGroups()
                    ->ordered()
                    ->with([
                        'children' => function($q) {
                            $q->ordered()->with(['children', 'stages.activities', 'directActivities']);
                        },
                        'stages' => function($q) {
                            $q->ordered()->with('activities');
                        },
                        'directActivities'
                    ])
                    ->get();

                $phaseTasks = [];

                foreach ($groups as $group) {
                    $phaseTasks[] = $this->formatGroupForGantt($group, $allDates);
                }

                $phaseProgress = $this->calculatePhaseProgressFromGroups($phaseTasks);

                $phasesData[] = [
                    'id' => $phase->id,
                    'name' => $phase->name,
                    'color' => $phase->color,
                    'weight' => $phase->weight,
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
                'phases' => $phasesData,
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting gantt data', [
                'delivery_projects_id' => $project->id,
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
    public function getSCurveData(DeliveryProject $project)
    {
        try {
            $phases = DeliveryPhase::where('delivery_projects_id', $project->id)
                ->ordered()
                ->get();

            $allDates = [];
            $dataPoints = [];

            foreach ($phases as $phase) {
                $groups = DeliveryGroup::where('delivery_projects_id', $project->id)
                    ->where('phase_id', $phase->id)
                    ->rootGroups()
                    ->ordered()
                    ->with([
                        'children' => function($q) {
                            $q->ordered()->with(['children', 'stages.activities', 'directActivities']);
                        },
                        'stages' => function($q) {
                            $q->ordered()->with('activities');
                        },
                        'directActivities'
                    ])
                    ->get();

                foreach ($groups as $group) {
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
                'phases' => $phases->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'color' => $p->color,
                    'weight' => $p->weight,
                ]),
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting S-curve data', [
                'delivery_projects_id' => $project->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load S-curve data'
            ], 500);
        }
    }

    /**
     * Get project summary statistics
     */
    public function getProjectSummary(DeliveryProject $project)
    {
        try {
            $phases = DeliveryPhase::where('delivery_projects_id', $project->id)->get();

            $totalGroups = DeliveryGroup::where('delivery_projects_id', $project->id)->count();
            $totalStages = DB::table('delivery_stages')
                ->where('delivery_projects_id', $project->id)
                ->whereNull('deleted_at')
                ->count();
            $totalActivities = DB::table('delivery_activities')
                ->where('delivery_projects_id', $project->id)
                ->whereNull('deleted_at')
                ->count();

            $activityStats = DB::table('delivery_activities')
                ->where('delivery_projects_id', $project->id)
                ->whereNull('deleted_at')
                ->selectRaw('
                    COUNT(*) as total,
                    SUM(CASE WHEN status = "not_started" THEN 1 ELSE 0 END) as not_started,
                    SUM(CASE WHEN status = "in_progress" THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = "delayed" THEN 1 ELSE 0 END) as delayed,
                    SUM(CASE WHEN status = "on_hold" THEN 1 ELSE 0 END) as on_hold,
                    AVG(progress_percentage) as avg_progress
                ')
                ->first();

            $overallProgress = 0;
            $totalWeight = $phases->sum('weight');

            if ($totalWeight > 0) {
                foreach ($phases as $phase) {
                    $overallProgress += ($phase->progress_percentage * $phase->weight) / $totalWeight;
                }
            } else {
                $overallProgress = $phases->avg('progress_percentage') ?? 0;
            }

            $dateRange = DB::table('delivery_activities')
                ->where('delivery_projects_id', $project->id)
                ->whereNull('deleted_at')
                ->selectRaw('MIN(planned_start_date) as earliest_start, MAX(planned_end_date) as latest_end')
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'phases_count' => $phases->count(),
                    'groups_count' => $totalGroups,
                    'stages_count' => $totalStages,
                    'activities_count' => $totalActivities,
                    'overall_progress' => round($overallProgress, 2),
                    'activity_stats' => [
                        'not_started' => $activityStats->not_started ?? 0,
                        'in_progress' => $activityStats->in_progress ?? 0,
                        'completed' => $activityStats->completed ?? 0,
                        'delayed' => $activityStats->delayed ?? 0,
                        'on_hold' => $activityStats->on_hold ?? 0,
                    ],
                    'date_range' => [
                        'start' => $dateRange->earliest_start,
                        'end' => $dateRange->latest_end,
                    ],
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting project summary', [
                'delivery_projects_id' => $project->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load project summary'
            ], 500);
        }
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Get phase groups hierarchically
     */
    protected function getPhaseGroupsHierarchical(DeliveryProject $project, DeliveryPhase $phase): array
    {
        $groups = DeliveryGroup::where('delivery_projects_id', $project->id)
            ->where('phase_id', $phase->id)
            ->rootGroups()
            ->ordered()
            ->with([
                'children' => function($q) {
                    $q->ordered()->with(['children', 'stages.activities', 'directActivities']);
                },
                'stages' => function($q) {
                    $q->ordered()->with('activities');
                },
                'directActivities'
            ])
            ->get();

        $result = [];
        foreach ($groups as $group) {
            $result[] = $this->formatGroupRecursive($group);
        }

        return $result;
    }

    /**
     * Format group recursively for table
     */
    protected function formatGroupRecursive(DeliveryGroup $group, int $level = 0): array
    {
        $calculatedDates = $this->calculateGroupDates($group);
        $calculatedProgress = $this->calculateGroupProgress($group);
        $calculatedStatus = $this->calculateStatus($calculatedProgress);

        $formatted = [
            'id' => $group->id,
            'type' => 'group',
            'name' => $group->name,
            'code' => $group->code,
            'is_group' => true,
            'phase_id' => $group->phase_id,
            'parent_id' => $group->parent_id,
            'level' => $level,
            'weight' => $group->weight,
            'progress_percentage' => $calculatedProgress,
            'status' => $calculatedStatus['status'],
            'status_text' => $calculatedStatus['text'],
            'status_badge' => $calculatedStatus['badge'],
            'start_date' => $calculatedDates['start']?->format('d M Y') ?? '-',
            'end_date' => $calculatedDates['end']?->format('d M Y') ?? '-',
            'duration_in_days' => $calculatedDates['duration'],
            'notes' => $group->notes,
            'sub_groups' => [],
            'stages' => [],
            'direct_activities' => [],
        ];

        if ($group->relationLoaded('children') && $group->children->isNotEmpty()) {
            foreach ($group->children as $subGroup) {
                $formatted['sub_groups'][] = $this->formatGroupRecursive($subGroup, $level + 1);
            }
        }

        if ($group->relationLoaded('stages') && $group->stages->isNotEmpty()) {
            foreach ($group->stages as $stage) {
                $formatted['stages'][] = $this->formatStageWithActivities($stage);
            }
        }

        if ($group->relationLoaded('directActivities') && $group->directActivities->isNotEmpty()) {
            foreach ($group->directActivities as $activity) {
                $formatted['direct_activities'][] = $this->formatActivityForTable($activity);
            }
        }

        return $formatted;
    }

    /**
     * Format stage with activities for table
     */
    protected function formatStageWithActivities($stage): array
    {
        $stageData = [
            'id' => $stage->id,
            'type' => 'stage',
            'name' => $stage->name,
            'code' => $stage->code,
            'description' => $stage->description,
            'weight' => $stage->weight,
            'progress_percentage' => $stage->progress_percentage,
            'status' => $stage->status,
            'status_text' => $stage->status_text,
            'status_badge' => $stage->status_badge,
            'start_date' => $stage->planned_start_date?->format('d M Y') ?? '-',
            'end_date' => $stage->planned_end_date?->format('d M Y') ?? '-',
            'duration_in_days' => $stage->duration_in_days,
            'color' => $stage->display_color,
            'activities' => []
        ];

        if ($stage->relationLoaded('activities') && $stage->activities->isNotEmpty()) {
            foreach ($stage->activities as $activity) {
                $stageData['activities'][] = $this->formatActivityForTable($activity);
            }
        }

        return $stageData;
    }

    /**
     * Format activity for table
     */
    protected function formatActivityForTable($activity): array
    {
        return [
            'id' => $activity->id,
            'type' => 'activity',
            'parent_type' => $activity->parent_type,
            'stage_id' => $activity->stage_id,
            'group_id' => $activity->group_id,
            'name' => $activity->name,
            'code' => $activity->code,
            'weight' => $activity->weight,
            'progress_percentage' => $activity->progress_percentage,
            'status' => $activity->status,
            'status_text' => $activity->status_text,
            'status_badge' => $activity->status_badge,
            'start_date' => $activity->planned_start_date?->format('d M Y') ?? '-',
            'end_date' => $activity->planned_end_date?->format('d M Y') ?? '-',
            'duration_in_days' => $activity->duration_in_days,
            'is_overdue' => $activity->is_overdue,
        ];
    }

    /**
     * Format group for Gantt
     */
    protected function formatGroupForGantt(DeliveryGroup $group, array &$allDates, int $level = 0): array
    {
        $calculatedDates = $this->calculateGroupDatesForGantt($group, $allDates);

        $formatted = [
            'id' => $group->id,
            'name' => $group->name,
            'start' => $calculatedDates['start'],
            'end' => $calculatedDates['end'],
            'progress' => round($group->progress_percentage),
            'status' => $group->status,
            'status_color' => $this->getStatusColor($group->status),
            'level' => $level,
            'is_group' => true,
            'sub_groups' => [],
            'stages' => [],
            'direct_activities' => [],
        ];

        if ($group->relationLoaded('children') && $group->children->isNotEmpty()) {
            foreach ($group->children as $subGroup) {
                $formatted['sub_groups'][] = $this->formatGroupForGantt($subGroup, $allDates, $level + 1);
            }
        }

        if ($group->relationLoaded('stages') && $group->stages->isNotEmpty()) {
            foreach ($group->stages as $stage) {
                $formatted['stages'][] = $this->formatStageForGantt($stage, $allDates);
            }
        }

        if ($group->relationLoaded('directActivities') && $group->directActivities->isNotEmpty()) {
            foreach ($group->directActivities as $activity) {
                $formatted['direct_activities'][] = $this->formatActivityForGantt($activity, $allDates);
            }
        }

        return $formatted;
    }

    /**
     * Format stage for Gantt
     */
    protected function formatStageForGantt($stage, array &$allDates): array
    {
        $this->addDate($allDates, $stage->planned_start_date);
        $this->addDate($allDates, $stage->planned_end_date);

        $activities = [];
        if ($stage->relationLoaded('activities')) {
            foreach ($stage->activities as $activity) {
                $activities[] = $this->formatActivityForGantt($activity, $allDates);
            }
        }

        return [
            'id' => $stage->id,
            'name' => $stage->name,
            'planned_start_date' => $stage->planned_start_date?->format('Y-m-d'),
            'planned_end_date' => $stage->planned_end_date?->format('Y-m-d'),
            'progress' => round($stage->progress_percentage),
            'status' => $stage->status,
            'color' => $stage->display_color,
            'activities' => $activities,
        ];
    }

    /**
     * Format activity for Gantt
     */
    protected function formatActivityForGantt($activity, array &$allDates): array
    {
        $this->addDate($allDates, $activity->planned_start_date);
        $this->addDate($allDates, $activity->planned_end_date);

        return [
            'id' => $activity->id,
            'name' => $activity->name,
            'start' => $activity->planned_start_date?->format('Y-m-d'),
            'end' => $activity->planned_end_date?->format('Y-m-d'),
            'progress' => round($activity->progress_percentage),
            'status' => $activity->status,
            'status_color' => $this->getStatusColor($activity->status),
        ];
    }

    /**
     * Calculate group dates
     */
    protected function calculateGroupDates(DeliveryGroup $group): array
    {
        $allDates = collect();

        if ($group->relationLoaded('stages')) {
            foreach ($group->stages as $stage) {
                if ($stage->planned_start_date) {
                    $allDates->push(['type' => 'start', 'date' => $stage->planned_start_date]);
                }
                if ($stage->planned_end_date) {
                    $allDates->push(['type' => 'end', 'date' => $stage->planned_end_date]);
                }
            }
        }

        if ($group->relationLoaded('directActivities')) {
            foreach ($group->directActivities as $activity) {
                if ($activity->planned_start_date) {
                    $allDates->push(['type' => 'start', 'date' => $activity->planned_start_date]);
                }
                if ($activity->planned_end_date) {
                    $allDates->push(['type' => 'end', 'date' => $activity->planned_end_date]);
                }
            }
        }

        if ($group->relationLoaded('children')) {
            foreach ($group->children as $subGroup) {
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
            return ['start' => null, 'end' => null, 'duration' => null];
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
     * Calculate group dates for Gantt
     */
    protected function calculateGroupDatesForGantt(DeliveryGroup $group, array &$allDates): array
    {
        $dates = collect();

        if ($group->relationLoaded('stages')) {
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

        if ($group->relationLoaded('directActivities')) {
            foreach ($group->directActivities as $activity) {
                if ($activity->planned_start_date) {
                    $this->addDate($allDates, $activity->planned_start_date);
                    $dates->push($activity->planned_start_date);
                }
                if ($activity->planned_end_date) {
                    $this->addDate($allDates, $activity->planned_end_date);
                    $dates->push($activity->planned_end_date);
                }
            }
        }

        if ($group->relationLoaded('children')) {
            foreach ($group->children as $subGroup) {
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
            return ['start' => null, 'end' => null];
        }

        $startDate = $dates->min();
        $endDate = $dates->max();

        return [
            'start' => $startDate ? Carbon::parse($startDate)->format('Y-m-d') : null,
            'end' => $endDate ? Carbon::parse($endDate)->format('Y-m-d') : null,
        ];
    }

    /**
     * Calculate group progress
     */
    protected function calculateGroupProgress(DeliveryGroup $group): float
    {
        $totalWeight = 0;
        $weightedProgress = 0;

        if ($group->relationLoaded('stages') && $group->stages->isNotEmpty()) {
            foreach ($group->stages as $stage) {
                $weight = $stage->weight ?? 0;
                $progress = $stage->progress_percentage ?? 0;
                $totalWeight += $weight;
                $weightedProgress += ($progress * $weight);
            }
        }

        if ($group->relationLoaded('directActivities') && $group->directActivities->isNotEmpty()) {
            foreach ($group->directActivities as $activity) {
                $weight = $activity->weight ?? 0;
                $progress = $activity->progress_percentage ?? 0;
                $totalWeight += $weight;
                $weightedProgress += ($progress * $weight);
            }
        }

        if ($group->relationLoaded('children') && $group->children->isNotEmpty()) {
            foreach ($group->children as $subGroup) {
                $weight = $subGroup->weight ?? 0;
                $progress = $this->calculateGroupProgress($subGroup);
                $totalWeight += $weight;
                $weightedProgress += ($progress * $weight);
            }
        }

        if ($totalWeight == 0) {
            $allProgress = collect();

            if ($group->relationLoaded('stages')) {
                $allProgress = $allProgress->merge($group->stages->pluck('progress_percentage'));
            }
            if ($group->relationLoaded('directActivities')) {
                $allProgress = $allProgress->merge($group->directActivities->pluck('progress_percentage'));
            }
            if ($group->relationLoaded('children')) {
                foreach ($group->children as $subGroup) {
                    $allProgress->push($this->calculateGroupProgress($subGroup));
                }
            }

            return $allProgress->isNotEmpty() ? round($allProgress->avg(), 2) : 0;
        }

        return round($weightedProgress / $totalWeight, 2);
    }

    /**
     * Calculate phase dates from groups
     */
    protected function calculatePhaseDates(array $groups): array
    {
        if (empty($groups)) {
            return ['start' => null, 'end' => null, 'duration' => null];
        }

        $allDates = collect();

        foreach ($groups as $group) {
            if (!empty($group['start_date']) && $group['start_date'] !== '-') {
                try {
                    $allDates->push(['type' => 'start', 'date' => Carbon::parse($group['start_date'])]);
                } catch (\Exception $e) {}
            }

            if (!empty($group['end_date']) && $group['end_date'] !== '-') {
                try {
                    $allDates->push(['type' => 'end', 'date' => Carbon::parse($group['end_date'])]);
                } catch (\Exception $e) {}
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
     * Calculate phase progress from groups
     */
    protected function calculatePhaseProgress(array $groups): float
    {
        if (empty($groups)) return 0;

        $totalWeight = 0;
        $weightedProgress = 0;

        foreach ($groups as $group) {
            $weight = $group['weight'] ?? 0;
            $progress = $group['progress_percentage'] ?? 0;
            $totalWeight += $weight;
            $weightedProgress += ($progress * $weight);
        }

        if ($totalWeight == 0) {
            return round(collect($groups)->avg('progress_percentage') ?? 0, 2);
        }

        return round($weightedProgress / $totalWeight, 2);
    }

    /**
     * Calculate phase progress from Gantt groups
     */
    protected function calculatePhaseProgressFromGroups(array $groups): float
    {
        if (empty($groups)) return 0;

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

    /**
     * Calculate status based on progress
     */
    protected function calculateStatus(float $progress): array
    {
        if ($progress >= 100) {
            return [
                'status' => 'completed',
                'text' => 'Completed',
                'badge' => 'bg-green-100 text-green-800'
            ];
        } elseif ($progress > 0) {
            return [
                'status' => 'in_progress',
                'text' => 'In Progress',
                'badge' => 'bg-blue-100 text-blue-800'
            ];
        }

        return [
            'status' => 'not_started',
            'text' => 'Not Started',
            'badge' => 'bg-gray-100 text-gray-800'
        ];
    }

    /**
     * Get status color
     */
    protected function getStatusColor(string $status): string
    {
        return match ($status) {
            'completed' => '#10b981',
            'in_progress' => '#3b82f6',
            'delayed' => '#ef4444',
            'on_hold' => '#f59e0b',
            default => '#9ca3af',
        };
    }

    /**
     * Add date to array
     */
    protected function addDate(array &$dateArray, $date): void
    {
        if ($date) {
            $dateStr = $date instanceof Carbon ? $date->format('Y-m-d') : Carbon::parse($date)->format('Y-m-d');
            if (!in_array($dateStr, $dateArray)) {
                $dateArray[] = $dateStr;
            }
        }
    }

    /**
     * Collect S-Curve dates
     */
    protected function collectSCurveDates(DeliveryGroup $group, array &$allDates, array &$dataPoints): void
    {
        if ($group->relationLoaded('stages')) {
            foreach ($group->stages as $stage) {
                $this->addDate($allDates, $stage->planned_start_date);
                $this->addDate($allDates, $stage->planned_end_date);
                $this->addDate($allDates, $stage->actual_start_date);
                $this->addDate($allDates, $stage->actual_end_date);

                if ($stage->relationLoaded('activities')) {
                    foreach ($stage->activities as $activity) {
                        $this->collectActivityDataPoint($activity, $allDates, $dataPoints);
                    }
                }
            }
        }

        if ($group->relationLoaded('directActivities')) {
            foreach ($group->directActivities as $activity) {
                $this->collectActivityDataPoint($activity, $allDates, $dataPoints);
            }
        }

        if ($group->relationLoaded('children')) {
            foreach ($group->children as $subGroup) {
                $this->collectSCurveDates($subGroup, $allDates, $dataPoints);
            }
        }
    }

    /**
     * Collect activity data point for S-Curve
     */
    protected function collectActivityDataPoint($activity, array &$allDates, array &$dataPoints): void
    {
        $this->addDate($allDates, $activity->planned_start_date);
        $this->addDate($allDates, $activity->planned_end_date);
        $this->addDate($allDates, $activity->actual_start_date);
        $this->addDate($allDates, $activity->actual_end_date);

        $dataPoints[] = [
            'type' => 'activity',
            'id' => $activity->id,
            'name' => $activity->name,
            'weight' => $activity->weight,
            'progress' => $activity->progress_percentage ?? 0,
            'planned_start' => $activity->planned_start_date,
            'planned_end' => $activity->planned_end_date,
            'actual_start' => $activity->actual_start_date,
            'actual_end' => $activity->actual_end_date,
        ];
    }

    /**
     * Generate weekly data for S-Curve
     */
    protected function generateWeeklyData(Carbon $startDate, Carbon $endDate, array $dataPoints): array
    {
        $weeklyData = [];
        $current = $startDate->copy();
        $totalWeight = collect($dataPoints)->sum('weight');

        while ($current <= $endDate) {
            $weekEnd = $current->copy()->endOfWeek();

            $plannedProgress = $this->calculateCumulativeProgress($dataPoints, $weekEnd, 'planned', $totalWeight);
            $actualProgress = $this->calculateCumulativeProgress($dataPoints, $weekEnd, 'actual', $totalWeight);

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

    /**
     * Calculate cumulative progress for S-Curve
     */
    protected function calculateCumulativeProgress(array $dataPoints, Carbon $targetDate, string $type, float $totalWeight): float
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

    /**
     * Calculate S-Curve statistics
     */
    protected function calculateSCurveStatistics(array $dataPoints): array
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
}
