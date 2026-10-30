<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-ticket deliverable-document requirement, set from the "Deliverable
 * Documents" window on the ticket page. It overrides the default of the
 * ticket's type (deliverable_document_type_ticket_types): a ticket with no rows
 * follows the default, a ticket with rows uses exactly them (is_mandatory
 * true/false per active document type). Rows are dropped when the ticket's
 * type changes.
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
