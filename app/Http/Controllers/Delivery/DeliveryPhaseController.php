<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\DeliveryProject;
use App\Models\DeliveryPhase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DeliveryPhaseController
 *
 * Mengelola fase delivery langsung per project (tanpa template/junction table).
 */
class DeliveryPhaseController extends Controller
{
    /**
     * Get all phases for a project
     */
    public function index(Project $project)
    {
        try {
            $phases = DeliveryPhase::where('project_id', $project->id)
                ->ordered()
                ->get()
                ->map(function ($phase) {
                    return [
                        'id' => $phase->id,
                        'name' => $phase->name,
                        'code' => $phase->code,
                        'description' => $phase->description,
                        'color' => $phase->color,
                        'icon' => $phase->icon,
                        'order_sequence' => $phase->order_sequence,
                        'planned_start_date' => $phase->planned_start_date?->format('Y-m-d'),
                        'planned_end_date' => $phase->planned_end_date?->format('Y-m-d'),
                        'actual_start_date' => $phase->actual_start_date?->format('Y-m-d'),
                        'actual_end_date' => $phase->actual_end_date?->format('Y-m-d'),
                        'weight' => $phase->weight,
                        'progress_percentage' => $phase->progress_percentage,
                        'status' => $phase->status,
                        'status_text' => $phase->status_text,
                        'status_badge' => $phase->status_badge,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $phases
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting delivery phases', [
                'project_id' => $project->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load phases'
            ], 500);
        }
    }

    /**
     * Create new phase for a project
     */
    public function store(Request $request, Project $project)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:50',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date|after_or_equal:planned_start_date',
            'weight' => 'nullable|numeric|min:0|max:100',
        ]);

        try {
            $maxSequence = DeliveryPhase::where('project_id', $project->id)
                ->max('order_sequence') ?? 0;

            $phase = DeliveryPhase::create([
                'project_id' => $project->id,
                'name' => $validated['name'],
                'code' => $validated['code'] ?? null,
                'description' => $validated['description'] ?? null,
                'color' => $validated['color'] ?? '#3B82F6',
                'icon' => $validated['icon'] ?? null,
                'order_sequence' => $maxSequence + 1,
                'planned_start_date' => $validated['planned_start_date'] ?? null,
                'planned_end_date' => $validated['planned_end_date'] ?? null,
                'weight' => $validated['weight'] ?? 0,
                'progress_percentage' => 0,
                'status' => 'not_started',
            ]);

            Log::info('Delivery phase created', [
                'phase_id' => $phase->id,
                'project_id' => $project->id,
                'name' => $phase->name
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Phase created successfully',
                'data' => $phase
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating delivery phase', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create phase: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get phase details
     */
    public function show(Project $project, DeliveryPhase $phase)
    {
        try {
            $phase->load(['groups' => function($query) {
                $query->rootGroups()->ordered();
            }]);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $phase->id,
                    'project_id' => $phase->project_id,
                    'name' => $phase->name,
                    'code' => $phase->code,
                    'description' => $phase->description,
                    'color' => $phase->color,
                    'icon' => $phase->icon,
                    'order_sequence' => $phase->order_sequence,
                    'planned_start_date' => $phase->planned_start_date?->format('Y-m-d'),
                    'planned_end_date' => $phase->planned_end_date?->format('Y-m-d'),
                    'actual_start_date' => $phase->actual_start_date?->format('Y-m-d'),
                    'actual_end_date' => $phase->actual_end_date?->format('Y-m-d'),
                    'weight' => $phase->weight,
                    'progress_percentage' => $phase->progress_percentage,
                    'status' => $phase->status,
                    'status_text' => $phase->status_text,
                    'status_badge' => $phase->status_badge,
                    'duration_in_days' => $phase->duration_in_days,
                    'groups_count' => $phase->groups->count(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Phase not found'
            ], 404);
        }
    }

    /**
     * Update phase
     */
    public function update(Request $request, Project $project, DeliveryPhase $phase)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'code' => 'nullable|string|max:20',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:50',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date|after_or_equal:planned_start_date',
            'actual_start_date' => 'nullable|date',
            'actual_end_date' => 'nullable|date|after_or_equal:actual_start_date',
            'weight' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|in:not_started,in_progress,completed,delayed,on_hold',
        ]);

        try {
            if (isset($validated['name'])) $phase->name = $validated['name'];
            if (isset($validated['code'])) $phase->code = $validated['code'];
            if (isset($validated['description'])) $phase->description = $validated['description'];
            if (isset($validated['color'])) $phase->color = $validated['color'];
            if (isset($validated['icon'])) $phase->icon = $validated['icon'];
            if (isset($validated['planned_start_date'])) $phase->planned_start_date = $validated['planned_start_date'];
            if (isset($validated['planned_end_date'])) $phase->planned_end_date = $validated['planned_end_date'];
            if (isset($validated['actual_start_date'])) $phase->actual_start_date = $validated['actual_start_date'];
            if (isset($validated['actual_end_date'])) $phase->actual_end_date = $validated['actual_end_date'];
            if (isset($validated['weight'])) $phase->weight = $validated['weight'];
            if (isset($validated['status'])) $phase->status = $validated['status'];

            $phase->save();

            Log::info('Delivery phase updated', ['phase_id' => $phase->id]);

            return response()->json([
                'success' => true,
                'message' => 'Phase updated successfully',
                'data' => $phase->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating delivery phase', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update phase: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete phase
     */
    public function destroy(Project $project, DeliveryPhase $phase)
    {
        try {
            // Check if phase has groups
            if ($phase->groups()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete phase with existing groups. Delete groups first.'
                ], 422);
            }

            $phase->delete();

            Log::info('Delivery phase deleted', ['phase_id' => $phase->id]);

            return response()->json([
                'success' => true,
                'message' => 'Phase deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting delivery phase', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete phase: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reorder phases
     */
    public function reorder(Request $request, Project $project)
    {
        $validated = $request->validate([
            'phases' => 'required|array',
            'phases.*.id' => 'required|exists:delivery_phases,id',
            'phases.*.order_sequence' => 'required|integer|min:1',
        ]);

        try {
            return DB::transaction(function () use ($validated, $project) {
                foreach ($validated['phases'] as $item) {
                    DeliveryPhase::where('id', $item['id'])
                        ->where('project_id', $project->id)
                        ->update(['order_sequence' => $item['order_sequence']]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Phases reordered successfully'
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error reordering phases', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder phases'
            ], 500);
        }
    }

    /**
     * Recalculate phase progress from groups
     */
    public function recalculateProgress(Project $project, DeliveryPhase $phase)
    {
        try {
            $phase->updateProgressFromGroups();
            $phase->updateDatesFromGroups();

            return response()->json([
                'success' => true,
                'message' => 'Progress recalculated',
                'data' => [
                    'progress_percentage' => $phase->fresh()->progress_percentage,
                    'status' => $phase->fresh()->status,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error recalculating phase progress', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to recalculate progress'
            ], 500);
        }
    }
}
