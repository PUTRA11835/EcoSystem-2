<?php

namespace App\Http\Resources\Mobile;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource untuk ProjectDetailScreen (tab Overview + Team + Updates).
 *
 * Expects eager load:
 *   DeliveryProject::with([
 *       'client.basicData',
 *       'deliveryOwner.basicData',
 *       'teamMembers.basicData',
 *       'phases.plannings',
 *       'updates.createdBy.employee.basicData',
 *   ])
 */
class ProjectDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        $owner   = $this->deliveryOwner;
        $ownerBd = $owner?->basicData;
        $ownerName = $ownerBd
            ? trim($ownerBd->first_name . ' ' . ($ownerBd->last_name ?? ''))
            : null;

        // Tab Team: PIC (deliveryOwner) selalu di index 0
        $teamMembers = $this->teamMembers
            ->sortByDesc(fn ($m) => $m->employee_id === $this->delivery_owner_id ? 1 : 0)
            ->values()
            ->map(fn ($m) => [
                'id'   => $m->employee_id,
                'name' => $m->basicData
                    ? trim($m->basicData->first_name . ' ' . ($m->basicData->last_name ?? ''))
                    : null,
            ]);

        // Tab Updates: terbaru dulu
        $updates = $this->updates->sortByDesc('created_at')->values();

        return [
            'id'              => $this->id,
            'customer'        => [
                'id'   => $this->client?->customer_id,
                'name' => $this->client?->basicData?->name_1,
            ],
            'project_type'    => $this->project_type,
            'description'     => $this->description,
            'category_status' => $this->category,
            'track_status'    => $this->status,
            'pic_user'        => [
                'id'   => $owner?->employee_id,
                'name' => $ownerName,
            ],
            'team_members'    => $teamMembers,
            // calculated_progress disimpan 0–100 di DB, mobile butuh 0.0–1.0
            'progress_percent' => round(($this->calculated_progress ?? 0) / 100, 4),
            'start_date'      => $this->start_date ? Carbon::parse($this->start_date)->toDateString() : null,
            'end_date'        => $this->end_date ? Carbon::parse($this->end_date)->toDateString() : null,
            // phases diurutkan by order_sequence (sudah handled di relationship)
            'phases'          => ProjectPhaseResource::collection($this->phases),
            'updates'         => ProjectUpdateResource::collection($updates),
        ];
    }
}
