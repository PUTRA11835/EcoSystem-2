<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "User Manual" adalah salah satu doc type di aturan mandatory/optional
 * deliverable per ticket type (App\Support\DeliverableDocumentRequirements)
 * untuk Change Request, tapi belum ada di master data — tambahkan di sini
 * supaya bisa dipilih di dropdown "New Document" dan dihitung checklist-nya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('deliverable_document_types')->where('name', 'User Manual')->exists()) {
            return;
        }

        $nextSeq = (int) (DB::table('deliverable_document_types')->max('order_seq') ?? 0) + 1;

        DB::table('deliverable_document_types')->insert([
            'name'       => 'User Manual',
            'is_active'  => true,
            'order_seq'  => $nextSeq,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('deliverable_document_types')->where('name', 'User Manual')->delete();
    }
};
