<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arsip percakapan AI — supaya riwayat tidak lagi hilang saat tab ditutup.
 *
 * DUA LAPIS, dan pembagiannya disengaja:
 *   - cache 'ai_chat'  = KONTEKS KERJA yang dikirim ke API (boleh hilang,
 *     TTL 1 jam, memuat byte lampiran).
 *   - tabel ini        = ARSIP untuk dibaca manusia (teks saja, tanpa lampiran,
 *     tanpa blok server tool).
 *
 * Keduanya tidak digabung karena isi cache harus tetap array polos hasil
 * toHistory(); blok server tool (web_search_tool_result) tidak round-trip
 * dengan andal dan tidak boleh ikut mendarat di sini.
 *
 * Prefiks 'ai_' — bukan 'ai_research_' — karena halaman AI Assistant nanti
 * memakai tabel yang sama, dibedakan lewat kolom 'assistant'.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('employee_id')->constrained('employee', 'employee_id')->cascadeOnDelete();

            // 'research' (AI Research) | 'internal' (AI Assistant, menyusul).
            $table->string('assistant', 20)->default('research');

            // UUID buatan browser; tetap dipakai apa adanya supaya kunci cache
            // ('ai_research:emp{id}:{uuid}') tidak perlu berubah bentuk.
            $table->string('conversation_id', 100);

            $table->string('title', 180);
            $table->string('model_tier', 20)->default('default');

            // Dasar retensi — sengaja bukan updated_at, yang bisa bergerak
            // karena perubahan judul/tier dan membuat percakapan mati
            // seolah-olah masih hidup.
            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();

            $table->unique(['employee_id', 'assistant', 'conversation_id'], 'ai_conv_owner_unique');
            $table->index(['employee_id', 'assistant', 'last_message_at'], 'ai_conv_owner_recent_idx');
            $table->index('last_message_at', 'ai_conv_retention_idx');
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ai_conversation_id')->constrained('ai_conversations')->cascadeOnDelete();

            $table->string('role', 16);             // user | assistant
            $table->longText('content');            // markdown apa adanya

            // Daftar {url, title} hasil pencarian giliran itu.
            $table->json('sources')->nullable();

            /*
             * Lampiran SENGAJA tidak diarsipkan — byte-nya hanya hidup di cache
             * selama percakapan aktif. Yang disimpan cuma jumlahnya, supaya
             * transkrip lama masih bisa menjelaskan "ada 1 gambar di sini"
             * tanpa menyimpan screenshot sistem customer selamanya.
             */
            $table->unsignedTinyInteger('attachment_count')->default(0);

            $table->timestamps();

            $table->index(['ai_conversation_id', 'id'], 'ai_msg_thread_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
