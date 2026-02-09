<?php

namespace App\Http\Controllers;

use App\Models\DeliveryProject;
use App\Models\DeliveryProjectPhase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeliveryProjectPlanningController extends Controller
{
    /**
     * Show planning index page - List all projects or show specific project
     */
    public function index(DeliveryProject $project = null)
    {
        // If no project is provided, show all projects
        if (!$project) {
            Log::info('📋 Viewing all projects planning index');

            $projects = DeliveryProject::with(['client', 'phases' => function($query) {
                $query->where('is_visible', true)
                    ->orderBy('order_sequence', 'asc');
            }])->get();

            return view('delivery.project.project-planning.index', compact('projects'));
        }

        // Show specific project planning
        Log::info('📋 Viewing project planning index', [
            'delivery_projects_id' => $project->id,
            'project_name' => $project->name
        ]);

        $project->load([
            'phases' => function($query) {
                $query->where('is_visible', true)
                    ->orderBy('order_sequence', 'asc');
            },
            'plannings' => function ($query) {
                $query->with(['activity.phase', 'children']);
            }
        ]);

        // When project is provided, redirect to phase management
        return redirect()->route('planning.phases.index', $project);
    }

    /**
     * Show planning detail/show page
     */
    public function show(DeliveryProject $project)
    {
        Log::info('📊 Viewing project planning details', [
            'delivery_projects_id' => $project->id,
            'project_name' => $project->name
        ]);

        // Redirect to phase management
        return redirect()->route('planning.phases.index', $project);
    }

    /**
     * Show Gantt Chart page
     */
    public function gantt(DeliveryProject $project)
    {
        Log::info('📊 Viewing Gantt chart', [
            'delivery_projects_id' => $project->id,
            'project_name' => $project->name
        ]);

        return view('project-planning.gantt', compact('project'));
    }

    /**
     * Show S-Curve Analysis page
     */
    public function scurve(DeliveryProject $project)
    {
        Log::info('📈 Viewing S-Curve analysis', [
            'delivery_projects_id' => $project->id,
            'project_name' => $project->name
        ]);

        return view('project-planning.scurve', compact('project'));
    }

    /**
     * Get available phases for project
     */
    public function getPhases(DeliveryProject $project)
    {
        $phases = DeliveryProjectPhase::orderBy('order_sequence')->get();
        
        // One-to-Many: phases belong directly to project
        $assignedPhases = $project->phases()
            ->where('is_visible', true)
            ->pluck('id')
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