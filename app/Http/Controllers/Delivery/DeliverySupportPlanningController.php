<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\DeliverySupport;
use App\Models\DeliverySupportPhase;
use App\Models\DeliverySupportActivity;
use App\Models\DeliverySupportActivityStage;
use App\Models\DeliverySupportPlanning;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DeliverySupportPlanningController extends Controller
{
    /**
     * Show planning page
     */
    public function index(DeliverySupport $support)
    {
        $support->load([
            'client.basicData',
            'phases' => function ($q) {
                $q->where('is_active', true)->orderBy('order_sequence');
            },
            'activities.phase',
            'activities.stages',
            'activities.employees.basicData',
            'viewConfiguration'
        ]);

        $verticalPhases = $support->phases->where('orientation', 'vertical');
        $horizontalPhases = $support->phases->where('orientation', 'horizontal');

        return view('delivery.support.planning.index', compact(
            'support',
            'verticalPhases',
            'horizontalPhases'
        ));
    }

    /**
     * Get planning data (JSON)
     */
    public function getData(DeliverySupport $support, Request $request)
    {
        try {
            $phaseId = $request->get('phase_id');

            $query = DeliverySupportPlanning::where('delivery_support_id', $support->id)
                ->with(['phase', 'activity', 'stage', 'children'])
                ->orderBy('order_sequence');

            if ($phaseId) {
                $query->where('phase_id', $phaseId);
            }

            $planning = $query->get();

            return response()->json([
                'success' => true,
                'data' => $planning
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting planning data', [
                'support_id' => $support->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load planning data'
            ], 500);
        }
    }

    /**
     * Store planning item (group or activity)
     */
    public function store(Request $request, DeliverySupport $support)
    {
        $validated = $request->validate([
            'phase_id' => 'required|exists:delivery_support_phases,id',
            'parent_id' => 'nullable|exists:delivery_support_planning,id',
            'name' => 'required|string|max:255',
            'group_name' => 'nullable|string|max:255',
            'is_group' => 'boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'weight' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
            'order_sequence' => 'nullable|integer',
        ]);

        try {
            // Calculate level based on parent
            $level = 0;
            if ($validated['parent_id']) {
                $parent = DeliverySupportPlanning::find($validated['parent_id']);
                $level = $parent ? $parent->level + 1 : 0;
            }

            // Get next order sequence
            $orderSequence = DeliverySupportPlanning::where('delivery_support_id', $support->id)
                    ->where('parent_id', $validated['parent_id'] ?? null)
                    ->max('order_sequence') + 1;

            $planning = DeliverySupportPlanning::create([
                'delivery_support_id' => $support->id,
                'phase_id' => $validated['phase_id'],
                'parent_id' => $validated['parent_id'] ?? null,
                'name' => $validated['name'],
                'group_name' => $validated['group_name'] ?? null,
                'is_group' => $validated['is_group'] ?? false,
                'level' => $level,
                'order_sequence' => $orderSequence,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'weight' => $validated['weight'] ?? 0,
                'notes' => $validated['notes'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Planning item created successfully',
                'data' => $planning->load(['phase', 'children'])
            ]);

        } catch (\Exception $e) {
            Log::error('Error creating planning item', [
                'support_id' => $support->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create planning item'
            ], 500);
        }
    }

    /**
     * Update planning item
     */
    public function update(Request $request, DeliverySupport $support, DeliverySupportPlanning $planning)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'group_name' => 'nullable|string|max:255',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'actual_start_date' => 'nullable|date',
            'actual_end_date' => 'nullable|date',
            'weight' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|string',
            'progress_percentage' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        try {
            $planning->update($validated);

            // Update parent progress if applicable
            $this->updateParentProgress($planning);

            // Update support overall progress
            $support->updateCalculatedProgress();

            return response()->json([
                'success' => true,
                'message' => 'Planning item updated successfully',
                'data' => $planning->fresh(['phase', 'children'])
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating planning item', [
                'planning_id' => $planning->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update planning item'
            ], 500);
        }
    }

    /**
     * Delete planning item
     */
    public function destroy(DeliverySupport $support, DeliverySupportPlanning $planning)
    {
        try {
            DB::beginTransaction();

            // Delete children recursively
            $this->deleteChildren($planning);

            // Delete the planning item
            $planning->delete();

            // Update support progress
            $support->updateCalculatedProgress();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Planning item deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting planning item', [
                'planning_id' => $planning->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete planning item'
            ], 500);
        }
    }

    /**
     * Reorder planning items
     */
    public function reorder(Request $request, DeliverySupport $support)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:delivery_support_planning,id',
            'items.*.order_sequence' => 'required|integer|min:0',
        ]);

        try {
            foreach ($validated['items'] as $item) {
                DeliverySupportPlanning::where('id', $item['id'])
                    ->update(['order_sequence' => $item['order_sequence']]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Order updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error reordering planning items', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder items'
            ], 500);
        }
    }

    /**
     * Update parent progress recursively
     */
    protected function updateParentProgress(DeliverySupportPlanning $planning)
    {
        if ($planning->parent_id) {
            $parent = $planning->parent;
            if ($parent) {
                $parent->progress_percentage = $parent->calculateProgressFromChildren();
                $parent->save();
                $this->updateParentProgress($parent);
            }
        }
    }

    /**
     * Delete children recursively
     */
    protected function deleteChildren(DeliverySupportPlanning $planning)
    {
        foreach ($planning->children as $child) {
            $this->deleteChildren($child);
            $child->delete();
        }
    }
}
