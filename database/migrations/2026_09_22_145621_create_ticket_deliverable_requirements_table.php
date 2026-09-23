<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot of which deliverable documents a SPECIFIC ticket needs, taken from
 * deliverable_document_type_ticket_types at the moment the ticket's type was
 * set (see Ticket::booted()'s `saved` hook / TicketDeliverableRequirementSync).
 *
 * This is deliberately a copy, not a live join — the requirement list for an
 * existing ticket must stay exactly as it was when the ticket got its type,
 * even if an admin later adds/removes/reclassifies document types in the
 * master data. `doc_type` is stored as a plain string (like
 * ticket_deliverables.doc_type) rather than an FK, so a requirement row
 * still makes sense even if the document type it was copied from is later
 * renamed or deleted from master data.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_deliverable_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('ticket', 'ticket_id')->cascadeOnDelete();
            $table->string('doc_type', 50);
            $table->boolean('is_mandatory')->default(false);
            $table->timestamps();

            $table->unique(['ticket_id', 'doc_type'], 'ticket_deliv_req_ticket_doc_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_deliverable_requirements');
    }
};
