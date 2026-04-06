<?php

namespace App\Http\Resources\Mobile;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource untuk item tiket di halaman TicketListScreen.
 * Hanya field yang dibutuhkan card preview.
 */
class TicketListResource extends JsonResource
{
    public function toArray($request): array
    {
        $employee  = $this->employee;
        $basicData = $employee?->basicData;
        $employeeName = $basicData
            ? trim($basicData->first_name . ' ' . ($basicData->last_name ?? ''))
            : null;

        return [
            'id'            => $this->ticket_id,
            'title'         => $this->subject,
            'status'        => $this->status_label,
            'priority'      => $this->ticket_priority,
            'customer'      => [
                'id'   => $this->customer?->customer_id,
                'name' => $this->customer?->basicData?->name_1,
            ],
            'assigned_user' => [
                'id'   => $employee?->employee_id,
                'name' => $employeeName,
            ],
            'updated_at'    => $this->updated_at ? Carbon::parse($this->updated_at)->toDateString() : null,
        ];
    }
}
