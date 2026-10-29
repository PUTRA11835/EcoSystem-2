<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Master data tipe dokumen deliverable (mis. IR, RCA, CR Form, ...), dipakai
 * di dropdown "New Document" pada Deliverable Panel menu Ticket. Menggantikan
 * daftar hardcoded lama di TicketDeliverableController::DOC_TYPES.
 */
class DeliverableDocumentType extends Model
{
    protected $table = 'deliverable_document_types';

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'order_seq',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
