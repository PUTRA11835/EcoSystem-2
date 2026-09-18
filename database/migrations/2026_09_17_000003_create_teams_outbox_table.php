<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Antrean kirim internal note EcoSystem -> group chat Teams (lewat flow 7).
 *
 * Kenapa antrean, bukan kirim langsung saat note disimpan (design §5):
 *  - user tidak ikut menunggu Power Automate merespons;
 *  - kegagalan bisa di-retry;
 *  - kegagalan TERLIHAT SEBAGAI DATA (status='failed' + last_error), bukan
 *    menghilang jadi satu baris log.
 *
 * Yang ketiga itu pelajaran langsung dari kasus email jadi draft diam-diam
 * (kolom email_status / email_error di ticket_message).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams_outbox', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('ticket_id');
            $table->string('chat_id', 255);

            // unique: satu internal note = satu kiriman. Ini yang menjamin note
            // tidak terkirim dua kali kalau jalur antre kebetulan terpanggil
            // ulang.
            $table->unsignedBigInteger('ticket_message_id')->unique();

            // Teks yang sudah jadi, siap kirim. Disimpan (bukan dirakit ulang
            // saat flush) supaya isi yang terkirim persis isi saat note ditulis,
            // walau note-nya kemudian diedit.
            $table->json('payload');

            $table->enum('status', ['pending', 'sending', 'sent', 'failed'])->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->dateTime('sent_at')->nullable();

            $table->timestamps();

            // Jalur baca command flush: ambil batch pending paling tua dulu.
            $table->index(['status', 'created_at'], 'tob_status_created_idx');
            $table->index('ticket_id', 'tob_ticket_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams_outbox');
    }
};
