<?php

namespace App\Http\Resources\Mobile;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource untuk satu item di ProjectListScreen.
 * Hanya field yang dibutuhkan card preview — tanpa phases & updates.
 */
class ProjectListResource extends JsonResource
{
    public function toArray($request): array
    {
        $owner   = $this->deliveryOwner;
        $ownerBd = $owner?->basicData;
        $ownerName = $ownerBd
            ? trim($ownerBd->first_name . ' ' . ($ownerBd->last_name ?? ''))
            : null;

        // Urutkan team: PIC (deliveryOwner) selalu di index 0
        $teamMembers = $this->teamMembers
            ->sortByDesc(fn ($m) => $m->employee_id === $this->delivery_owner_id ? 1 : 0)
            ->values()
            ->map(fn ($m) => [
                'id'   => $m->employee_id,
                'name' => $m->basicData
                    ? trim($m->basicData->first_name . ' ' . ($m->basicData->last_name ?? ''))
                    : null,
            ]);

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
            // Contract window (manually entered) — boundary for planning dates
            'contract_start_date' => $this->contract_start_date ? Carbon::parse($this->contract_start_date)->toDateString() : null,
            'contract_end_date'   => $this->contract_end_date ? Carbon::parse($this->contract_end_date)->toDateString() : null,
        ];
    }
}
