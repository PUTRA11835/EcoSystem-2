<?php

namespace App\Services;

use App\Models\DeliverableDocumentTypeTicketType;
use App\Models\Ticket;

/**
 * Copies the live deliverable-document config
 * (deliverable_document_type_ticket_types) into a ticket's own
 * ticket_deliverable_requirements rows when its ticket_type is set or changed
 * (see Ticket::booted()), and again via syncIfStale() whenever the config has
 * drifted from that copy (see DeliverableDocumentRequirements::forTicket()).
 */
final class TicketDeliverableRequirementSync
{
    /**
     * Replace the ticket's snapshot with the current config for its
     * ticket_type. Call when the type was just set/changed, or from
     * syncIfStale() when the config has changed.
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

    /**
     * Re-sync when the ticket's snapshot no longer matches the live config
     * (e.g. an admin added/changed a Document Type rule after the ticket was
     * created), so Settings changes show up in the checklist.
     */
    public static function syncIfStale(Ticket $ticket): void
    {
        if (!$ticket->ticket_type) {
            return;
        }

        $signature = fn ($rows) => $rows
            ->map(fn ($r) => $r['doc_type'] . ':' . (int) $r['is_mandatory'])
            ->sort()->values()->all();

        $live = DeliverableDocumentTypeTicketType::where('ticket_type', $ticket->ticket_type)
            ->whereHas('documentType', fn ($q) => $q->active())
            ->with('documentType')
            ->get()
            ->map(fn ($row) => ['doc_type' => $row->documentType->name, 'is_mandatory' => $row->is_mandatory]);

        $snapshot = $ticket->deliverableRequirements()->get(['doc_type', 'is_mandatory'])
            ->map(fn ($row) => ['doc_type' => $row->doc_type, 'is_mandatory' => $row->is_mandatory]);

        if ($signature($live) !== $signature($snapshot)) {
            self::sync($ticket);
        }
    }
}
