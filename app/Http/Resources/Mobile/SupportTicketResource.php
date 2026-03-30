<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource untuk item delivery support — SupportListScreen.
 *
 * Status diturunkan dari calculated_progress:
 *   0          → "Not Started"
 *   0 < x < 100 → "In Process"
 *   >= 100     → "Closed"
 *
 * progress_percent = calculated_progress / 100  (0.0 – 1.0)
 */
class SupportTicketResource extends JsonResource
{
    public function toArray($request): array
    {
        $progress   = (float) ($this->calculated_progress ?? 0);
        $picEmployee = $this->deliveryOwner;
        $picBd       = $picEmployee?->basicData;
        $picName     = $picBd
            ? trim($picBd->first_name . ' ' . ($picBd->last_name ?? ''))
            : null;

        $teamMembers = $this->relationLoaded('teamMembersList')
            ? $this->teamMembersList->map(fn($m) => [
                'id'   => $m->employee_id,
                'name' => $m->basicData
                    ? trim($m->basicData->first_name . ' ' . ($m->basicData->last_name ?? ''))
                    : null,
            ])->values()
            : [];

        return [
            'id'               => $this->id,
            'title'            => $this->name,
            'status'           => $this->status_label,
            'customer'         => [
                'id'   => $this->client?->customer_id,
                'name' => $this->client?->basicData?->name_1,
            ],
            'pic_user'         => [
                'id'   => $picEmployee?->employee_id,
                'name' => $picName,
            ],
            'start_date'       => $this->start_date?->toDateString(),
            'end_date'         => $this->end_date?->toDateString(),
            'progress_percent' => round($progress / 100, 2),
            'team_members'     => $teamMembers,
        ];
    }
}
