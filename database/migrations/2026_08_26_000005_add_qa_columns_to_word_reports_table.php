<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dukungan "AI boleh bertanya balik" saat proses generate — status baru
 * `awaiting_input` (lihat WordReport::STATUS_AWAITING_INPUT). `question`
 * menyimpan pertanyaan yang sedang menunggu jawaban; `qa_log` riwayat
 * seluruh tanya-jawab putaran ini (dipakai buildPrompt() supaya generate
 * ulang tidak menanyakan hal yang sama).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('word_reports', function (Blueprint $table) {
            $table->text('question')->nullable()->after('instructions');
            $table->json('qa_log')->nullable()->after('question');
        });
    }

    public function down(): void
    {
        Schema::table('word_reports', function (Blueprint $table) {
            $table->dropColumn(['question', 'qa_log']);
        });
    }
};
