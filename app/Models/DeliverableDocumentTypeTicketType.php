<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Default row: whether a deliverable document type is mandatory/optional for a
 * given ticket type. Managed from Management > Master Ticket Settings >
 * Document Type. Tickets follow this live until they are customised in their
 * own Deliverable Documents window (see TicketDeliverableRequirement and
 * App\Support\DeliverableRequirements).
 */
class DeliverableDocumentTypeTicketType extends Model
{
    protected $table = 'deliverable_document_type_ticket_types';

    protected $fillable = [
        'document_type_id',
        'ticket_type',
        'is_mandatory',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
    ];

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DeliverableDocumentType::class, 'document_type_id');
    }
}
