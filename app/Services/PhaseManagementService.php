<?php

namespace App\Services;

use App\Models\DeliveryProject;
use App\Models\DeliveryProjectPhase;
use App\Models\DeliveryDynamicProjectPhase;
use App\Models\DeliveryProjectPlanning;
use App\Models\DeliveryProjectActivity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PhaseManagementService
{
    /**
     * Calculate overall project progress considering all phases and weights
     */
    public function calculateProjectProgress(DeliveryProject $project)
    {
        // One-to-Many: phases belong directly to project
        $phases = $project->phases()
            ->where('is_visible', true)
            ->get();

        if ($phases->isEmpty()) {
            return 0;
        }

        $totalWeightedProgress = 0;
        $totalWeight = 0;

        foreach ($phases as $phase) {
            $phaseProgress = $this->calculatePhaseProgress($project, $phase);
            $phaseWeight = $phase->weight ?? 0;

            // Consider orientation multiplier (horizontal phases might have different impact)
            $orientationMultiplier = $phase->orientation === 'horizontal' ? 0.8 : 1.0;

            $weightedProgress = $phaseProgress * $phaseWeight * $orientationMultiplier;
            $totalWeightedProgress += $weightedProgress;
            $totalWeight += $phaseWeight * $orientationMultiplier;
        }

        return $totalWeight > 0 ? round($totalWeightedProgress / $totalWeight, 1) : 0;
    }

    /**
     * Calculate progress for a specific phase
     */
    public function calculatePhaseProgress(DeliveryProject $project, DeliveryProjectPhase $phase)
    {
        // Get all plannings for this phase
        $plannings = DeliveryProjectPlanning::where('delivery_projects_id', $project->id)
            ->where('phase_id', $phase->id)
            ->where('is_group', false)
            ->get();

        if ($plannings->isEmpty()) {
            return 0;
        }

        return $plannings->avg('progress_percentage') ?? 0;
    }

    /**
     * Generate Gantt chart data with horizontal phases
     */
    public function generateGanttData(DeliveryProject $project)
    {
        $ganttTasks = [];
        $taskIdCounter = 1;

        // Get vertical phases (One-to-Many relationship)
        $verticalPhases = $project->phases()
            ->where('orientation', 'vertical')
            ->where('is_visible', true)
            ->orderBy('order_sequence')
            ->get();

        // Get horizontal phases
        $horizontalPhases = $project->phases()
            ->where('orientation', 'horizontal')
            ->where('is_visible', true)
            ->orderBy('order_sequence')
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
    private function getPhaseGanttTasks(DeliveryProject $project, DeliveryProjectPhase $phase, &$taskIdCounter, $isHorizontal = false)
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

        // Get plannings for this phase
        $plannings = DeliveryProjectPlanning::where('delivery_projects_id', $project->id)
            ->where('phase_id', $phase->id)
            ->whereNull('parent_id')
            ->with(['activity', 'children'])
            ->get();

        foreach ($plannings as $planning) {
            $tasks = array_merge($tasks, $this->createGanttTaskFromPlanning($planning, $taskIdCounter, $phase, $isHorizontal));
        }

        return $tasks;
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
                'name' => $indent . '▼ ' . ($planning->name ?? 'Group'),
                'start' => $planning->children->min('start_date') ?? '',
                'end' => $planning->children->max('end_date') ?? '',
                'progress' => $planning->calculated_progress ?? 0,
                'custom_class' => $isHorizontal ? 'gantt-group-horizontal' : 'gantt-group',
                'dependencies' => ''
            ];

            // Add children
            foreach ($planning->children as $child) {
                $tasks = array_merge($tasks, $this->createGanttTaskFromPlanning($child, $taskIdCounter, $phase, $isHorizontal, $level + 1));
            }
        } else {
            // Add activity - use the name accessor which handles activity lookup
            $activityName = $planning->name ?? 'Unknown';

            $customClass = strtolower(str_replace(' ', '-', $phase->name));
            if ($isHorizontal) {
                $customClass .= ' horizontal-task';
            }
            if ($planning->is_overdue) {
                $customClass .= ' overdue';
            }

            $startDate = $planning->start_date ? $planning->start_date->format('Y-m-d') : Carbon::now()->format('Y-m-d');
            $endDate = $planning->end_date ? $planning->end_date->format('Y-m-d') : Carbon::now()->format('Y-m-d');

            $tasks[] = [
                'id' => 'task_' . $planning->id,
                'name' => $indent . $activityName,
                'start' => $startDate,
                'end' => $endDate,
                'progress' => $planning->progress_percentage ?? 0,
                'custom_class' => $customClass,
                'dependencies' => '',
                'deliverable' => $planning->notes ?? ''
            ];
        }

        return $tasks;
    }

    /**
     * Generate table data for a specific phase
     */
    public function generateTableData(DeliveryProject $project, DeliveryProjectPhase $phase)
    {
        $data = [];

        // Get root plannings for this phase
        $plannings = DeliveryProjectPlanning::where('delivery_projects_id', $project->id)
            ->where('phase_id', $phase->id)
            ->whereNull('parent_id')
            ->with(['activity', 'children.activity'])
            ->get();

        foreach ($plannings as $planning) {
            $data[] = $this->formatPlanningForTable($planning);
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
            'name' => $planning->name ?? 'Unknown',
            'module' => $planning->activity->module ?? null,
            'new_requirement' => $planning->activity->new_requirement ?? false,
            'object' => $planning->activity->object ?? null,
            'receive_type' => $planning->activity->receive_type ?? null,
            'complexity' => $planning->activity->complexity ?? null,
            'start_date' => $planning->start_date,
            'end_date' => $planning->end_date,
            'planned_days' => $planning->duration_in_days,
            'actual_start_date' => $planning->actual_start_date ?? null,
            'actual_end_date' => $planning->actual_end_date ?? null,
            'actual_days' => $planning->actual_duration_in_days ?? null,
            'status' => $planning->status,
            'status_text' => $planning->status_text,
            'progress_percentage' => $planning->progress_percentage,
            'deliverable' => $planning->activity->deliverable ?? null,
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
    public function duplicatePhaseConfiguration(DeliveryProject $sourceProject, DeliveryProject $targetProject)
    {
        DB::transaction(function () use ($sourceProject, $targetProject) {
            // Clear existing phases for target project
            DeliveryProjectPhase::where('delivery_projects_id', $targetProject->id)->delete();

            // Copy phases (One-to-Many - create new records)
            $sourcePhases = $sourceProject->phases()->get();

            foreach ($sourcePhases as $phase) {
                DeliveryProjectPhase::create([
                    'delivery_projects_id' => $targetProject->id,
                    'name' => $phase->name,
                    'description' => $phase->description,
                    'order_sequence' => $phase->order_sequence,
                    'color' => $phase->color,
                    'weight' => $phase->weight,
                    'is_visible' => $phase->is_visible,
                    'custom_settings' => $phase->custom_settings,
                    'is_system_default' => false,
                    'is_optional' => $phase->is_optional,
                    'orientation' => $phase->orientation,
                    'is_active' => $phase->is_active,
                    'settings' => $phase->settings,
                ]);
            }

            // Copy view configuration if exists
            $sourceViewConfig = $targetProject->viewConfiguration ?? null;
            if ($sourceViewConfig) {
                $targetProject->viewConfiguration()->updateOrCreate(
                    ['delivery_projects_id' => $targetProject->id],
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
    public function validatePhaseWeights(DeliveryProject $project, $orientation = 'vertical')
    {
        // One-to-Many relationship
        $phases = $project->phases()
            ->where('orientation', $orientation)
            ->where('is_visible', true)
            ->get();

        $totalWeight = $phases->sum('weight');

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
    public function exportToExcel(DeliveryProject $project)
    {
        $data = [];

        // One-to-Many relationship
        $phases = $project->phases()
            ->where('is_visible', true)
            ->orderBy('order_sequence')
            ->get();

        foreach ($phases as $phase) {
            $phaseData = [
                'phase_name' => $phase->name,
                'orientation' => $phase->orientation,
                'weight' => $phase->weight,
                'activities' => $this->generateTableData($project, $phase)
            ];
            $data[] = $phaseData;
        }

        return $data;
    }
}
