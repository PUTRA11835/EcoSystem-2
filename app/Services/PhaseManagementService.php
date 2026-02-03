<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\DynamicProjectPhase;
use App\Models\ProjectPlanning;
use App\Models\ProjectActivity;
use App\Models\ProjectCustomActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PhaseManagementService
{
    /**
     * Calculate overall project progress considering all phases and weights
     */
    public function calculateProjectProgress(Project $project)
    {
        $phases = $project->phases()
            ->withPivot(['weight', 'is_visible', 'orientation'])
            ->wherePivot('is_visible', true)
            ->get();

        if ($phases->isEmpty()) {
            return 0;
        }

        $totalWeightedProgress = 0;
        $totalWeight = 0;

        foreach ($phases as $phase) {
            $phaseProgress = $this->calculatePhaseProgress($project, $phase);
            $phaseWeight = $phase->pivot->weight;
            
            // Consider orientation multiplier (horizontal phases might have different impact)
            $orientationMultiplier = $phase->pivot->orientation === 'horizontal' ? 0.8 : 1.0;
            
            $weightedProgress = $phaseProgress * $phaseWeight * $orientationMultiplier;
            $totalWeightedProgress += $weightedProgress;
            $totalWeight += $phaseWeight * $orientationMultiplier;
        }

        return $totalWeight > 0 ? round($totalWeightedProgress / $totalWeight, 1) : 0;
    }

    /**
     * Calculate progress for a specific phase
     */
    public function calculatePhaseProgress(Project $project, ProjectPhase $phase)
    {
        // Get all activities for this phase (including from groups and sub-groups)
        $plannings = ProjectPlanning::where('project_id', $project->id)
            ->where('is_group', false)
            ->where(function($query) use ($phase) {
                $query->whereHas('activity', function($q) use ($phase) {
                    $q->where('project_phase_id', $phase->id);
                })->orWhereHas('customActivity', function($q) use ($phase) {
                    $q->where('project_phase_id', $phase->id);
                });
            })
            ->get();

        if ($plannings->isEmpty()) {
            return 0;
        }

        return $plannings->avg('progress_percentage') ?? 0;
    }

    /**
     * Generate Gantt chart data with horizontal phases
     */
    public function generateGanttData(Project $project)
    {
        $ganttTasks = [];
        $taskIdCounter = 1;
        
        // Get vertical phases
        $verticalPhases = $project->phases()
            ->withPivot(['weight', 'is_visible', 'orientation', 'order_sequence'])
            ->wherePivot('orientation', 'vertical')
            ->wherePivot('is_visible', true)
            ->orderBy('pivot_order_sequence')
            ->get();

        // Get horizontal phases
        $horizontalPhases = $project->phases()
            ->withPivot(['weight', 'is_visible', 'orientation', 'order_sequence'])
            ->wherePivot('orientation', 'horizontal')
            ->wherePivot('is_visible', true)
            ->orderBy('pivot_order_sequence')
            ->get();

        // Process vertical phases
        foreach ($verticalPhases as $phase) {
            $phaseTasks = $this->getPhaseGanttTasks($project, $phase, $taskIdCounter);
            $ganttTasks = array_merge($ganttTasks, $phaseTasks);
            $taskIdCounter += count($phaseTasks);
        }

        // Add separator for horizontal phases if any exist
        if ($horizontalPhases->isNotEmpty()) {
            $ganttTasks[] = [
                'id' => 'separator_' . $taskIdCounter++,
                'name' => '--- FASE HORIZONTAL ---',
                'start' => Carbon::now()->format('Y-m-d'),
                'end' => Carbon::now()->format('Y-m-d'),
                'progress' => 0,
                'custom_class' => 'gantt-separator',
                'dependencies' => ''
            ];
        }

        // Process horizontal phases
        foreach ($horizontalPhases as $phase) {
            $phaseTasks = $this->getPhaseGanttTasks($project, $phase, $taskIdCounter, true);
            $ganttTasks = array_merge($ganttTasks, $phaseTasks);
            $taskIdCounter += count($phaseTasks);
        }

        return $ganttTasks;
    }

    /**
     * Get Gantt tasks for a specific phase
     */
    private function getPhaseGanttTasks(Project $project, ProjectPhase $phase, &$taskIdCounter, $isHorizontal = false)
    {
        $tasks = [];
        
        // Add phase header
        $tasks[] = [
            'id' => 'phase_' . $taskIdCounter++,
            'name' => strtoupper($phase->name) . ($isHorizontal ? ' (H)' : ''),
            'start' => '',
            'end' => '',
            'progress' => $this->calculatePhaseProgress($project, $phase),
            'custom_class' => $isHorizontal ? 'gantt-phase-horizontal' : 'gantt-phase-header',
            'dependencies' => ''
        ];

        // Get activities for this phase
        $plannings = ProjectPlanning::where('project_id', $project->id)
            ->whereNull('parent_id')
            ->with(['activity', 'customActivity', 'children'])
            ->get();

        foreach ($plannings as $planning) {
            if ($this->planningBelongsToPhase($planning, $phase)) {
                $tasks = array_merge($tasks, $this->createGanttTaskFromPlanning($planning, $taskIdCounter, $phase, $isHorizontal));
            }
        }

        return $tasks;
    }

    /**
     * Check if planning belongs to a specific phase
     */
    private function planningBelongsToPhase($planning, ProjectPhase $phase)
    {
        if (!$planning->is_group) {
            return ($planning->activity && $planning->activity->project_phase_id == $phase->id) ||
                   ($planning->customActivity && $planning->customActivity->project_phase_id == $phase->id);
        }

        // For groups, check children recursively
        foreach ($planning->children as $child) {
            if ($this->planningBelongsToPhase($child, $phase)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create Gantt task from planning item
     */
    private function createGanttTaskFromPlanning($planning, &$taskIdCounter, $phase, $isHorizontal = false, $level = 0)
    {
        $tasks = [];
        $indent = str_repeat('  ', $level);

        if ($planning->is_group) {
            // Add group header
            $tasks[] = [
                'id' => 'group_' . $taskIdCounter++,
                'name' => $indent . '▼ ' . ($planning->notes ?? 'Group'),
                'start' => $planning->children->min('start_date') ?? '',
                'end' => $planning->children->max('end_date') ?? '',
                'progress' => $planning->calculateProgress(),
                'custom_class' => $isHorizontal ? 'gantt-group-horizontal' : 'gantt-group',
                'dependencies' => ''
            ];

            // Add children
            foreach ($planning->children as $child) {
                $tasks = array_merge($tasks, $this->createGanttTaskFromPlanning($child, $taskIdCounter, $phase, $isHorizontal, $level + 1));
            }
        } else {
            // Add activity
            $activityName = $planning->activity ? $planning->activity->name : 
                          ($planning->customActivity ? $planning->customActivity->name : 'Unknown');
            
            $customClass = strtolower(str_replace(' ', '-', $phase->name));
            if ($isHorizontal) {
                $customClass .= ' horizontal-task';
            }
            if ($planning->is_overdue) {
                $customClass .= ' overdue';
            }

            $tasks[] = [
                'id' => 'task_' . $planning->id,
                'name' => $indent . $activityName,
                'start' => $planning->start_date->format('Y-m-d'),
                'end' => $planning->end_date->format('Y-m-d'),
                'progress' => $planning->progress_percentage,
                'custom_class' => $customClass,
                'dependencies' => $planning->dependencies ?? '',
                'deliverable' => $planning->notes ?? ''
            ];
        }

        return $tasks;
    }

    /**
     * Generate table data for a specific phase
     */
    public function generateTableData(Project $project, DynamicProjectPhase $phase)
    {
        $data = [];
        
        // Get root plannings for this phase
        $plannings = ProjectPlanning::where('project_id', $project->id)
            ->whereNull('parent_id')
            ->with(['activity', 'customActivity', 'children.activity', 'children.customActivity'])
            ->get();

        foreach ($plannings as $planning) {
            if ($this->planningBelongsToPhase($planning, $phase)) {
                $data[] = $this->formatPlanningForTable($planning);
            }
        }

        return $data;
    }

    /**
     * Format planning item for table display
     */
    private function formatPlanningForTable($planning, $level = 0)
    {
        $formatted = [
            'id' => $planning->id,
            'level' => $level,
            'is_group' => $planning->is_group,
            'name' => $planning->is_group ? ($planning->notes ?? 'Unnamed Group') : 
                     ($planning->activity ? $planning->activity->name : 
                     ($planning->customActivity ? $planning->customActivity->name : 'Unknown')),
            'module' => $planning->module ?? null,
            'new_requirement' => $planning->new_requirement ?? false,
            'tcode' => $planning->tcode ?? null,
            'receive_type' => $planning->receive_type ?? null,
            'complexity' => $planning->complexity ?? null,
            'functional_sinergi' => $planning->functional_sinergi ?? null,
            'technical_sinergi' => $planning->technical_sinergi ?? null,
            'start_date' => $planning->start_date,
            'end_date' => $planning->end_date,
            'planned_days' => $planning->duration_in_days,
            'actual_start_date' => $planning->actual_start_date ?? null,
            'actual_end_date' => $planning->actual_end_date ?? null,
            'actual_days' => $planning->actual_duration_in_days ?? null,
            'status' => $planning->status,
            'status_text' => $planning->status_text,
            'progress_percentage' => $planning->progress_percentage,
            'deliverable' => $planning->deliverable ?? null,
            'notes' => $planning->notes ?? null,
            'children' => []
        ];

        // Add children if it's a group
        if ($planning->is_group && $planning->children) {
            foreach ($planning->children as $child) {
                $formatted['children'][] = $this->formatPlanningForTable($child, $level + 1);
            }
        }

        return $formatted;
    }

    /**
     * Duplicate phase configuration from one project to another
     */
    public function duplicatePhaseConfiguration(Project $sourceProject, Project $targetProject)
    {
        DB::transaction(function () use ($sourceProject, $targetProject) {
            // Clear existing configuration
            DB::table('project_project_phase')->where('project_id', $targetProject->id)->delete();
            
            // Copy phase configuration
            $sourcePhases = $sourceProject->phases()
                ->withPivot(['weight', 'order_sequence', 'is_visible', 'orientation', 'custom_settings'])
                ->get();
            
            foreach ($sourcePhases as $phase) {
                $targetProject->phases()->attach($phase->id, [
                    'weight' => $phase->pivot->weight,
                    'order_sequence' => $phase->pivot->order_sequence,
                    'is_visible' => $phase->pivot->is_visible,
                    'orientation' => $phase->pivot->orientation,
                    'custom_settings' => $phase->pivot->custom_settings,
                ]);
            }
            
            // Copy view configuration
            $sourceViewConfig = $sourceProject->viewConfiguration;
            if ($sourceViewConfig) {
                ProjectViewConfiguration::updateOrCreate(
                    ['project_id' => $targetProject->id],
                    [
                        'default_view' => $sourceViewConfig->default_view,
                        'gantt_settings' => $sourceViewConfig->gantt_settings,
                        'table_settings' => $sourceViewConfig->table_settings,
                        'column_visibility' => $sourceViewConfig->column_visibility,
                    ]
                );
            }
        });
    }

    /**
     * Validate phase weight distribution
     */
    public function validatePhaseWeights(Project $project, $orientation = 'vertical')
    {
        $phases = $project->phases()
            ->wherePivot('orientation', $orientation)
            ->wherePivot('is_visible', true)
            ->get();

        $totalWeight = $phases->sum('pivot.weight');

        return [
            'valid' => abs($totalWeight - 100) < 0.01,
            'total_weight' => $totalWeight,
            'message' => abs($totalWeight - 100) < 0.01 ? 
                'Bobot fase valid' : 
                "Total bobot harus 100%, saat ini: {$totalWeight}%"
        ];
    }

    /**
     * Export project planning to Excel format
     */
    public function exportToExcel(Project $project)
    {
        // This would integrate with a library like PhpSpreadsheet
        // Returns data formatted for Excel export
        $data = [];
        
        $phases = $project->phases()
            ->withPivot(['weight', 'is_visible', 'orientation'])
            ->wherePivot('is_visible', true)
            ->orderBy('pivot_order_sequence')
            ->get();

        foreach ($phases as $phase) {
            $phaseData = [
                'phase_name' => $phase->name,
                'orientation' => $phase->pivot->orientation,
                'weight' => $phase->pivot->weight,
                'activities' => $this->generateTableData($project, $phase)
            ];
            $data[] = $phaseData;
        }

        return $data;
    }
}