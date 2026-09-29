<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Klaim atomic untuk "jawaban pembuka otomatis" room AI Research BERSAMA
 * (lihat AiResearchController::chat() dan resources/views/ai/research.blade.php
 * airTriggerInitial()) — cuma relevan untuk room bersama, di mana lebih dari
 * satu anggota tim bisa membuka room yang sama nyaris bersamaan begitu tiket
 * di-approve. Tanpa klaim ini, dua orang yang buka bareng bisa sama-sama
 * memicu panggilan AI dan mengarsipkan dua jawaban pembuka yang berbeda untuk
 * satu seed yang sama.
 *
 * Pola SAMA PERSIS dengan ai_analysis_status di staging_tickets (klaim via
 * UPDATE ber-syarat, bukan lock aplikasi) — NULL berarti belum pernah
 * diklaim; terisi berarti sedang/sudah dicoba. Staleness window ditangani di
 * kode (bukan kolom terpisah): klaim yang lebih tua dari ambang tertentu
 * boleh diklaim ulang, mengantisipasi request yang mati di tengah jalan
 * sebelum sempat mengarsipkan jawaban.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->timestamp('initial_reply_claimed_at')->nullable()->after('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropColumn('initial_reply_claimed_at');
        });
    }
};
