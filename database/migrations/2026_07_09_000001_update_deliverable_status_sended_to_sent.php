<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Samakan label status dokumen deliverable: "Sended" → "Sent".
     * Baris lama yang sudah dikirim ke customer perlu ikut diselaraskan agar
     * gating edit/delete/send di frontend & backend tetap konsisten.
     */
    public function up(): void
    {
        DB::table('ticket_deliverables')
            ->where('status', 'Sended')
            ->update(['status' => 'Sent']);
    }

    public function down(): void
    {
        DB::table('ticket_deliverables')
            ->where('status', 'Sent')
            ->update(['status' => 'Sended']);
    }
};
