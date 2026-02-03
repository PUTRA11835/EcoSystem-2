<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectPhase;
use App\Models\DynamicProjectPhase;
use App\Models\ProjectPlanning;
use App\Models\ProjectActivity;
use App\Models\ProjectViewConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DynamicPhaseController extends Controller
{
    /**
     * Display phase management interface
     */
    public function index(Project $project)
    {
        // ✅ Eager load relasi yang dibutuhkan untuk mengurangi query database
        $project->load([
            'client',
            'phases' => function ($query) {
                $query->withPivot(['weight', 'order_sequence', 'is_visible', 'orientation', 'custom_settings'])
                      ->orderBy('pivot_order_sequence');
            },
            // ✅ Ambil SEMUA plannings, termasuk relasi yang dibutuhkan oleh view
            'plannings' => function ($query) {
                $query->with(['activity.phase', 'customActivity.phase', 'extended', 'children']);
            }
        ]);

        $phases = $project->phases; // Ambil dari relasi yang sudah di-load

        $verticalPhases = $phases->where('pivot.orientation', 'vertical');
        $horizontalPhases = $phases->where('pivot.orientation', 'horizontal');

        $availablePhases = ProjectPhase::where('is_active', true)
            ->whereNotIn('id', $phases->pluck('id'))
            ->get();
        
        $viewConfig = ProjectViewConfiguration::firstOrCreate(
            ['project_id' => $project->id],
            [
                'default_view' => 'table',
                'gantt_settings' => [],
                'table_settings' => [],
                'column_visibility' => $this->getDefaultColumnVisibility()
            ]
        );
        
        return view('delivery.project.project-planning.phase-management', compact(
            'project',
            'verticalPhases',
            'horizontalPhases',
            'availablePhases',
            'viewConfig'
            // Variabel 'plannings' sekarang otomatis ada di dalam objek $project
        ));
    }

    public function createCustomPhase(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'orientation' => 'required|in:vertical,horizontal',
                'color' => 'nullable|string|max:7',
            ]);

            // ✅ Get max order_sequence
            $maxSequence = DB::table('project_phases')
                ->where('is_active', true)
                ->max('order_sequence');
            
            // ✅ Handle null case
            if ($maxSequence === null) {
                $maxSequence = 0;
            }

            // ✅ Create new phase
            $phase = ProjectPhase::create([
                'name' => $validated['name'],
                'color' => $validated['color'] ?? '#3B82F6',
                'is_active' => true,
                'order_sequence' => $maxSequence + 1,
                'description' => 'Custom phase created for project',
            ]);

            Log::info('✅ Custom phase created successfully', [
                'phase_id' => $phase->id,
                'name' => $phase->name,
                'order_sequence' => $phase->order_sequence
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Phase created successfully',
                'phase' => [
                    'id' => $phase->id,
                    'name' => $phase->name,
                    'color' => $phase->color,
                    'orientation' => $validated['orientation']
                ]
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', array_flatten($e->errors()))
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('❌ Error creating custom phase', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create phase: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * ✅ FIXED: Add phase to project - Accept EXACT weight from user (NO auto-calculation)
     */
    public function addPhase(Request $request, Project $project)
    {
        $validated = $request->validate([
            'phase_id' => 'required|exists:project_phases,id',
            'weight' => 'required|numeric|min:0|max:100',
            'orientation' => 'required|in:vertical,horizontal',
            'is_golive_phase' => 'nullable|boolean',
            'parent_phase_id' => 'nullable|exists:project_phases,id',
        ]);

        try {
            DB::transaction(function () use ($validated, $project) {
                // Get current max sequence
                $maxSequence = DB::table('project_project_phase')
                    ->where('project_id', $project->id)
                    ->where('orientation', $validated['orientation'])
                    ->max('order_sequence') ?? 0;

                // ✅ FIXED: Use $validated instead of $request
                $project->phases()->attach($validated['phase_id'], [
                    'weight' => $validated['weight'],
                    'order_sequence' => $maxSequence + 1,
                    'is_visible' => true,
                    'is_golive_phase' => $validated['is_golive_phase'] ?? false, // ✅ FIXED
                    'orientation' => $validated['orientation'],
                    'custom_settings' => json_encode($validated['custom_settings'] ?? []),
                ]);
            });

            Log::info('✅ Phase added with exact weight', [
                'phase_id' => $validated['phase_id'],
                'weight' => $validated['weight'],
                'is_golive' => $validated['is_golive_phase'] ?? false,
                'orientation' => $validated['orientation']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Fase berhasil ditambahkan',
            ]);
        } catch (\Exception $e) {
            Log::error('Error adding phase: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan fase: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ FIXED: Update phase - Accept EXACT weight from user (NO auto-calculation)
     */
    public function updatePhase(Request $request, Project $project, $phaseId)
    {
        $validated = $request->validate([
            'weight' => 'nullable|numeric|min:0|max:100',
            'is_visible' => 'nullable|boolean',
            'is_golive_phase' => 'nullable|boolean', // ✅ Added
            'custom_settings' => 'nullable|array',
        ]);

        try {
            $updateData = [];
            
            if (isset($validated['weight'])) {
                $updateData['weight'] = $validated['weight'];
            }
            
            if (isset($validated['is_visible'])) {
                $updateData['is_visible'] = $validated['is_visible'];
            }
            
            // ✅ FIXED: Add is_golive_phase to update
            if (isset($validated['is_golive_phase'])) {
                $updateData['is_golive_phase'] = $validated['is_golive_phase'];
            }
            
            if (isset($validated['custom_settings'])) {
                $updateData['custom_settings'] = json_encode($validated['custom_settings']);
            }

            if (!empty($updateData)) {
                $project->phases()->updateExistingPivot($phaseId, $updateData);
            }

            Log::info('✅ Phase updated', [
                'phase_id' => $phaseId,
                'updates' => $updateData
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Konfigurasi fase berhasil diperbarui',
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating phase: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui fase: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ FIXED: Remove phase - NO weight recalculation
     */
    public function removePhase(Request $request, Project $project, $phaseId)
    {
        try {
            DB::transaction(function () use ($project, $phaseId) {
                // Check if phase has activities
                $hasActivities = ProjectPlanning::where('project_id', $project->id)
                    ->whereHas('activity', function($q) use ($phaseId) {
                        $q->where('project_phase_id', $phaseId);
                    })
                    ->exists();

                if ($hasActivities) {
                    throw new \Exception('Fase tidak dapat dihapus karena memiliki aktivitas');
                }

                // Detach phase
                $project->phases()->detach($phaseId);

                // ✅ NO recalculateWeights() - User will manually adjust remaining weights
            });

            Log::info('✅ Phase removed from project', ['phase_id' => $phaseId]);

            return response()->json([
                'success' => true,
                'message' => 'Fase berhasil dihapus',
            ]);
        } catch (\Exception $e) {
            Log::error('Error removing phase: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Reorder phases (no weight changes)
     */
    public function reorderPhases(Request $request, Project $project)
    {
        $request->validate([
            'phases' => 'required|array',
            'phases.*.id' => 'required|exists:project_phases,id',
            'phases.*.sequence' => 'required|integer|min:0',
        ]);

        try {
            DB::transaction(function () use ($request, $project) {
                foreach ($request->phases as $phaseData) {
                    $project->phases()->updateExistingPivot($phaseData['id'], [
                        'order_sequence' => $phaseData['sequence'],
                    ]);
                }
            });

            Log::info('✅ Phases reordered', ['count' => count($request->phases)]);

            return response()->json([
                'success' => true,
                'message' => 'Urutan fase berhasil diperbarui',
            ]);
        } catch (\Exception $e) {
            Log::error('Error reordering phases: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengubah urutan fase: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle phase visibility
     */
    public function togglePhaseVisibility(Request $request, Project $project, $phaseId)
    {
        try {
            $phase = $project->phases()->find($phaseId);
            $currentVisibility = $phase->pivot->is_visible;

            $project->phases()->updateExistingPivot($phaseId, [
                'is_visible' => !$currentVisibility,
            ]);

            Log::info('✅ Phase visibility toggled', [
                'phase_id' => $phaseId,
                'new_visibility' => !$currentVisibility
            ]);

            return response()->json([
                'success' => true,
                'is_visible' => !$currentVisibility,
                'message' => 'Visibilitas fase berhasil diubah',
            ]);
        } catch (\Exception $e) {
            Log::error('Error toggling phase visibility: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengubah visibilitas fase: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update view configuration
     */
    public function updateViewConfig(Request $request, Project $project)
    {
        try {
            // ✅ FIXED: Make all fields optional with defaults
            $validated = $request->validate([
                'default_view' => 'nullable|in:gantt,table,scurve',
                'gantt_settings' => 'nullable|array',
                'table_settings' => 'nullable|array',
                'column_visibility' => 'nullable|array',
            ]);

            // ✅ Set default view jika tidak ada
            if (empty($validated['default_view'])) {
                $validated['default_view'] = $request->input('default_view', 'table');
            }

            Log::info('✅ Updating view config', [
                'project_id' => $project->id,
                'data' => $validated
            ]);

            $viewConfig = ProjectViewConfiguration::updateOrCreate(
                ['project_id' => $project->id],
                [
                    'default_view' => $validated['default_view'],
                    'gantt_settings' => $validated['gantt_settings'] ?? [],
                    'table_settings' => $validated['table_settings'] ?? [],
                    'column_visibility' => $validated['column_visibility'] ?? $this->getDefaultColumnVisibility(),
                ]
            );

            return response()->json([
                'success' => true,
                'config' => $viewConfig,
                'message' => 'View preference saved successfully',
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('❌ Validation error in updateViewConfig', [
                'errors' => $e->errors(),
                'input' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('❌ Error updating view config', [
                'project_id' => $project->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to save view preference: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get default column visibility settings
     */
    private function getDefaultColumnVisibility()
    {
        return [
            'task_title' => true,
            'module' => true,
            'new_req' => true,
            'tcode' => true,
            'receive_type' => true,
            'complexity' => true,
            'functional_sinergi' => true,
            'technical_sinergi' => true,
            'planned_start' => true,
            'planned_end' => true,
            'planned_days' => true,
            'actual_start' => true,
            'actual_end' => true,
            'actual_days' => true,
            'status' => true,
            'progress' => true,
            'deliverable' => true,
            'notes' => true,
        ];
    }
}