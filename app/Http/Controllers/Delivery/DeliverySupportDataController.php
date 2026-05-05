<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\DeliverySupport;
use App\Models\DeliverySupportPhase;
use App\Models\DeliverySupportActivity;
use App\Models\DeliverySupportPlanning;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * DeliverySupportDataController
 *
 * Provides data endpoints for table, gantt, and s-curve views
 * Format compatible with project planning views
 */
class DeliverySupportDataController extends Controller
{
    /**
     * Get table view data (hierarchical format like project)
     */
    public function getTableData(DeliverySupport $support, Request $request)
    {
        try {
            $phaseId = $request->get('phase_id');

            // Get phases
            $phasesQuery = DeliverySupportPhase::where('delivery_support_id', $support->id)
                ->where('is_active', true)
                ->orderBy('order_sequence');

            if ($phaseId) {
                $phasesQuery->where('id', $phaseId);
            }

            $phases = $phasesQuery->get();

            $tableData = [];

            foreach ($phases as $phase) {
                $groups = $this->getPhaseGroupsHierarchical($support, $phase);

                $phaseDates = $this->calculatePhaseDates($groups);
                $phaseProgress = $this->calculatePhaseProgressFromGroups($groups);
                $phaseStatus = $this->calculatePhaseStatus($groups, $phaseProgress);

                $tableData[] = [
                    'phase' => [
                        'id' => $phase->id,
                        'name' => $phase->name,
                        'color' => $phase->color ?? '#6366f1',
                        'orientation' => $phase->orientation ?? 'vertical',
                        'weight' => $phase->weight ?? 0,
                        'start_date' => $phaseDates['start'] ? $phaseDates['start']->format('d M Y') : '-',
                        'end_date' => $phaseDates['end'] ? $phaseDates['end']->format('d M Y') : '-',
                        'duration_in_days' => $phaseDates['duration'],
                        'progress' => $phaseProgress,
                        'status' => $phaseStatus['status'],
                        'status_text' => $phaseStatus['text'],
                        'status_badge' => $phaseStatus['badge'],
                    ],
                    'groups' => $groups
                ];
            }

            // Return array directly like project controller
            return response()->json($tableData);

        } catch (\Exception $e) {
            Log::error('Error getting table data', [
                'support_id' => $support->id,
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load table data'
            ], 500);
        }
    }

    /**
     * Get Gantt data (format like project)
     */
    public function getGanttData(DeliverySupport $support, Request $request)
    {
        try {
            $phaseId = $request->get('phase_id');

            // Get phases
            $phasesQuery = DeliverySupportPhase::where('delivery_support_id', $support->id)
                ->where('is_active', true)
                ->orderBy('order_sequence');

            if ($phaseId) {
                $phasesQuery->where('id', $phaseId);
            }

            $phases = $phasesQuery->get();

            $allDates = [];
            $verticalGroups = [];

            foreach ($phases as $phase) {
                $phaseTasks = $this->getPhaseGanttTasks($support, $phase, $allDates);

                $phaseProgress = $this->calculatePhaseProgressFromGanttTasks($phaseTasks);

                $verticalGroups[] = [
                    'id' => $phase->id,
                    'name' => $phase->name,
                    'color' => $phase->color ?? '#6366f1',
                    'weight' => $phase->weight ?? 0,
                    'progress' => $phaseProgress,
                    'tasks' => $phaseTasks,
                ];
            }

            // Calculate date range
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
                'support_id' => $support->id,
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load gantt data'
            ], 500);
        }
    }

    /**
     * Get S-Curve data (format like project)
     */
    public function getSCurveData(DeliverySupport $support, Request $request)
    {
        try {
            // Collect all data points from activities
            $phases = DeliverySupportPhase::where('delivery_support_id', $support->id)
                ->where('is_active', true)
                ->orderBy('order_sequence')
                ->get();

            $allDates = [];
            $dataPoints = [];

            foreach ($phases as $phase) {
                $activities = DeliverySupportActivity::where('delivery_support_id', $support->id)
                    ->where('delivery_support_phase_id', $phase->id)
                    ->with(['stages'])
                    ->orderBy('order_sequence')
                    ->get();

                foreach ($activities as $activity) {
                    $this->collectActivityDates($activity, $allDates, $dataPoints);
                }
            }

            // Calculate date range
            if (empty($allDates)) {
                $startDate = Carbon::now()->startOfMonth();
                $endDate = Carbon::now()->addMonths(3)->endOfMonth();
            } else {
                sort($allDates);
                $startDate = Carbon::parse($allDates[0])->startOfWeek();
                $endDate = Carbon::parse($allDates[count($allDates) - 1])->endOfWeek();
            }

            // Generate weekly data
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
            Log::error('Error getting s-curve data', [
                'support_id' => $support->id,
                'error' => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load s-curve data'
            ], 500);
        }
    }

    /**
     * Get support summary
     */
    public function getSummary(DeliverySupport $support)
    {
        try {
            $activities = DeliverySupportActivity::where('delivery_support_id', $support->id)->get();

            $summary = [
                'total_activities' => $activities->count(),
                'completed_activities' => $activities->where('status', 'completed')->count(),
                'in_progress_activities' => $activities->where('status', 'in_progress')->count(),
                'not_started_activities' => $activities->where('status', 'not_started')->count(),
                'delayed_activities' => $activities->where('status', 'delayed')->count(),
                'overall_progress' => $support->calculated_progress ?? 0,
                'start_date' => $support->start_date?->format('Y-m-d'),
                'end_date' => $support->end_date?->format('Y-m-d'),
                'resolution_estimated' => $support->resolution_estimated?->format('Y-m-d'),
                'days_remaining' => $support->end_date
                    ? max(0, now()->diffInDays($support->end_date, false))
                    : null,
                'is_overdue' => $support->end_date && $support->end_date->lt(now()),
            ];

            // Phase breakdown
            $phases = DeliverySupportPhase::where('delivery_support_id', $support->id)
                ->where('is_active', true)
                ->orderBy('order_sequence')
                ->get();

            $phaseBreakdown = [];
            foreach ($phases as $phase) {
                $phaseActivities = $activities->where('delivery_support_phase_id', $phase->id);
                $phaseBreakdown[] = [
                    'id' => $phase->id,
                    'name' => $phase->name,
                    'color' => $phase->color,
                    'weight' => $phase->weight,
                    'activities_count' => $phaseActivities->count(),
                    'progress' => $this->calculatePhaseProgress($phaseActivities),
                ];
            }

            $summary['phases'] = $phaseBreakdown;

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting support summary', [
                'support_id' => $support->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load summary'
            ], 500);
        }
    }

    // =========================================================================
    // HIERARCHICAL DATA METHODS
    // =========================================================================

    /**
     * Get phase groups in hierarchical format for table view
     * Groups come from DeliverySupportPlanning table (is_group = true)
     * Activities are fetched under each group
     */
    protected function getPhaseGroupsHierarchical(DeliverySupport $support, DeliverySupportPhase $phase)
    {
        // Get groups from planning table (is_group = true, parent_id = null for top-level)
        $planningGroups = DeliverySupportPlanning::where('delivery_support_id', $support->id)
            ->where('phase_id', $phase->id)
            ->where('is_group', true)
            ->whereNull('parent_id')
            ->orderBy('order_sequence')
            ->get();

        $groups = [];

        foreach ($planningGroups as $planningGroup) {
            $groups[] = $this->formatPlanningGroupHierarchical($support, $phase, $planningGroup, 0);
        }

        // Also get activities that are directly under the phase (no group)
        $directActivities = DeliverySupportActivity::where('delivery_support_id', $support->id)
            ->where('delivery_support_phase_id', $phase->id)
            ->whereNotIn('id', function ($query) use ($support) {
                $query->select('activity_id')
                    ->from('delivery_support_planning')
                    ->where('delivery_support_id', $support->id)
                    ->whereNotNull('activity_id');
            })
            ->with(['stages', 'employees.basicData'])
            ->orderBy('order_sequence')
            ->get();

        foreach ($directActivities as $activity) {
            $groups[] = $this->formatActivityAsGroup($activity);
        }

        return $groups;
    }

    /**
     * Format planning group with hierarchy (sub-groups and activities)
     */
    protected function formatPlanningGroupHierarchical(DeliverySupport $support, DeliverySupportPhase $phase, DeliverySupportPlanning $group, $level)
    {
        // Get sub-groups (children that are groups)
        $subGroups = DeliverySupportPlanning::where('delivery_support_id', $support->id)
            ->where('parent_id', $group->id)
            ->where('is_group', true)
            ->orderBy('order_sequence')
            ->get();

        $formattedSubGroups = [];
        foreach ($subGroups as $subGroup) {
            $formattedSubGroups[] = $this->formatPlanningGroupHierarchical($support, $phase, $subGroup, $level + 1);
        }

        // Get activities under this group (from planning table)
        $activityPlannings = DeliverySupportPlanning::where('delivery_support_id', $support->id)
            ->where('parent_id', $group->id)
            ->where('is_group', false)
            ->whereNotNull('activity_id')
            ->with(['activity.stages', 'activity.employees.basicData'])
            ->orderBy('order_sequence')
            ->get();

        $activities = [];
        foreach ($activityPlannings as $actPlanning) {
            if ($actPlanning->activity) {
                $activities[] = $this->formatActivityForTable($actPlanning->activity);
            }
        }

        // Calculate dates from children
        $allChildren = array_merge($formattedSubGroups, $activities);
        $groupDates = $this->calculateGroupDates($allChildren, $group);
        $groupProgress = $this->calculateGroupProgressFromChildren($allChildren, $group);
        $groupStatus = $this->calculateGroupStatus($allChildren, $groupProgress, $group);

        return [
            'id' => $group->id,
            'type' => 'group',
            'name' => $group->name ?? $group->group_name ?? 'Unnamed Group',
            'is_group' => true,
            'phase_id' => $phase->id,
            'parent_id' => $group->parent_id,
            'level' => $level,
            'weight' => $group->weight ?? 0,
            'progress_percentage' => $groupProgress,
            'status' => $groupStatus['status'],
            'status_text' => $groupStatus['text'],
            'status_badge' => $groupStatus['badge'],
            'start_date' => $groupDates['start'] ? $groupDates['start']->format('d M Y') : '-',
            'end_date' => $groupDates['end'] ? $groupDates['end']->format('d M Y') : '-',
            'duration_in_days' => $groupDates['duration'],
            'notes' => $group->notes ?? null,
            'sub_groups' => $formattedSubGroups,
            'activities' => $activities,
            'stages' => []
        ];
    }

    /**
     * Format activity for table display
     */
    protected function formatActivityForTable($activity)
    {
        $calculatedDates = $this->calculateActivityDates($activity);
        $calculatedProgress = $activity->progress_percentage ?? 0;
        $calculatedStatus = $this->calculateActivityStatus($activity);

        $stages = [];
        if ($activity->stages && $activity->stages->isNotEmpty()) {
            foreach ($activity->stages as $stage) {
                $stages[] = $this->formatStageWithActivities($stage);
            }
        }

        return [
            'id' => $activity->id,
            'type' => 'activity',
            'name' => $activity->name,
            'is_group' => false,
            'phase_id' => $activity->delivery_support_phase_id,
            'parent_id' => null,
            'level' => 0,
            'weight' => $activity->weight ?? 0,
            'progress_percentage' => $calculatedProgress,
            'status' => $calculatedStatus['status'],
            'status_text' => $calculatedStatus['text'],
            'status_badge' => $calculatedStatus['badge'],
            'start_date' => $calculatedDates['start'] ? $calculatedDates['start']->format('d M Y') : '-',
            'end_date' => $calculatedDates['end'] ? $calculatedDates['end']->format('d M Y') : '-',
            'duration_in_days' => $calculatedDates['duration'],
            'notes' => $activity->notes ?? null,
            'module' => $activity->module,
            'object' => $activity->object,
            'deliverable' => $activity->deliverable,
            'complexity' => $activity->complexity,
            'incident_type' => $activity->incident_type ?? null,
            'stages' => $stages
        ];
    }

    /**
     * Calculate group dates from children
     */
    protected function calculateGroupDates($children, $group)
    {
        // If group has its own dates, use them
        if ($group->start_date || $group->end_date) {
            $duration = null;
            if ($group->start_date && $group->end_date) {
                $duration = Carbon::parse($group->start_date)->diffInDays(Carbon::parse($group->end_date)) + 1;
            }
            return [
                'start' => $group->start_date ? Carbon::parse($group->start_date) : null,
                'end' => $group->end_date ? Carbon::parse($group->end_date) : null,
                'duration' => $duration
            ];
        }

        // Otherwise calculate from children
        if (empty($children)) {
            return ['start' => null, 'end' => null, 'duration' => null];
        }

        $allDates = collect();
        foreach ($children as $child) {
            if (!empty($child['start_date']) && $child['start_date'] !== '-') {
                try {
                    $allDates->push(['type' => 'start', 'date' => Carbon::parse($child['start_date'])]);
                } catch (\Exception $e) {}
            }
            if (!empty($child['end_date']) && $child['end_date'] !== '-') {
                try {
                    $allDates->push(['type' => 'end', 'date' => Carbon::parse($child['end_date'])]);
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

        return ['start' => $startDate, 'end' => $endDate, 'duration' => $duration];
    }

    /**
     * Calculate group progress from children
     */
    protected function calculateGroupProgressFromChildren($children, $group)
    {
        // If group has its own progress set, use it
        if ($group->progress_percentage > 0) {
            return $group->progress_percentage;
        }

        if (empty($children)) {
            return 0;
        }

        $totalWeight = 0;
        $weightedProgress = 0;

        foreach ($children as $child) {
            $weight = $child['weight'] ?? 1;
            $progress = $child['progress_percentage'] ?? 0;

            $totalWeight += $weight;
            $weightedProgress += ($progress * $weight);
        }

        if ($totalWeight == 0) {
            $sum = 0;
            foreach ($children as $child) {
                $sum += ($child['progress_percentage'] ?? 0);
            }
            return count($children) > 0 ? round($sum / count($children), 2) : 0;
        }

        return round($weightedProgress / $totalWeight, 2);
    }

    /**
     * Calculate group status from children
     */
    protected function calculateGroupStatus($children, $progress, $group)
    {
        // If group has its own status set
        if ($group->status && $group->status !== 'not_started') {
            $statusMap = [
                'not_started' => ['status' => 'not_started', 'text' => 'Not Started', 'badge' => 'bg-gray-100 text-gray-800'],
                'in_progress' => ['status' => 'in_progress', 'text' => 'In Progress', 'badge' => 'bg-blue-100 text-blue-800'],
                'completed' => ['status' => 'completed', 'text' => 'Completed', 'badge' => 'bg-green-100 text-green-800'],
                'delayed' => ['status' => 'delayed', 'text' => 'Delayed', 'badge' => 'bg-red-100 text-red-800'],
                'on_hold' => ['status' => 'on_hold', 'text' => 'On Hold', 'badge' => 'bg-yellow-100 text-yellow-800'],
            ];
            return $statusMap[$group->status] ?? $statusMap['not_started'];
        }

        // Calculate from progress
        if ($progress == 0) {
            return ['status' => 'not_started', 'text' => 'Not Started', 'badge' => 'bg-gray-100 text-gray-800'];
        } elseif ($progress >= 100) {
            return ['status' => 'completed', 'text' => 'Completed', 'badge' => 'bg-green-100 text-green-800'];
        }

        // Check for delayed children
        $hasDelayed = false;
        foreach ($children as $child) {
            if (($child['status'] ?? '') === 'delayed') {
                $hasDelayed = true;
                break;
            }
        }

        if ($hasDelayed) {
            return ['status' => 'delayed', 'text' => 'Delayed', 'badge' => 'bg-red-100 text-red-800'];
        }

        return ['status' => 'in_progress', 'text' => 'In Progress', 'badge' => 'bg-blue-100 text-blue-800'];
    }

    /**
     * Format activity as group (for hierarchical table)
     */
    protected function formatActivityAsGroup($activity, $level = 0)
    {
        $calculatedDates = $this->calculateActivityDates($activity);
        $calculatedProgress = $activity->progress_percentage ?? 0;
        $calculatedStatus = $this->calculateActivityStatus($activity);

        $formatted = [
            'id' => $activity->id,
            'type' => 'group',
            'name' => $activity->name,
            'is_group' => true,
            'phase_id' => $activity->delivery_support_phase_id,
            'parent_id' => null,
            'level' => $level,
            'weight' => $activity->weight ?? 0,
            'progress_percentage' => $calculatedProgress,
            'status' => $calculatedStatus['status'],
            'status_text' => $calculatedStatus['text'],
            'status_badge' => $calculatedStatus['badge'],
            'start_date' => $calculatedDates['start'] ? $calculatedDates['start']->format('d M Y') : '-',
            'end_date' => $calculatedDates['end'] ? $calculatedDates['end']->format('d M Y') : '-',
            'duration_in_days' => $calculatedDates['duration'],
            'notes' => $activity->notes ?? null,
            'module' => $activity->module,
            'object' => $activity->object,
            'deliverable' => $activity->deliverable,
            'complexity' => $activity->complexity,
            'incident_type' => $activity->incident_type ?? null,
            'sub_groups' => [],
            'stages' => []
        ];

        // Add stages
        if ($activity->stages && $activity->stages->isNotEmpty()) {
            foreach ($activity->stages as $stage) {
                $formatted['stages'][] = $this->formatStageWithActivities($stage);
            }
        }

        return $formatted;
    }

    /**
     * Format stage with activities
     */
    protected function formatStageWithActivities($stage)
    {
        return [
            'id' => $stage->id,
            'type' => 'stage',
            'name' => $stage->name,
            'description' => $stage->description ?? null,
            'weight' => $stage->weight ?? 0,
            'progress' => $stage->progress ?? 0,
            'status' => $stage->status ?? 'not_started',
            'status_text' => ucwords(str_replace('_', ' ', $stage->status ?? 'not_started')),
            'status_badge' => $this->getStatusBadgeClass($stage->status ?? 'not_started'),
            'start_date' => $stage->planned_start_date ? $stage->planned_start_date->format('d M Y') : '-',
            'end_date' => $stage->planned_end_date ? $stage->planned_end_date->format('d M Y') : '-',
            'duration_in_days' => $stage->duration_days ?? null,
            'color' => $stage->color ?? '#06b6d4',
            'activities' => []
        ];
    }

    // =========================================================================
    // GANTT DATA METHODS
    // =========================================================================

    /**
     * Get phase tasks for Gantt view (using planning groups)
     */
    protected function getPhaseGanttTasks(DeliverySupport $support, DeliverySupportPhase $phase, &$allDates)
    {
        // Get groups from planning table
        $planningGroups = DeliverySupportPlanning::where('delivery_support_id', $support->id)
            ->where('phase_id', $phase->id)
            ->where('is_group', true)
            ->whereNull('parent_id')
            ->orderBy('order_sequence')
            ->get();

        $tasks = [];

        foreach ($planningGroups as $planningGroup) {
            $tasks[] = $this->formatPlanningGroupForGantt($support, $phase, $planningGroup, $allDates, 0);
        }

        // Also get activities that are directly under the phase (no group)
        $directActivities = DeliverySupportActivity::where('delivery_support_id', $support->id)
            ->where('delivery_support_phase_id', $phase->id)
            ->whereNotIn('id', function ($query) use ($support) {
                $query->select('activity_id')
                    ->from('delivery_support_planning')
                    ->where('delivery_support_id', $support->id)
                    ->whereNotNull('activity_id');
            })
            ->with(['stages'])
            ->orderBy('order_sequence')
            ->get();

        foreach ($directActivities as $activity) {
            $tasks[] = $this->formatActivityForGantt($activity, $allDates);
        }

        return $tasks;
    }

    /**
     * Format planning group for Gantt
     */
    protected function formatPlanningGroupForGantt(DeliverySupport $support, DeliverySupportPhase $phase, DeliverySupportPlanning $group, &$allDates, $level)
    {
        // Get sub-groups
        $subGroups = DeliverySupportPlanning::where('delivery_support_id', $support->id)
            ->where('parent_id', $group->id)
            ->where('is_group', true)
            ->orderBy('order_sequence')
            ->get();

        $formattedSubGroups = [];
        foreach ($subGroups as $subGroup) {
            $formattedSubGroups[] = $this->formatPlanningGroupForGantt($support, $phase, $subGroup, $allDates, $level + 1);
        }

        // Get activities under this group
        $activityPlannings = DeliverySupportPlanning::where('delivery_support_id', $support->id)
            ->where('parent_id', $group->id)
            ->where('is_group', false)
            ->whereNotNull('activity_id')
            ->with(['activity.stages'])
            ->orderBy('order_sequence')
            ->get();

        $activities = [];
        foreach ($activityPlannings as $actPlanning) {
            if ($actPlanning->activity) {
                $activities[] = $this->formatActivityForGantt($actPlanning->activity, $allDates);
            }
        }

        // Add group's dates
        $this->addDate($allDates, $group->start_date);
        $this->addDate($allDates, $group->end_date);

        // Calculate group progress from children
        $allChildren = array_merge($formattedSubGroups, $activities);
        $groupProgress = $this->calculateGanttGroupProgress($allChildren, $group);

        return [
            'id' => 'group_' . $group->id,
            'name' => $group->name ?? $group->group_name ?? 'Unnamed Group',
            'start' => $group->start_date ? Carbon::parse($group->start_date)->format('Y-m-d') : null,
            'end' => $group->end_date ? Carbon::parse($group->end_date)->format('Y-m-d') : null,
            'progress' => round($groupProgress),
            'status' => $group->status ?? 'not_started',
            'status_color' => $this->getStatusColor($group->status ?? 'not_started'),
            'custom_class' => 'group-task',
            'level' => $level,
            'is_group' => true,
            'sub_groups' => $formattedSubGroups,
            'activities' => $activities,
            'stages' => [],
        ];
    }

    /**
     * Calculate Gantt group progress
     */
    protected function calculateGanttGroupProgress($children, $group)
    {
        if ($group->progress_percentage > 0) {
            return $group->progress_percentage;
        }

        if (empty($children)) {
            return 0;
        }

        $sum = 0;
        foreach ($children as $child) {
            $sum += ($child['progress'] ?? 0);
        }

        return count($children) > 0 ? round($sum / count($children)) : 0;
    }

    /**
     * Format activity for Gantt
     */
    protected function formatActivityForGantt($activity, &$allDates, $level = 0)
    {
        $this->addDate($allDates, $activity->start_date);
        $this->addDate($allDates, $activity->end_date);

        $formatted = [
            'id' => $activity->id,
            'name' => $activity->name,
            'start' => $activity->start_date ? $activity->start_date->format('Y-m-d') : null,
            'end' => $activity->end_date ? $activity->end_date->format('Y-m-d') : null,
            'progress' => round($activity->progress_percentage ?? 0),
            'status' => $activity->status ?? 'not_started',
            'status_color' => $this->getStatusColor($activity->status ?? 'not_started'),
            'custom_class' => $activity->status ?? 'not_started',
            'level' => $level,
            'is_group' => true,
            'sub_groups' => [],
            'stages' => [],
        ];

        // Add stages
        if ($activity->stages && $activity->stages->isNotEmpty()) {
            foreach ($activity->stages as $stage) {
                $formatted['stages'][] = $this->formatStageForGantt($stage, $allDates);
            }
        }

        return $formatted;
    }

    /**
     * Format stage for Gantt
     */
    protected function formatStageForGantt($stage, &$allDates)
    {
        $this->addDate($allDates, $stage->planned_start_date);
        $this->addDate($allDates, $stage->planned_end_date);

        return [
            'id' => $stage->id,
            'name' => $stage->name,
            'planned_start_date' => $stage->planned_start_date ? $stage->planned_start_date->format('Y-m-d') : null,
            'planned_end_date' => $stage->planned_end_date ? $stage->planned_end_date->format('Y-m-d') : null,
            'progress' => round($stage->progress ?? 0),
            'status' => $stage->status ?? 'not_started',
            'color' => $stage->color ?? '#06b6d4',
            'activities' => [],
        ];
    }

    // =========================================================================
    // S-CURVE DATA METHODS
    // =========================================================================

    /**
     * Collect activity dates for S-Curve
     */
    protected function collectActivityDates($activity, &$allDates, &$dataPoints)
    {
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
            'weight' => $activity->weight ?? 0,
            'progress' => $activity->progress_percentage ?? 0,
            'planned_start' => $activity->start_date,
            'planned_end' => $activity->end_date,
            'actual_start' => $activity->actual_start_date,
            'actual_end' => $activity->actual_end_date,
        ];

        // Add stages as data points
        if ($activity->stages) {
            foreach ($activity->stages as $stage) {
                if ($stage->planned_start_date) {
                    $allDates[] = $stage->planned_start_date->format('Y-m-d');
                }
                if ($stage->planned_end_date) {
                    $allDates[] = $stage->planned_end_date->format('Y-m-d');
                }

                $dataPoints[] = [
                    'type' => 'stage',
                    'id' => $stage->id,
                    'name' => $stage->name,
                    'weight' => $stage->weight ?? 0,
                    'progress' => $stage->progress ?? 0,
                    'planned_start' => $stage->planned_start_date,
                    'planned_end' => $stage->planned_end_date,
                    'actual_start' => $stage->actual_start_date ?? null,
                    'actual_end' => $stage->actual_end_date ?? null,
                ];
            }
        }
    }

    /**
     * Generate weekly data for S-Curve
     */
    protected function generateWeeklyData($startDate, $endDate, $dataPoints)
    {
        $weeklyData = [];
        $current = $startDate->copy();

        $totalWeight = collect($dataPoints)->sum('weight');
        if ($totalWeight == 0) {
            $totalWeight = count($dataPoints);
        }

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

    /**
     * Calculate cumulative progress at a date
     */
    protected function calculateCumulativeProgress($dataPoints, $targetDate, $type, $totalWeight)
    {
        if ($totalWeight == 0) return 0;

        $cumulativeWeight = 0;

        foreach ($dataPoints as $point) {
            $startDate = $type === 'planned' ? $point['planned_start'] : $point['actual_start'];
            $endDate = $type === 'planned' ? $point['planned_end'] : $point['actual_end'];

            if (!$startDate || !$endDate) continue;

            if (Carbon::parse($startDate)->lte($targetDate)) {
                $weight = $point['weight'] ?: 1;

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
    protected function calculateSCurveStatistics($dataPoints)
    {
        $total = count($dataPoints);
        $completed = 0;
        $onTrack = 0;
        $delayed = 0;
        $notStarted = 0;

        $totalWeight = 0;
        $completedWeight = 0;

        foreach ($dataPoints as $point) {
            $weight = $point['weight'] ?: 1;
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
            'total_activities' => $total,
            'completed' => $completed,
            'on_track' => $onTrack,
            'delayed' => $delayed,
            'not_started' => $notStarted,
            'overall_progress' => $totalWeight > 0 ? round(($completedWeight / $totalWeight) * 100, 1) : 0,
        ];
    }

    /**
     * Format phases for S-Curve legend
     */
    protected function formatPhasesForSCurve($phases)
    {
        return $phases->map(function ($phase) {
            return [
                'id' => $phase->id,
                'name' => $phase->name,
                'color' => $phase->color ?? '#6366f1',
                'weight' => $phase->weight ?? 0,
            ];
        });
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Calculate activity dates
     */
    protected function calculateActivityDates($activity)
    {
        $startDate = $activity->start_date;
        $endDate = $activity->end_date;

        // Also check stages for dates
        if ($activity->stages && $activity->stages->isNotEmpty()) {
            foreach ($activity->stages as $stage) {
                if ($stage->planned_start_date && (!$startDate || $stage->planned_start_date->lt($startDate))) {
                    $startDate = $stage->planned_start_date;
                }
                if ($stage->planned_end_date && (!$endDate || $stage->planned_end_date->gt($endDate))) {
                    $endDate = $stage->planned_end_date;
                }
            }
        }

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
     * Calculate activity status
     */
    protected function calculateActivityStatus($activity)
    {
        $status = $activity->status ?? 'not_started';

        $statusMap = [
            'not_started' => ['status' => 'not_started', 'text' => 'Not Started', 'badge' => 'bg-gray-100 text-gray-800'],
            'in_progress' => ['status' => 'in_progress', 'text' => 'In Progress', 'badge' => 'bg-blue-100 text-blue-800'],
            'completed' => ['status' => 'completed', 'text' => 'Completed', 'badge' => 'bg-green-100 text-green-800'],
            'delayed' => ['status' => 'delayed', 'text' => 'Delayed', 'badge' => 'bg-red-100 text-red-800'],
            'on_hold' => ['status' => 'on_hold', 'text' => 'On Hold', 'badge' => 'bg-yellow-100 text-yellow-800'],
        ];

        return $statusMap[$status] ?? $statusMap['not_started'];
    }

    /**
     * Calculate phase progress from activities
     */
    protected function calculatePhaseProgress($activities)
    {
        if ($activities->isEmpty()) {
            return 0;
        }

        $totalWeight = $activities->sum('weight');

        if ($totalWeight > 0) {
            $weightedProgress = $activities->sum(function ($activity) {
                return ($activity->weight / 100) * ($activity->progress_percentage ?? 0);
            });
            return round($weightedProgress, 2);
        }

        return round($activities->avg('progress_percentage') ?? 0, 2);
    }

    /**
     * Calculate phase progress from groups
     */
    protected function calculatePhaseProgressFromGroups($groups)
    {
        if (empty($groups)) {
            return 0;
        }

        $totalWeight = 0;
        $weightedProgress = 0;

        foreach ($groups as $group) {
            $weight = $group['weight'] ?? 0;
            $progress = $group['progress_percentage'] ?? 0;

            $totalWeight += $weight;
            $weightedProgress += ($progress * $weight);
        }

        if ($totalWeight == 0) {
            $sum = 0;
            foreach ($groups as $group) {
                $sum += ($group['progress_percentage'] ?? 0);
            }
            return count($groups) > 0 ? round($sum / count($groups), 2) : 0;
        }

        return round($weightedProgress / $totalWeight, 2);
    }

    /**
     * Calculate phase progress from Gantt tasks
     */
    protected function calculatePhaseProgressFromGanttTasks($tasks)
    {
        if (empty($tasks)) {
            return 0;
        }

        $sum = 0;
        foreach ($tasks as $task) {
            $sum += ($task['progress'] ?? 0);
        }

        return count($tasks) > 0 ? round($sum / count($tasks)) : 0;
    }

    /**
     * Calculate phase dates from groups
     */
    protected function calculatePhaseDates($groups)
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
     * Calculate phase status from groups
     */
    protected function calculatePhaseStatus($groups, $progress)
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

    /**
     * Get status badge class
     */
    protected function getStatusBadgeClass($status)
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
     * Get status color
     */
    protected function getStatusColor($status)
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

    /**
     * Add date to array
     */
    protected function addDate(&$dateArray, $date)
    {
        if ($date) {
            $dateStr = $date instanceof Carbon ? $date->format('Y-m-d') : Carbon::parse($date)->format('Y-m-d');
            if (!in_array($dateStr, $dateArray)) {
                $dateArray[] = $dateStr;
            }
        }
    }
}
