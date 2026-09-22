<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single ticket's snapshotted deliverable-document requirement — a copy of
 * deliverable_document_type_ticket_types taken when the ticket's type was
 * set (see Ticket::booted() and App\Services\TicketDeliverableRequirementSync).
 * Drives the completeness checklist in the Deliverable Documents modal
 * (resources/views/ticket/show.blade.php).
 */
class TicketDeliverableRequirement extends Model
{
    protected $fillable = [
        'ticket_id',
        'doc_type',
        'is_mandatory',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }
}
