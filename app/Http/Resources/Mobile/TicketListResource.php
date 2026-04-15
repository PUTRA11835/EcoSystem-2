<?php

namespace App\Http\Resources\Mobile;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource untuk item tiket di halaman TicketListScreen.
 *
 * Field yang dikembalikan sesuai kolom di tabel web:
 *   last_update, ticket_number, date, customer, pic,
 *   priority, scale (man_days), status, jarvies_status, type.
 */
class TicketListResource extends JsonResource
{
    public function toArray($request): array
    {
        $employee     = $this->employee;
        $basicData    = $employee?->basicData;
        $employeeName = $basicData
            ? trim($basicData->first_name . ' ' . ($basicData->last_name ?? ''))
            : null;

        // last_update: gunakan last_message_at jika ada, fallback ke updated_at
        $lastUpdate = $this->last_message_at ?? $this->updated_at;

        return [
            'id'             => $this->ticket_id,
            'ticket_number'  => $this->ticket_number,
            'description'    => $this->description,

            // Tanggal tiket dibuat
            'date'           => $this->created_at
                ? Carbon::parse($this->created_at)->toDateTimeString()
                : null,

            // Timestamp aktivitas terakhir (pesan masuk / update)
            'last_update'    => $lastUpdate
                ? Carbon::parse($lastUpdate)->toDateTimeString()
                : null,

            'customer'       => [
                'id'   => $this->customer?->customer_id,
                'name' => $this->customer?->basicData?->name_1,
            ],

            // PIC (Person In Charge)
            'pic'            => [
                'id'   => $employee?->employee_id,
                'name' => $employeeName,
            ],

            'priority'       => $this->ticket_priority,

            // Scale = man_days (satuan hari pengerjaan)
            'scale'          => $this->man_days !== null ? (float) $this->man_days : null,

            'status'         => $this->status_label,
            'status_raw'     => $this->status,

            'jarvies_status' => $this->jarvies_status,

            'type'           => $this->ticket_type,
        ];
    }
}
