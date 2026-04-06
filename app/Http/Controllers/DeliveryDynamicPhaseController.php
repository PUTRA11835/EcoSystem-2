<?php

namespace App\Http\Controllers;

use App\Models\DeliveryProject;
use App\Models\DeliveryProjectPhase;
use App\Models\DeliveryDynamicProjectPhase;
use App\Models\DeliveryProjectPlanning;
use App\Models\DeliveryProjectActivity;
use App\Models\DeliveryProjectViewConfiguration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeliveryDynamicPhaseController extends Controller
{
    /**
     * Display phase management interface
     */
    public function index(DeliveryProject $project)
    {
        // ✅ Eager load relasi yang dibutuhkan
        $project->load([
            'client',
            'phases' => function ($query) {
                $query->orderBy('order_sequence');
            },
            'plannings' => function ($query) {
                $query->with(['activity.phase', 'children']);
            }
        ]);

        $phases = $project->phases;

        $verticalPhases = $phases->where('orientation', 'vertical');
        $horizontalPhases = $phases->where('orientation', 'horizontal');

        // ✅ Ambil phase template yang belum ditambahkan ke project
        $availablePhases = DeliveryProjectPhase::where('is_system_default', true)
            ->whereNull('delivery_projects_id')
            ->whereNotIn('id', $phases->pluck('id'))
            ->get();
        
        $viewConfig = DeliveryProjectViewConfiguration::firstOrCreate(
            ['delivery_projects_id' => $project->id],
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

            $maxSequence = DB::table('delivery_project_phases')
                ->where('is_active', true)
                ->max('order_sequence');
            
            if ($maxSequence === null) {
                $maxSequence = 0;
            }

            $phase = DeliveryProjectPhase::create([
                'name' => $validated['name'],
                'color' => $validated['color'] ?? '#3B82F6',
                'is_active' => true,
                'order_sequence' => $maxSequence + 1,
                'description' => 'Custom phase created for project',
                'is_system_default' => true, // Jadi template untuk semua project
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
     * ✅ FIXED: Add phase to project - One-to-Many menggunakan create()
     */
    public function addPhase(Request $request, DeliveryProject $project)
    {
        $validated = $request->validate([
            'phase_id' => 'required|exists:delivery_project_phases,id',
            'weight' => 'required|numeric|min:0|max:100',
            'orientation' => 'required|in:vertical,horizontal',
            'is_golive_phase' => 'nullable|boolean',
            'custom_settings' => 'nullable|array',
        ]);

        try {
            // ✅ Ambil phase template
            $systemPhase = DeliveryProjectPhase::where('id', $validated['phase_id'])
                ->where('is_system_default', true)
                ->whereNull('delivery_projects_id')
                ->firstOrFail();

            // ✅ Create phase baru untuk project ini (One-to-Many)
            $projectPhase = $project->phases()->create([
                'name' => $systemPhase->name,
                'description' => $systemPhase->description,
                'order_sequence' => $validated['orientation'] === 'vertical' 
                    ? ($project->phases()->where('orientation', 'vertical')->max('order_sequence') ?? 0) + 1
                    : ($project->phases()->where('orientation', 'horizontal')->max('order_sequence') ?? 0) + 1,
                'color' => $systemPhase->color,
                'weight' => $validated['weight'],
                'is_visible' => true,
                'is_golive_phase' => $validated['is_golive_phase'] ?? ($systemPhase->name === 'Go-Live & Support'),
                'orientation' => $validated['orientation'],
                'is_optional' => $systemPhase->is_optional,
                'is_active' => true,
                'is_system_default' => false, // Bukan template lagi
                'settings' => $systemPhase->settings,
                'custom_settings' => $validated['custom_settings'] ?? null,
            ]);

            Log::info('✅ Phase added to project', [
                'project_id' => $project->id,
                'phase_id' => $projectPhase->id,
                'weight' => $projectPhase->weight,
                'orientation' => $projectPhase->orientation
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Phase added successfully',
                'phase' => $projectPhase
            ]);
        } catch (\Exception $e) {
            Log::error('Error adding phase: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to add phase: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ FIXED: Update phase - One-to-Many menggunakan update()
     */
    public function updatePhase(Request $request, DeliveryProject $project, $phaseId)
    {
        $validated = $request->validate([
            'weight' => 'nullable|numeric|min:0|max:100',
            'is_visible' => 'nullable|boolean',
            'is_golive_phase' => 'nullable|boolean',
            'custom_settings' => 'nullable|array',
        ]);

        try {
            // ✅ Cari phase milik project ini
            $phase = $project->phases()->where('id', $phaseId)->firstOrFail();

            $updateData = [];
            
            if (isset($validated['weight'])) {
                $updateData['weight'] = $validated['weight'];
            }
            
            if (isset($validated['is_visible'])) {
                $updateData['is_visible'] = $validated['is_visible'];
            }

            if (isset($validated['is_golive_phase'])) {
                $updateData['is_golive_phase'] = $validated['is_golive_phase'];
            }
            
            if (isset($validated['custom_settings'])) {
                $updateData['custom_settings'] = $validated['custom_settings'];
            }

            if (!empty($updateData)) {
                $phase->update($updateData);
            }

            Log::info('✅ Phase updated', [
                'phase_id' => $phaseId,
                'updates' => $updateData
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Phase configuration updated successfully',
                'phase' => $phase
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating phase: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update phase: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ FIXED: Remove phase - One-to-Many menggunakan delete()
     */
    public function removePhase(Request $request, DeliveryProject $project, $phaseId)
    {
        try {
            // ✅ Cek apakah phase ada dan milik project ini
            $phase = $project->phases()->where('id', $phaseId)->firstOrFail();

            $hasActivities = DeliveryProjectPlanning::where('delivery_projects_id', $project->id)
                ->where('phase_id', $phaseId)
                ->exists();

            if ($hasActivities) {
                throw new \Exception('Phase cannot be deleted because it has existing activities');
            }

            // ✅ Hapus phase
            $phase->delete();

            Log::info('✅ Phase removed from project', ['phase_id' => $phaseId]);

            return response()->json([
                'success' => true,
                'message' => 'Phase deleted successfully',
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
    public function reorderPhases(Request $request, DeliveryProject $project)
    {
        $request->validate([
            'phases' => 'required|array',
            'phases.*.id' => 'required|exists:delivery_project_phases,id',
            'phases.*.sequence' => 'required|integer|min:0',
        ]);

        try {
            foreach ($request->phases as $phaseData) {
                // ✅ Update phase milik project
                $phase = $project->phases()->where('id', $phaseData['id'])->firstOrFail();
                $phase->update([
                    'order_sequence' => $phaseData['sequence'],
                ]);
            }

            Log::info('✅ Phases reordered', ['count' => count($request->phases)]);

            return response()->json([
                'success' => true,
                'message' => 'Phase order updated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error reordering phases: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update phase order: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle phase visibility
     */
    public function togglePhaseVisibility(Request $request, DeliveryProject $project, $phaseId)
    {
        try {
            // ✅ Cari phase milik project
            $phase = $project->phases()->where('id', $phaseId)->firstOrFail();
            $currentVisibility = $phase->is_visible;

            $phase->update([
                'is_visible' => !$currentVisibility,
            ]);

            Log::info('✅ Phase visibility toggled', [
                'phase_id' => $phaseId,
                'new_visibility' => !$currentVisibility
            ]);

            return response()->json([
                'success' => true,
                'is_visible' => !$currentVisibility,
                'message' => 'Phase visibility updated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Error toggling phase visibility: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update phase visibility: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update view configuration
     */
    public function updateViewConfig(Request $request, DeliveryProject $project)
    {
        try {
            $validated = $request->validate([
                'default_view' => 'nullable|in:gantt,table,scurve',
                'gantt_settings' => 'nullable|array',
                'table_settings' => 'nullable|array',
                'column_visibility' => 'nullable|array',
            ]);

            if (empty($validated['default_view'])) {
                $validated['default_view'] = $request->input('default_view', 'table');
            }

            Log::info('✅ Updating view config', [
                'delivery_projects_id' => $project->id,
                'data' => $validated
            ]);

            $viewConfig = DeliveryProjectViewConfiguration::updateOrCreate(
                ['delivery_projects_id' => $project->id],
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
                'delivery_projects_id' => $project->id,
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
            'object' => true,
            'receive_type' => true,
            'complexity' => true,
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