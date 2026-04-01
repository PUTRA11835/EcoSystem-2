<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource untuk satu phase di tab Overview — ProjectDetailScreen.
 *
 * Expects phase sudah di-eager-load bersama plannings:
 *   DeliveryProject::with(['phases.plannings'])
 *
 * Status phase dihitung dari planning items (is_group = false):
 *   Closed     → semua planning berstatus "completed"
 *   In Process → ada planning berstatus "in_progress" atau "delayed"
 *   Open       → selain kondisi di atas
 *
 * Progress dihitung dari avg(progress_percentage) planning items, dibagi 100.
 */
class ProjectPhaseResource extends JsonResource
{
    public function toArray($request): array
    {
        // Gunakan plannings yang sudah di-eager-load, filter hanya aktivitas (bukan group)
        $activities = $this->plannings->where('is_group', false);

        $progress = 0.0;
        $status   = 'Open';

        if ($activities->isNotEmpty()) {
            $avgProgress = $activities->avg('progress_percentage') ?? 0;
            $progress    = round($avgProgress / 100, 4);

            $allCompleted  = $activities->where('status', '!=', 'completed')->isEmpty();
            $hasInProgress = $activities->whereIn('status', ['in_progress', 'delayed'])->isNotEmpty();

            if ($allCompleted) {
                $status = 'Closed';
            } elseif ($hasInProgress) {
                $status = 'In Process';
            }
        }

        return [
            'id'       => $this->id,
            'name'     => $this->name,
            'progress' => $progress,
            'status'   => $status,
        ];
    }
}
