<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mandatory deliverable documents are again configured as a DEFAULT per ticket
 * type (deliverable_document_type_ticket_types, Management > Document Type),
 * with a per-ticket override set from the ticket's Deliverable Documents window
 * (ticket_deliverable_requirements). A ticket with no rows of its own follows
 * the default live.
 *
 * Replaces the abandoned 2026_10_29_000002 migration that dropped the default
 * table. If that table was dropped anywhere before this deploy, it is
 * recreated here with the original seed rules.
 *
 * ticket_deliverable_requirements used to be filled with a copy of the default
 * for every ticket that opened its checklist. Those copies are removed (only
 * when they match the ticket type's default exactly), so those tickets follow
 * the default again; any ticket whose rows differ was set by hand and is kept.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('deliverable_document_type_ticket_types')) {
            Schema::create('deliverable_document_type_ticket_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('document_type_id')->constrained('deliverable_document_types')->cascadeOnDelete();
                $table->string('ticket_type', 50);
                $table->boolean('is_mandatory')->default(false);
                $table->timestamps();

                $table->unique(['document_type_id', 'ticket_type'], 'deliv_doc_type_ticket_type_unique');
            });

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
                        'ticket_type'      => $ticketType,
                        'is_mandatory'     => $isMandatory,
                        'created_at'       => $now,
                        'updated_at'       => $now,
                    ]);
                }
            }
        }

        // Default mandatory doc type names per ticket type (active types only).
        $defaults = DB::table('deliverable_document_type_ticket_types as l')
            ->join('deliverable_document_types as d', 'd.id', '=', 'l.document_type_id')
            ->where('l.is_mandatory', true)
            ->where('d.is_active', true)
            ->get(['l.ticket_type', 'd.name'])
            ->groupBy('ticket_type')
            ->map(fn ($rows) => $rows->pluck('name')->sort()->values()->all());

        $ticketTypes = DB::table('ticket')->pluck('ticket_type', 'ticket_id');

        DB::table('ticket_deliverable_requirements')
            ->get(['ticket_id', 'doc_type', 'is_mandatory'])
            ->groupBy('ticket_id')
            ->each(function ($rows, $ticketId) use ($defaults, $ticketTypes) {
                $mandatory = $rows->where('is_mandatory', true)->pluck('doc_type')->sort()->values()->all();
                $default   = $defaults->get($ticketTypes[$ticketId] ?? '', []);

                if ($mandatory === $default) {
                    DB::table('ticket_deliverable_requirements')->where('ticket_id', $ticketId)->delete();
                }
            });
    }

    public function down(): void
    {
        // Data cleanup only; nothing to restore (the copies were derived from the default).
    }
};
