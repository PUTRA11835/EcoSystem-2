<?php

namespace App\Http\Resources\Mobile;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource untuk detail tiket — Tab "Information" pada TicketDetailScreen.
 */
class TicketDetailResource extends JsonResource
{
    public function toArray($request): array
    {
        $employee      = $this->employee;
        $employeeBd    = $employee?->basicData;
        $employeeName  = $employeeBd
            ? trim($employeeBd->first_name . ' ' . ($employeeBd->last_name ?? ''))
            : null;

        $members = $this->relationLoaded('members')
            ? $this->members->map(fn($m) => [
                'id'   => $m->employee_id,
                'name' => $m->basicData
                    ? trim($m->basicData->first_name . ' ' . ($m->basicData->last_name ?? ''))
                    : null,
            ])->values()
            : [];

        return [
            'id'             => $this->ticket_id,
            'title'          => $this->subject,
            'description'    => $this->description,
            'status'         => $this->status_label,
            'priority'       => $this->ticket_priority,
            'type'           => $this->category,
            'jarvies_status' => $this->jarvies_status,
            'customer'       => [
                'id'   => $this->customer?->customer_id,
                'name' => $this->customer?->basicData?->name_1,
            ],
            'assigned_user'  => [
                'id'   => $employee?->employee_id,
                'name' => $employeeName,
            ],
            'members'        => $members,
            'created_at'     => $this->created_at ? Carbon::parse($this->created_at)->toDateString() : null,
            'updated_at'     => $this->updated_at ? Carbon::parse($this->updated_at)->toDateString() : null,
        ];
    }
}
