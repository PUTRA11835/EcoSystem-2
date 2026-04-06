<?php

namespace App\Services;

use App\Models\DeliveryProject;
use App\Models\DeliveryProjectPhase;
use App\Models\DeliveryProjectPlanning;
use App\Models\DeliveryProjectActivity;
use App\Models\ActivityStage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/**
 * Service untuk handle business logic project planning
 * Memisahkan logic dari controller untuk performa lebih baik
 */
class DeliveryProjectPlanningService
{
    /**
     * Get hierarchical phase data dengan optimized eager loading
     */
    public function getPhaseActivitiesOptimized(DeliveryProject $project, DeliveryProjectPhase $phase): array
    {
        Log::info("Fetching activities for Project ID: {$project->id} and Phase ID: {$phase->id}");

        // ✅ Optimized: Eager load dengan query builder yang lebih efisien
        $groups = DeliveryProjectPlanning::where('delivery_projects_id', $project->id)
            ->where('phase_id', $phase->id)
            ->where('is_group', true)
            ->whereNull('parent_id')
            ->with($this->getEagerLoadRelations())
            ->orderBy('order_sequence')
            ->get();

        Log::info("Found {$groups->count()} root groups for phase {$phase->name}");

        // Load all related data in batches to avoid N+1
        $this->loadNestedRelations($groups);

        $result = [];
        foreach ($groups as $group) {
            $result[] = $this->formatGroupRecursive($group);
        }

        return $result;
    }

    /**
     * Get table data dengan optimized queries
     */
    public function getTableDataOptimized(DeliveryProject $project): array
    {
        Log::info('📊 Getting table data with optimized queries', ['project_id' => $project->id]);
        
        // ✅ Optimized: One-to-Many relationship (phases belong to project)
        $phases = $project->phases()
            ->where('is_visible', true)
            ->orderBy('order_sequence', 'asc')
            ->get();
        
        $data = [];
        
        foreach ($phases as $phase) {
            $groups = $this->getPhaseActivitiesOptimized($project, $phase);
            
            // Calculate phase metrics
            $phaseDates = $this->calculatePhaseDates($groups);
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
                    'duration_in_days' => $phaseDates['duration'],
                    'progress' => $phaseProgress,
                    'status' => $phaseStatus['status'],
                    'status_text' => $phaseStatus['text'],
                    'status_badge' => $phaseStatus['badge'],
                ],
                'groups' => $groups
            ];
        }
        
        return $data;
    }

    /**
     * Calculate group dates dari children
     */
    public function calculateGroupDates($group): array
    {
        $allDates = collect();
        
        // Collect dates dari stages
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
        
        // Collect dates dari sub-groups (recursive)
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
     * Calculate group progress dengan weighted average
     */
    public function calculateGroupProgress($group): float
    {
        $totalWeight = 0;
        $weightedProgress = 0;
        
        // Progress dari stages
        if ($group->stages && $group->stages->isNotEmpty()) {
            foreach ($group->stages as $stage) {
                $weight = $stage->weight ?? 0;
                $progress = $stage->calculated_progress ?? $stage->progress ?? 0;
                
                $totalWeight += $weight;
                $weightedProgress += ($progress * $weight);
            }
        }
        
        // Progress dari sub-groups
        if ($group->children && $group->children->where('is_group', true)->isNotEmpty()) {
            foreach ($group->children->where('is_group', true) as $subGroup) {
                $weight = $subGroup->calculated_weight ?? 0;
                $progress = $this->calculateGroupProgress($subGroup);
                
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
            
            return $allProgress->isNotEmpty() ? round($allProgress->avg(), 2) : 0;
        }
        
        return round($weightedProgress / $totalWeight, 2);
    }

    /**
     * Calculate group status
     */
    public function calculateGroupStatus($group): array
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
        
        // Determine overall status
        if ($progress == 0 && $allStatuses->isEmpty()) {
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
     * Calculate phase dates dari groups
     */
    private function calculatePhaseDates(array $groups): array
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
     * Calculate phase progress
     */
    private function calculatePhaseProgress(array $groups, float $phaseWeight): float
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
            return round(collect($groups)->avg('progress_percentage') ?? 0, 2);
        }
        
        return round($weightedProgress / $totalWeight, 2);
    }

    /**
     * Calculate phase status
     */
    private function calculatePhaseStatus(array $groups, float $progress): array
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
     * Format group recursive untuk response
     */
    private function formatGroupRecursive($group, int $level = 0): array
    {
        $calculatedDates = $this->calculateGroupDates($group);
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
            'duration_in_days' => $calculatedDates['duration'],
            'notes' => $group->notes,
            'sub_groups' => [],
            'stages' => []
        ];

        // Format SUB-GROUPS
        if ($group->children && $group->children->isNotEmpty()) {
            foreach ($group->children->where('is_group', true) as $subGroup) {
                $formatted['sub_groups'][] = $this->formatGroupRecursive($subGroup, $level + 1);
            }
        }

        // Format STAGES
        if ($group->stages && $group->stages->isNotEmpty()) {
            foreach ($group->stages as $stage) {
                $formatted['stages'][] = $this->formatStageWithActivities($stage);
            }
        }

        return $formatted;
    }

    /**
     * Format stage dengan activities
     */
    private function formatStageWithActivities($stage): array
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
            'duration_in_days' => $stage->duration_days ?? null,
            'color' => $stage->color ?? '#06b6d4',
            'planning_id' => $stage->planning_id,
            'activities' => []
        ];

        // Use projectActivities (NEW structure) if available
        if ($stage->projectActivities && $stage->projectActivities->isNotEmpty()) {
            foreach ($stage->projectActivities as $activity) {
                $stageData['activities'][] = $this->formatActivityForHierarchy($activity, 0);
            }
        }
        // FALLBACK: Use old structure
        elseif ($stage->activities && $stage->activities->isNotEmpty()) {
            foreach ($stage->activities as $activity) {
                $stageData['activities'][] = $this->formatActivityForHierarchy($activity, 0);
            }
        }

        return $stageData;
    }

    /**
     * Format activity untuk hierarchy
     */
    private function formatActivityForHierarchy($activity, int $level = 0): array
    {
        $isProjectActivity = $activity instanceof DeliveryProjectActivity;
        
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
            $formatted['status'] = $activity->status ?? 'not_started';
            
            $duration = null;
            if ($activity->start_date && $activity->end_date) {
                $duration = $activity->start_date->diffInDays($activity->end_date) + 1;
            }
            $formatted['duration_in_days'] = $duration;
            
            $formatted['status_text'] = ucwords(str_replace('_', ' ', $activity->status ?? 'not_started'));
            $formatted['status_badge'] = $this->getStatusBadgeClass($activity->status ?? 'not_started');
            
            $formatted['is_overdue'] = false;
            if ($activity->end_date && $activity->status !== 'completed') {
                $formatted['is_overdue'] = $activity->end_date->isPast();
            }
            
            // Extended fields
            $formatted['module'] = $activity->module;
            $formatted['tcode'] = $activity->tcode;
            $formatted['deliverable'] = $activity->deliverable;
            $formatted['complexity'] = $activity->complexity;
            $formatted['receive_type'] = $activity->receive_type;
            $formatted['new_requirement'] = $activity->new_requirement;
            $formatted['functional_sinergi'] = $activity->functional_sinergi;
            $formatted['technical_sinergi'] = $activity->technical_sinergi;
        } else {
            // OLD structure - backward compatibility
            $formatted['progress_percentage'] = $activity->calculated_progress ?? $activity->progress_percentage ?? 0;
            $formatted['start_date'] = $activity->start_date ? $activity->start_date->format('d M Y') : '-';
            $formatted['end_date'] = $activity->end_date ? $activity->end_date->format('d M Y') : '-';
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

    /**
     * Get status badge class
     */
    private function getStatusBadgeClass(string $status): string
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
     * Get eager load relations untuk optimized query
     */
    private function getEagerLoadRelations(): array
    {
        return [
            'children' => function($query) {
                $query->where('is_group', true)
                    ->with(['children', 'stages'])
                    ->orderBy('order_sequence');
            },
            'stages' => function($query) {
                $query->with([
                    'projectActivities' => function($actQuery) {
                        $actQuery->orderBy('order_sequence');
                    }
                ])->orderBy('order_sequence');
            }
        ];
    }

    /**
     * Load nested relations untuk avoid N+1
     */
    private function loadNestedRelations(Collection $groups): void
    {
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
    }
}