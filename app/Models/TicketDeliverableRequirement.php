<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-ticket deliverable-document requirement, set from the "Deliverable
 * Documents" window on the ticket page. A row marks a document type as
 * mandatory (is_mandatory) or optional for that one ticket; a document type
 * with no row is optional.
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
