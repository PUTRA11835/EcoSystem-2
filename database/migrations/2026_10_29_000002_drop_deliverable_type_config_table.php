<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mandatory/optional is no longer a global rule per ticket type. It is now set
 * per ticket from the Deliverable Documents window, stored in
 * ticket_deliverable_requirements. The global config table is dropped and the
 * old rows (snapshots of that global config) are cleared so every ticket
 * starts with all document types optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('deliverable_document_type_ticket_types');
        DB::table('ticket_deliverable_requirements')->delete();
    }

    public function down(): void
    {
        Schema::create('deliverable_document_type_ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_type_id')->constrained('deliverable_document_types')->cascadeOnDelete();
            $table->string('ticket_type', 50);
            $table->boolean('is_mandatory')->default(false);
            $table->timestamps();

            $table->unique(['document_type_id', 'ticket_type'], 'deliv_doc_type_ticket_type_unique');
        });
    }
};
