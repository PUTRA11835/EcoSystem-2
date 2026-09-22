<?php

namespace App\Support;

use App\Models\Ticket;
use App\Services\TicketDeliverableRequirementSync;

/**
 * Reads a ticket's SNAPSHOTTED deliverable-document requirements (mandatory
 * vs optional), used to render the completeness checklist in the
 * "Deliverable Documents" modal (resources/views/ticket/show.blade.php).
 *
 * The snapshot (ticket_deliverable_requirements) is what actually backs
 * this — taken from the live config (deliverable_document_type_ticket_types,
 * managed at Management > Master Ticket Settings > Document Type) the moment
 * the ticket's type was set. Reading the ticket's own snapshot rather than
 * the live config means a later config change never silently changes what
 * an already-existing ticket is required to have.
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

        // Legacy tickets created before this feature has no snapshot yet —
        // backfill once from the current config so their checklist isn't
        // just empty forever.
        TicketDeliverableRequirementSync::syncIfMissing($ticket);

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
