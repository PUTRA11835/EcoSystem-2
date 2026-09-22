<?php

namespace App\Services;

use App\Models\DeliverableDocumentTypeTicketType;
use App\Models\Ticket;

/**
 * Snapshots the live deliverable-document config
 * (deliverable_document_type_ticket_types) into a ticket's own
 * ticket_deliverable_requirements rows, at the moment its ticket_type is set
 * or changed — see Ticket::booted(). Deliberately a one-time copy: running
 * this again later (e.g. after an admin edits the config) does NOT touch
 * tickets that already have a snapshot for their current ticket_type, so an
 * existing ticket's checklist never silently changes under it.
 */
final class TicketDeliverableRequirementSync
{
    /**
     * Replace the ticket's snapshot with the current config for its
     * ticket_type. Call only when the type was just set/changed (or to
     * backfill a legacy ticket that has none yet) — never on every save.
     */
    public static function sync(Ticket $ticket): void
    {
        $ticket->deliverableRequirements()->delete();

        if (!$ticket->ticket_type) {
            return;
        }

        $configRows = DeliverableDocumentTypeTicketType::where('ticket_type', $ticket->ticket_type)
            ->whereHas('documentType', fn ($q) => $q->active())
            ->with('documentType')
            ->get()
            ->sortBy(fn ($row) => $row->documentType->order_seq);

        foreach ($configRows as $row) {
            $ticket->deliverableRequirements()->create([
                'doc_type'     => $row->documentType->name,
                'is_mandatory' => $row->is_mandatory,
            ]);
        }
    }

    /**
     * Sync only if the ticket has no snapshot yet — used for legacy tickets
     * created before this feature existed, so opening their checklist for the
     * first time backfills it instead of showing nothing.
     */
    public static function syncIfMissing(Ticket $ticket): void
    {
        if ($ticket->deliverableRequirements()->doesntExist()) {
            self::sync($ticket);
        }
    }
}
