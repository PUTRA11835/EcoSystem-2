<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\DeliverySupport;
use App\Models\DeliverySupportPhase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DeliverySupportPhaseController
 *
 * Manages phases for support delivery items
 */
class DeliverySupportPhaseController extends Controller
{
    /**
     * Get all phases for a support item
     */
    public function index(DeliverySupport $support)
    {
        try {
            $phases = DeliverySupportPhase::where('delivery_support_id', $support->id)
                ->orderBy('order_sequence')
                ->get()
                ->map(function ($phase) {
                    return [
                        'id' => $phase->id,
                        'name' => $phase->name,
                        'description' => $phase->description,
                        'color' => $phase->color,
                        'order_sequence' => $phase->order_sequence,
                        'weight' => $phase->weight,
                        'orientation' => $phase->orientation,
                        'is_active' => $phase->is_active,
                        'is_system_default' => $phase->is_system_default,
                        'is_resolution_phase' => $phase->is_resolution_phase,
                        'is_visible' => $phase->is_visible,
                        'is_optional' => $phase->is_optional,
                        'activities_count' => $phase->activities()->count(),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $phases
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting support phases', [
                'support_id' => $support->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load phases'
            ], 500);
        }
    }

    /**
     * Create new phase
     */
    public function store(Request $request, DeliverySupport $support)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'weight' => 'nullable|numeric|min:0|max:100',
            'orientation' => 'nullable|in:vertical,horizontal',
            'is_resolution_phase' => 'boolean',
            'is_optional' => 'boolean',
        ]);

        try {
            // Check weight limit
            if (isset($validated['weight'])) {
                $currentTotal = DeliverySupportPhase::where('delivery_support_id', $support->id)
                    ->sum('weight');
                if (($currentTotal + $validated['weight']) > 100.01) {
                    return response()->json([
                        'success' => false,
                        'message' => "Total weight would exceed 100%. Current: {$currentTotal}%"
                    ], 422);
                }
            }

            $maxSequence = DeliverySupportPhase::where('delivery_support_id', $support->id)
                ->max('order_sequence') ?? 0;

            $phase = DeliverySupportPhase::create([
                'delivery_support_id' => $support->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'color' => $validated['color'] ?? '#3B82F6',
                'order_sequence' => $maxSequence + 1,
                'weight' => $validated['weight'] ?? 0,
                'orientation' => $validated['orientation'] ?? 'vertical',
                'is_resolution_phase' => $validated['is_resolution_phase'] ?? false,
                'is_optional' => $validated['is_optional'] ?? false,
                'is_active' => true,
                'is_visible' => true,
            ]);

            Log::info('Support phase created', [
                'phase_id' => $phase->id,
                'support_id' => $support->id,
                'name' => $phase->name
            ]);

            // Progress support = rata-rata TERBOBOT antar phase, jadi menambah phase
            // ikut mengubah total weight → harus dihitung ulang, kalau tidak nilai
            // tersimpan (badge + filter status di index) jadi basi.
            $support->updateCalculatedProgress();

            return response()->json([
                'success' => true,
                'message' => 'Phase created successfully',
                'data' => $phase
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating support phase', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create phase'
            ], 500);
        }
    }

    /**
     * Get phase details
     */
    public function show(DeliverySupport $support, DeliverySupportPhase $phase)
    {
        try {
            $phase->load(['activities', 'children']);

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $phase->id,
                    'delivery_support_id' => $phase->delivery_support_id,
                    'name' => $phase->name,
                    'description' => $phase->description,
                    'color' => $phase->color,
                    'order_sequence' => $phase->order_sequence,
                    'weight' => $phase->weight,
                    'orientation' => $phase->orientation,
                    'is_active' => $phase->is_active,
                    'is_visible' => $phase->is_visible,
                    'is_system_default' => $phase->is_system_default,
                    'is_resolution_phase' => $phase->is_resolution_phase,
                    'is_optional' => $phase->is_optional,
                    'activities_count' => $phase->activities->count(),
                    'settings' => $phase->settings,
                    'custom_settings' => $phase->custom_settings,
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
    public function update(Request $request, DeliverySupport $support, DeliverySupportPhase $phase)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'weight' => 'nullable|numeric|min:0|max:100',
            'orientation' => 'nullable|in:vertical,horizontal',
            'is_active' => 'boolean',
            'is_visible' => 'boolean',
            'is_resolution_phase' => 'boolean',
            'is_optional' => 'boolean',
            'settings' => 'nullable|array',
            'custom_settings' => 'nullable|array',
        ]);

        try {
            // Check weight if changing
            if (isset($validated['weight']) && $validated['weight'] != $phase->weight) {
                $currentTotal = DeliverySupportPhase::where('delivery_support_id', $support->id)
                    ->where('id', '!=', $phase->id)
                    ->sum('weight');
                if (($currentTotal + $validated['weight']) > 100.01) {
                    return response()->json([
                        'success' => false,
                        'message' => "Total weight would exceed 100%. Current (excluding this): {$currentTotal}%"
                    ], 422);
                }
            }

            $phase->fill($validated);
            $phase->save();

            Log::info('Support phase updated', ['phase_id' => $phase->id]);

            // Weight phase berubah → bobot progress ikut berubah.
            $support->updateCalculatedProgress();

            return response()->json([
                'success' => true,
                'message' => 'Phase updated successfully',
                'data' => $phase->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating support phase', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update phase'
            ], 500);
        }
    }

    /**
     * Delete phase
     */
    public function destroy(DeliverySupport $support, DeliverySupportPhase $phase)
    {
        try {
            // Check if phase has activities
            if ($phase->activities()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete phase with existing activities. Delete activities first.'
                ], 422);
            }

            $phase->delete();

            Log::info('Support phase deleted', ['phase_id' => $phase->id]);

            $support->updateCalculatedProgress();

            return response()->json([
                'success' => true,
                'message' => 'Phase deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting support phase', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete phase'
            ], 500);
        }
    }

    /**
     * Reorder phases
     */
    public function reorder(Request $request, DeliverySupport $support)
    {
        $validated = $request->validate([
            'phases' => 'required|array',
            'phases.*.id' => 'required|exists:delivery_support_phases,id',
            'phases.*.order_sequence' => 'required|integer|min:1',
        ]);

        try {
            return DB::transaction(function () use ($validated, $support) {
                foreach ($validated['phases'] as $item) {
                    DeliverySupportPhase::where('id', $item['id'])
                        ->where('delivery_support_id', $support->id)
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
     * Toggle phase visibility
     */
    public function toggleVisibility(DeliverySupport $support, DeliverySupportPhase $phase)
    {
        try {
            $phase->is_visible = !$phase->is_visible;
            $phase->save();

            // calculateProgress() hanya menghitung phase yang visible.
            $support->updateCalculatedProgress();

            return response()->json([
                'success' => true,
                'message' => 'Phase visibility toggled',
                'data' => ['is_visible' => $phase->is_visible]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle visibility'
            ], 500);
        }
    }

    /**
     * Batch update phases (for phase modal batch submission)
     */
    public function batchUpdate(Request $request, DeliverySupport $support)
    {
        $validated = $request->validate([
            'phases' => 'required|array',
            'phases.*.id' => 'nullable|exists:delivery_support_phases,id',
            'phases.*.name' => 'required|string|max:255',
            'phases.*.description' => 'nullable|string',
            'phases.*.color' => 'nullable|string|max:7',
            'phases.*.weight' => 'nullable|numeric|min:0|max:100',
            'phases.*.orientation' => 'nullable|in:vertical,horizontal',
            'phases.*.order_sequence' => 'required|integer|min:0',
            'phases.*.is_active' => 'boolean',
            'phases.*.is_visible' => 'boolean',
            'phases.*._action' => 'nullable|in:create,update,delete',
        ]);

        try {
            return DB::transaction(function () use ($validated, $support) {
                $result = ['created' => 0, 'updated' => 0, 'deleted' => 0];

                // First pass: delete
                foreach ($validated['phases'] as $phaseData) {
                    if (($phaseData['_action'] ?? '') === 'delete' && !empty($phaseData['id'])) {
                        $phase = DeliverySupportPhase::find($phaseData['id']);
                        if ($phase && $phase->delivery_support_id == $support->id) {
                            $phase->delete();
                            $result['deleted']++;
                        }
                    }
                }

                // Second pass: update
                foreach ($validated['phases'] as $phaseData) {
                    if (($phaseData['_action'] ?? '') === 'update' && !empty($phaseData['id'])) {
                        $phase = DeliverySupportPhase::find($phaseData['id']);
                        if ($phase && $phase->delivery_support_id == $support->id) {
                            $phase->update([
                                'name' => $phaseData['name'],
                                'description' => $phaseData['description'] ?? null,
                                'color' => $phaseData['color'] ?? '#3B82F6',
                                'weight' => $phaseData['weight'] ?? 0,
                                'orientation' => $phaseData['orientation'] ?? 'vertical',
                                'order_sequence' => $phaseData['order_sequence'],
                                'is_active' => $phaseData['is_active'] ?? true,
                                'is_visible' => $phaseData['is_visible'] ?? true,
                            ]);
                            $result['updated']++;
                        }
                    }
                }

                // Third pass: create
                foreach ($validated['phases'] as $phaseData) {
                    if (($phaseData['_action'] ?? '') === 'create') {
                        DeliverySupportPhase::create([
                            'delivery_support_id' => $support->id,
                            'name' => $phaseData['name'],
                            'description' => $phaseData['description'] ?? null,
                            'color' => $phaseData['color'] ?? '#3B82F6',
                            'weight' => $phaseData['weight'] ?? 0,
                            'orientation' => $phaseData['orientation'] ?? 'vertical',
                            'order_sequence' => $phaseData['order_sequence'],
                            'is_active' => $phaseData['is_active'] ?? true,
                            'is_visible' => $phaseData['is_visible'] ?? true,
                        ]);
                        $result['created']++;
                    }
                }

                // Batch bisa membuat/menghapus/mengubah weight sekaligus → hitung
                // ulang sekali di akhir, setelah semua perubahan phase tersimpan.
                $support->updateCalculatedProgress();

                return response()->json([
                    'success' => true,
                    'message' => "Phases updated: {$result['created']} created, {$result['updated']} updated, {$result['deleted']} deleted",
                    'data' => $result
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error batch updating phases', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update phases'
            ], 500);
        }
    }
}
