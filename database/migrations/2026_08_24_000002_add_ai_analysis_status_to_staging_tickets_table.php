<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ticket Analyzer sekarang dipicu otomatis saat admin membuka tiket untuk
 * divalidasi (bukan tombol manual) dan HANYA boleh terjadi sekali per staging
 * ticket — kolom ini yang menegakkan itu secara atomic di level DB:
 *   null      → belum pernah dicoba
 *   pending   → sedang diklaim/diproses (dicegah dobel lewat UPDATE ... WHERE
 *               ai_analysis_status IS NULL, lihat StagingTicketController::analyze())
 *   completed → berhasil, ai_analysis terisi
 *   failed    → sudah dicoba (termasuk retry internal utk error transient) dan
 *               tetap gagal — terminal, tidak ada jalur untuk mencoba lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staging_tickets', function (Blueprint $table) {
            $table->string('ai_analysis_status', 20)->nullable()->after('ai_analysis_generated_by');
        });
    }

    public function down(): void
    {
        Schema::table('staging_tickets', function (Blueprint $table) {
            $table->dropColumn('ai_analysis_status');
        });
    }
};
