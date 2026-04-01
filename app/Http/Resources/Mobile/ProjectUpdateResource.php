<?php

namespace App\Http\Resources\Mobile;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource untuk satu update/catatan di tab Updates — ProjectDetailScreen.
 *
 * Catatan: kolom created_by_id tidak ada di tabel existing.
 * Author dikembalikan null untuk update lama.
 * Update baru dari endpoint storeUpdate mengembalikan author langsung dari token.
 */
class ProjectUpdateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'author'     => null,
            'note'       => $this->notes,
            'created_at' => $this->created_at ? Carbon::parse($this->created_at)->toDateString() : null,
        ];
    }
}
