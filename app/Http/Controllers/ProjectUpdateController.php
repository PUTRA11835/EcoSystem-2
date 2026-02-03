<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectUpdate;
use Illuminate\Http\Request;

class ProjectUpdateController extends Controller
{
    /**
     * Store a new issue/update for a project
     */
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'highlight_issue' => 'required|string',
            'action' => 'required|string',
            'due_date' => 'required|date',
            'status' => 'required|string|in:To Be Discussed,To Be Confirmed,Open,Closed',
            'complexity' => 'required|string|in:Low,Medium,High',
        ]);

        $update = $project->updates()->create($validated);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Issue added successfully',
                'update' => $update
            ]);
        }

        return back()->with('success', 'Issue added successfully.');
    }

    /**
     * Display the edit form for an issue (optional if using modal)
     */
    public function edit(ProjectUpdate $project_update)
    {
        return view('delivery.project.issues.edit', ['update' => $project_update]);
    }

    /**
     * Update an existing issue/update
     */
    public function update(Request $request, ProjectUpdate $project_update)
    {
        $validated = $request->validate([
            'highlight_issue' => 'required|string',
            'action' => 'required|string',
            'due_date' => 'required|date',
            'status' => 'required|string|in:To Be Discussed,To Be Confirmed,Open,Closed',
            'complexity' => 'required|string|in:Low,Medium,High',
        ]);

        $project_update->update($validated);
        
        // Update 'updated_at' on parent project
        $project_update->project()->touch();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Issue updated successfully',
                'update' => $project_update->fresh()
            ]);
        }

        return redirect()->route('projects.show', $project_update->project_id)
                         ->with('success', 'Issue updated successfully.');
    }

    /**
     * Delete an issue/update
     */
    public function destroy(ProjectUpdate $project_update)
    {
        $projectId = $project_update->project_id;
        $project_update->delete();

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Issue deleted successfully'
            ]);
        }

        return back()->with('success', 'Issue deleted successfully.');
    }
}