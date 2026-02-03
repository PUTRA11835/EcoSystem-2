<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectPhase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProjectPlanningController extends Controller
{
    /**
     * Show planning index page - List all projects or show specific project
     */
    public function index(Project $project = null)
    {
        // If no project is provided, show all projects
        if (!$project) {
            Log::info('📋 Viewing all projects planning index');

            $projects = Project::with(['client', 'phases' => function($query) {
                $query->wherePivot('is_visible', true)
                    ->orderBy('project_project_phase.order_sequence', 'asc');
            }])->get();

            return view('delivery.project.project-planning.index', compact('projects'));
        }

        // Show specific project planning
        Log::info('📋 Viewing project planning index', [
            'project_id' => $project->id,
            'project_name' => $project->name
        ]);

        $project->load([
            'phases' => function($query) {
                $query->wherePivot('is_visible', true)
                    ->orderBy('project_project_phase.order_sequence', 'asc');
            },
            'plannings' => function ($query) {
                $query->with(['activity.phase', 'customActivity.phase', 'extended', 'children']);
            }
        ]);

        // When project is provided, redirect to phase management
        return redirect()->route('planning.phases.index', $project);
    }

    /**
     * Show planning detail/show page
     */
    public function show(Project $project)
    {
        Log::info('📊 Viewing project planning details', [
            'project_id' => $project->id,
            'project_name' => $project->name
        ]);

        // Redirect to phase management
        return redirect()->route('planning.phases.index', $project);
    }

    /**
     * Show Gantt Chart page
     */
    public function gantt(Project $project)
    {
        Log::info('📊 Viewing Gantt chart', [
            'project_id' => $project->id,
            'project_name' => $project->name
        ]);

        return view('project-planning.gantt', compact('project'));
    }

    /**
     * Show S-Curve Analysis page
     */
    public function scurve(Project $project)
    {
        Log::info('📈 Viewing S-Curve analysis', [
            'project_id' => $project->id,
            'project_name' => $project->name
        ]);

        return view('project-planning.scurve', compact('project'));
    }

    /**
     * Get available phases for project
     */
    public function getPhases(Project $project)
    {
        $phases = ProjectPhase::orderBy('order_sequence')->get();
        
        $assignedPhases = $project->phases()
            ->wherePivot('is_visible', true)
            ->pluck('project_phases.id')
            ->toArray();

        return response()->json([
            'success' => true,
            'phases' => $phases->map(function($phase) use ($assignedPhases) {
                return [
                    'id' => $phase->id,
                    'name' => $phase->name,
                    'color' => $phase->color,
                    'is_assigned' => in_array($phase->id, $assignedPhases),
                ];
            })
        ]);
    }
}