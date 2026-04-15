<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\DeliveryProject;
use App\Models\DeliveryGroup;
use App\Models\DeliveryPhase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DeliveryGroupController
 *
 * Mengelola grup dan sub-grup dalam delivery planning.
 * Mendukung unlimited nesting level via parent_id.
 */
class DeliveryGroupController extends Controller
{
    /**
     * Get all groups for a phase
     */
    public function index(DeliveryProject $project, DeliveryPhase $phase)
    {
        try {
            $groups = DeliveryGroup::where('delivery_projects_id', $project->id)
                ->where('phase_id', $phase->id)
                ->rootGroups()
                ->ordered()
                ->with([
                    'children' => function($query) {
                        $query->ordered()->with('children', 'stages');
                    },
                    'stages' => function($query) {
                        $query->ordered();
                    },
                    'directActivities'
                ])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $groups->map(fn($g) => $this->formatGroupWithHierarchy($g))
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting delivery groups', [
                'delivery_projects_id' => $project->id,
                'phase_id' => $phase->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load groups'
            ], 500);
        }
    }

    /**
     * Create new group
     */
    public function store(Request $request, DeliveryProject $project)
    {
        $validated = $request->validate([
            'phase_id' => 'required|exists:delivery_phases,id',
            'parent_id' => 'nullable|exists:delivery_groups,id',
            'name' => 'required|string|max:255',
            'code' => 'nullable|string|max:30',
            'description' => 'nullable|string',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date|after_or_equal:planned_start_date',
            'weight' => 'nullable|numeric|min:0|max:100',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:50',
        ]);

        try {
            return DB::transaction(function () use ($validated, $project) {
                // Determine level
                $level = 0;
                $parentPath = '';

                if (!empty($validated['parent_id'])) {
                    $parent = DeliveryGroup::findOrFail($validated['parent_id']);
                    $level = $parent->level + 1;
                    $parentPath = $parent->path ?? '';
                }

                // Get max sequence
                $query = DeliveryGroup::where('delivery_projects_id', $project->id)
                    ->where('phase_id', $validated['phase_id']);

                if (!empty($validated['parent_id'])) {
                    $query->where('parent_id', $validated['parent_id']);
                } else {
                    $query->whereNull('parent_id');
                }

                $maxSequence = $query->max('order_sequence') ?? 0;

                $group = DeliveryGroup::create([
                    'delivery_projects_id' => $project->id,
                    'phase_id' => $validated['phase_id'],
                    'parent_id' => $validated['parent_id'] ?? null,
                    'name' => $validated['name'],
                    'code' => $validated['code'] ?? null,
                    'description' => $validated['description'] ?? null,
                    'level' => $level,
                    'order_sequence' => $maxSequence + 1,
                    'planned_start_date' => $validated['planned_start_date'] ?? null,
                    'planned_end_date' => $validated['planned_end_date'] ?? null,
                    'weight' => $validated['weight'] ?? 0,
                    'status' => 'not_started',
                    'progress_percentage' => 0,
                    'color' => $validated['color'] ?? null,
                    'icon' => $validated['icon'] ?? null,
                ]);

                // Update materialized path
                $group->path = $parentPath . $group->id . '/';
                $group->saveQuietly();

                Log::info('Delivery group created', [
                    'group_id' => $group->id,
                    'name' => $group->name,
                    'level' => $level
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Group created successfully',
                    'data' => $group->fresh()
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error creating delivery group', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create group'
            ], 500);
        }
    }

    /**
     * Get group details
     */
    public function show(DeliveryProject $project, DeliveryGroup $group)
    {
        try {
            $group->load([
                'children' => function($query) {
                    $query->ordered();
                },
                'stages' => function($query) {
                    $query->ordered()->with('activities');
                },
                'directActivities',
                'parent',
                'phase'
            ]);

            return response()->json([
                'success' => true,
                'data' => $this->formatGroupWithHierarchy($group, true)
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting delivery group', [
                'group_id' => $group->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Group not found'
            ], 404);
        }
    }

    /**
     * Update group
     */
    public function update(Request $request, DeliveryProject $project, DeliveryGroup $group)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'code' => 'nullable|string|max:30',
            'description' => 'nullable|string',
            'planned_start_date' => 'nullable|date',
            'planned_end_date' => 'nullable|date|after_or_equal:planned_start_date',
            'actual_start_date' => 'nullable|date',
            'actual_end_date' => 'nullable|date|after_or_equal:actual_start_date',
            'weight' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|in:not_started,in_progress,completed,delayed,on_hold',
            'progress_percentage' => 'nullable|numeric|min:0|max:100',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
        ]);

        try {
            return DB::transaction(function () use ($validated, $group) {
                if (isset($validated['name'])) $group->name = $validated['name'];
                if (isset($validated['code'])) $group->code = $validated['code'];
                if (isset($validated['description'])) $group->description = $validated['description'];
                if (isset($validated['planned_start_date'])) $group->planned_start_date = $validated['planned_start_date'];
                if (isset($validated['planned_end_date'])) $group->planned_end_date = $validated['planned_end_date'];
                if (isset($validated['actual_start_date'])) $group->actual_start_date = $validated['actual_start_date'];
                if (isset($validated['actual_end_date'])) $group->actual_end_date = $validated['actual_end_date'];
                if (isset($validated['weight'])) $group->weight = $validated['weight'];
                if (isset($validated['status'])) $group->status = $validated['status'];
                if (isset($validated['progress_percentage'])) $group->progress_percentage = $validated['progress_percentage'];
                if (isset($validated['color'])) $group->color = $validated['color'];
                if (isset($validated['icon'])) $group->icon = $validated['icon'];
                if (isset($validated['notes'])) $group->notes = $validated['notes'];

                $group->save();

                // Update parent progress if exists
                if ($group->parent) {
                    $group->parent->updateProgress();
                }

                // Update phase
                if ($group->phase) {
                    $group->phase->updateProgressFromGroups();
                }

                Log::info('Delivery group updated', ['group_id' => $group->id]);

                return response()->json([
                    'success' => true,
                    'message' => 'Group updated successfully',
                    'data' => $group->fresh()
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error updating delivery group', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update group'
            ], 500);
        }
    }

    /**
     * Delete group
     */
    public function destroy(DeliveryProject $project, DeliveryGroup $group)
    {
        try {
            // Check for children
            if ($group->children()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete group with sub-groups. Delete sub-groups first.'
                ], 422);
            }

            // Check for stages
            if ($group->stages()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete group with stages. Delete stages first.'
                ], 422);
            }

            // Check for direct activities
            if ($group->directActivities()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete group with activities. Delete activities first.'
                ], 422);
            }

            return DB::transaction(function () use ($group) {
                $parentId = $group->parent_id;
                $phaseId = $group->phase_id;

                $group->delete();

                // Update parent progress if exists
                if ($parentId) {
                    $parent = DeliveryGroup::find($parentId);
                    if ($parent) {
                        $parent->updateProgress();
                    }
                }

                // Update phase
                if ($phaseId) {
                    $phase = DeliveryPhase::find($phaseId);
                    if ($phase) {
                        $phase->updateProgressFromGroups();
                    }
                }

                Log::info('Delivery group deleted', ['group_id' => $group->id]);

                return response()->json([
                    'success' => true,
                    'message' => 'Group deleted successfully'
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error deleting delivery group', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete group'
            ], 500);
        }
    }

    /**
     * Move group to different parent
     */
    public function move(Request $request, DeliveryProject $project, DeliveryGroup $group)
    {
        $validated = $request->validate([
            'parent_id' => 'nullable|exists:delivery_groups,id',
            'phase_id' => 'nullable|exists:delivery_phases,id',
        ]);

        try {
            return DB::transaction(function () use ($validated, $group) {
                $newParentId = $validated['parent_id'] ?? null;

                // Prevent circular reference
                if ($newParentId) {
                    $newParent = DeliveryGroup::find($newParentId);

                    // Check if new parent is a descendant of this group
                    if ($newParent && str_contains($newParent->path ?? '', $group->id . '/')) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Cannot move group to its own descendant'
                        ], 422);
                    }
                }

                $oldParentId = $group->parent_id;

                // Update group
                $group->parent_id = $newParentId;

                if (isset($validated['phase_id'])) {
                    $group->phase_id = $validated['phase_id'];
                }

                // Update level
                if ($newParentId) {
                    $newParent = DeliveryGroup::find($newParentId);
                    $group->level = $newParent->level + 1;
                } else {
                    $group->level = 0;
                }

                $group->save();

                // Update materialized path
                $group->updatePath();

                // Update old parent progress
                if ($oldParentId) {
                    $oldParent = DeliveryGroup::find($oldParentId);
                    if ($oldParent) {
                        $oldParent->updateProgress();
                    }
                }

                // Update new parent progress
                if ($newParentId) {
                    $newParent = DeliveryGroup::find($newParentId);
                    if ($newParent) {
                        $newParent->updateProgress();
                    }
                }

                Log::info('Group moved', [
                    'group_id' => $group->id,
                    'old_parent' => $oldParentId,
                    'new_parent' => $newParentId
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Group moved successfully',
                    'data' => $group->fresh()
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error moving group', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to move group'
            ], 500);
        }
    }

    /**
     * Reorder groups within same parent
     */
    public function reorder(Request $request, DeliveryProject $project)
    {
        $validated = $request->validate([
            'groups' => 'required|array',
            'groups.*.id' => 'required|exists:delivery_groups,id',
            'groups.*.order_sequence' => 'required|integer|min:1',
        ]);

        try {
            return DB::transaction(function () use ($validated, $project) {
                foreach ($validated['groups'] as $item) {
                    DeliveryGroup::where('id', $item['id'])
                        ->where('delivery_projects_id', $project->id)
                        ->update(['order_sequence' => $item['order_sequence']]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Groups reordered successfully'
                ]);
            });

        } catch (\Exception $e) {
            Log::error('Error reordering groups', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reorder groups'
            ], 500);
        }
    }

    /**
     * Recalculate group progress from children
     */
    public function recalculateProgress(DeliveryProject $project, DeliveryGroup $group)
    {
        try {
            $group->updateProgress();
            $group->updateDates();

            return response()->json([
                'success' => true,
                'message' => 'Progress recalculated',
                'data' => [
                    'progress_percentage' => $group->fresh()->progress_percentage,
                    'status' => $group->fresh()->status,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error recalculating progress', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to recalculate progress'
            ], 500);
        }
    }

    /**
     * Format group with hierarchy for response
     */
    protected function formatGroupWithHierarchy(DeliveryGroup $group, $includeDetails = false): array
    {
        $formatted = [
            'id' => $group->id,
            'delivery_projects_id' => $group->project_id,
            'phase_id' => $group->phase_id,
            'parent_id' => $group->parent_id,
            'name' => $group->name,
            'code' => $group->code,
            'level' => $group->level,
            'order_sequence' => $group->order_sequence,
            'weight' => $group->weight,
            'status' => $group->status,
            'status_text' => $group->status_text,
            'status_badge' => $group->status_badge,
            'progress_percentage' => $group->progress_percentage,
            'planned_start_date' => $group->planned_start_date?->format('Y-m-d'),
            'planned_end_date' => $group->planned_end_date?->format('Y-m-d'),
            'duration_in_days' => $group->duration_in_days,
            'color' => $group->color,
            'icon' => $group->icon,
            'has_children' => $group->has_children,
        ];

        if ($includeDetails) {
            $formatted['description'] = $group->description;
            $formatted['actual_start_date'] = $group->actual_start_date?->format('Y-m-d');
            $formatted['actual_end_date'] = $group->actual_end_date?->format('Y-m-d');
            $formatted['notes'] = $group->notes;
            $formatted['path'] = $group->path;
        }

        // Include children
        if ($group->relationLoaded('children') && $group->children->isNotEmpty()) {
            $formatted['sub_groups'] = $group->children->map(
                fn($child) => $this->formatGroupWithHierarchy($child, $includeDetails)
            )->toArray();
        } else {
            $formatted['sub_groups'] = [];
        }

        // Include stages
        if ($group->relationLoaded('stages') && $group->stages->isNotEmpty()) {
            $formatted['stages'] = $group->stages->map(function($stage) {
                return [
                    'id' => $stage->id,
                    'name' => $stage->name,
                    'code' => $stage->code,
                    'weight' => $stage->weight,
                    'status' => $stage->status,
                    'progress_percentage' => $stage->progress_percentage,
                    'color' => $stage->display_color,
                    'order_sequence' => $stage->order_sequence,
                    'planned_start_date' => $stage->planned_start_date?->format('Y-m-d'),
                    'planned_end_date' => $stage->planned_end_date?->format('Y-m-d'),
                ];
            })->toArray();
        } else {
            $formatted['stages'] = [];
        }

        // Include direct activities
        if ($group->relationLoaded('directActivities') && $group->directActivities->isNotEmpty()) {
            $formatted['direct_activities'] = $group->directActivities->map(function($activity) {
                return [
                    'id' => $activity->id,
                    'name' => $activity->name,
                    'code' => $activity->code,
                    'weight' => $activity->weight,
                    'status' => $activity->status,
                    'progress_percentage' => $activity->progress_percentage,
                    'order_sequence' => $activity->order_sequence,
                    'planned_start_date' => $activity->planned_start_date?->format('Y-m-d'),
                    'planned_end_date' => $activity->planned_end_date?->format('Y-m-d'),
                ];
            })->toArray();
        } else {
            $formatted['direct_activities'] = [];
        }

        return $formatted;
    }
}
