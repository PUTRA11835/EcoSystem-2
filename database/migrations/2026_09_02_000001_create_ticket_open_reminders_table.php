<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak reminder "tiket masih Open" yang dikirim ke Microsoft Teams lewat
 * Power Automate.
 *
 * Tabel terpisah, BUKAN kolom tambahan di `ticket`: tabel `ticket` dipakai
 * bersama repo JARVIES (customer side), jadi menambah kolom di sana menyeret
 * aplikasi lain ikut memikirkan kolom yang tidak ada urusannya dengan mereka.
 *
 * Barisnya juga yang menjaga scheduler tetap idempoten — kalau satu run
 * kebetulan tumpang tindih dengan run berikutnya, last_sent_at membuat tiket
 * yang sama tidak dikirimi dua kali dalam satu interval.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_open_reminders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id')->unique();
            $table->unsignedInteger('sent_count')->default(0);
            $table->timestamp('first_sent_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamps();

            // Scheduler memilih kandidat dengan mengurutkan last_sent_at.
            $table->index('last_sent_at');

            $table->foreign('ticket_id')
                  ->references('ticket_id')->on('ticket')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_open_reminders');
    }
};
