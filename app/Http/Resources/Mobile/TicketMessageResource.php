<?php

namespace App\Http\Resources\Mobile;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource untuk bubble chat pesan tiket — Tab "Messages" pada TicketDetailScreen.
 *
 * is_from_team: true  → bubble kanan (dari employee/tim)
 * is_from_team: false → bubble kiri  (dari customer)
 */
class TicketMessageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'           => $this->id,
            'sender'       => [
                'id'   => $this->sender_id,
                'name' => $this->sender_name,
            ],
            'content'      => $this->message,
            'is_from_team' => $this->sender_type === 'employee',
            'created_at'   => $this->created_at?->toIso8601String(),
        ];
    }
}
