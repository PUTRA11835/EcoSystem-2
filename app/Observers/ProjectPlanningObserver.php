<?php

namespace App\Observers;

use App\Models\ProjectPlanning;
use Illuminate\Support\Facades\Log;

class ProjectPlanningObserver
{
    /**
     * Handle the ProjectPlanning "saved" event.
     * Dipanggil setiap kali planning di-save (baik create maupun update)
     */
    public function saved(ProjectPlanning $planning)
    {
        // ✅ Jika ini activity (bukan group) dan punya parent
        if (!$planning->is_group && $planning->parent_id) {
            Log::info("Activity saved, triggering parent group update", [
                'activity_id' => $planning->id,
                'activity_name' => $planning->name,
                'activity_weight' => $planning->weight,
                'parent_id' => $planning->parent_id,
                'progress' => $planning->progress_percentage,
                'status' => $planning->status
            ]);

            $parent = $planning->parent;
            if ($parent && $parent->is_group) {
                $parent->updateGroupStatus();
            }
        }
    }

    /**
     * Handle the ProjectPlanning "updated" event.
     * Dipanggil setiap kali planning di-update
     */
    public function updated(ProjectPlanning $planning)
    {
        // ✅ Jika ini activity (bukan group) dan punya parent
        if (!$planning->is_group && $planning->parent_id) {
            Log::info("Activity updated, triggering parent group update", [
                'activity_id' => $planning->id,
                'activity_name' => $planning->name,
                'activity_weight' => $planning->weight,
                'parent_id' => $planning->parent_id,
                'progress' => $planning->progress_percentage,
                'status' => $planning->status,
                'changes' => $planning->getDirty() // Log apa yang berubah
            ]);

            $parent = $planning->parent;
            if ($parent && $parent->is_group) {
                $parent->updateGroupStatus();
            }
        }
    }

    /**
     * Handle the ProjectPlanning "deleted" event.
     * Dipanggil setiap kali planning dihapus
     */
    public function deleted(ProjectPlanning $planning)
    {
        // ✅ Jika activity yang dihapus punya parent, update parent
        if (!$planning->is_group && $planning->parent_id) {
            Log::info("Activity deleted, triggering parent group update", [
                'activity_id' => $planning->id,
                'activity_name' => $planning->name,
                'parent_id' => $planning->parent_id
            ]);

            $parent = ProjectPlanning::find($planning->parent_id);
            if ($parent && $parent->is_group) {
                $parent->updateGroupStatus();
            }
        }
    }
}