<?php

namespace App\Support;

use App\Models\Ticket;
use App\Services\TicketDeliverableRequirementSync;

/**
 * Reads a ticket's deliverable-document requirements (mandatory vs optional),
 * used to render the completeness checklist in the "Deliverable Documents"
 * modal (resources/views/ticket/show.blade.php).
 *
 * The ticket's own copy (ticket_deliverable_requirements) is kept in step with
 * the live config (deliverable_document_type_ticket_types, managed at
 * Management > Master Ticket Settings > Document Type): every read compares
 * the two and re-syncs on any difference, so a Document Type change can never
 * leave a ticket showing a stale checklist.
 */
final class DeliverableDocumentRequirements
{
    /**
     * @return array<int, array{doc_type: string, mandatory: bool}>|null null when the
     *         ticket has no defined requirement (no ticket_type, or its type
     *         has no config rules).
     */
    public static function forTicket(Ticket $ticket): ?array
    {
        if (!$ticket->ticket_type) {
            return null;
        }

        // Keeps the snapshot aligned with the live Document Type config
        // (also backfills legacy tickets that have no snapshot yet).
        TicketDeliverableRequirementSync::syncIfStale($ticket);

        $rows = $ticket->deliverableRequirements()->orderBy('id')->get();

        if ($rows->isEmpty()) {
            return null;
        }

        return $rows
            ->map(fn ($row) => ['doc_type' => $row->doc_type, 'mandatory' => $row->is_mandatory])
            ->values()
            ->all();
    }
}
