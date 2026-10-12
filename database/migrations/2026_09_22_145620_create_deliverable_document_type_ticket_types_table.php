<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Config: which deliverable document types are mandatory/optional per ticket
 * type — a many-to-many between deliverable_document_types and the ticket
 * type enum (App\Support\TicketClassification::TYPES). `ticket_type` is a
 * plain string rather than a FK to a "ticket_types" table because that enum
 * has no table of its own anywhere in this codebase (ticket.ticket_type
 * itself is a plain string column) — adding one just for this join would be
 * inconsistent with how the rest of the app models it.
 *
 * This is the LIVE, editable configuration (Management > Master Ticket
 * Settings > Document Type). It intentionally does NOT drive the checklist
 * on an individual ticket directly — see ticket_deliverable_requirements,
 * which snapshots this config per ticket so a later config change never
 * silently changes what an existing ticket is required to have.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliverable_document_type_ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained('deliverable_document_types')->cascadeOnDelete();
            $table->string('ticket_type', 50);
            $table->boolean('is_mandatory')->default(false);
            $table->timestamps();

            $table->unique(['document_type_id', 'ticket_type'], 'deliv_doc_type_ticket_type_unique');
        });

        // Seed dengan aturan yang sebelumnya hardcoded di
        // App\Support\DeliverableDocumentRequirements::RULES.
        $rules = [
            'Incident' => ['RCA' => true, 'MOM' => false],
            'Change Request' => [
                'CR Form'     => true,
                'FSD'         => true,
                'TD'          => true,
                'UAT'         => true,
                'User Manual' => false,
                'MOM'         => false,
            ],
        ];

        $now = now();
        foreach ($rules as $ticketType => $docTypes) {
            foreach ($docTypes as $docTypeName => $isMandatory) {
                $docTypeId = DB::table('deliverable_document_types')->where('name', $docTypeName)->value('id');
                if (!$docTypeId) {
                    continue;
                }

                DB::table('deliverable_document_type_ticket_types')->insert([
                    'document_type_id' => $docTypeId,
                    'ticket_type'       => $ticketType,
                    'is_mandatory'      => $isMandatory,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deliverable_document_type_ticket_types');
    }
};
