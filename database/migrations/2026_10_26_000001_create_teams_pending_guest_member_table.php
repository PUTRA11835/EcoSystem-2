<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consultant eksternal (guest) yang menunggu dimasukkan ke group chat tiket.
 *
 * Kenapa perlu antrean: guest yang BARU SAJA diundang belum dikenal Teams.
 * Entra langsung memberi object id-nya, tetapi direktori Teams baru menyusul
 * beberapa menit kemudian — selama jeda itu POST /chats yang memuatnya ditolak
 * SELURUHNYA (403 "One or more members cannot be added to the thread roster",
 * tiket 26100280, 26 Okt 2026). Chat yang sama dengan anggota yang sama
 * berhasil ±12 menit kemudian.
 *
 * Jadi chat dibuat dulu tanpa guest itu, lalu guest-nya dimasukkan susulan oleh
 * teams:add-pending-guests. Kegagalan yang tidak kunjung pulih berhenti di
 * status 'failed' dengan last_error terisi — terlihat sebagai data, seperti
 * teams_outbox.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams_pending_guest_member', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('ticket_id');

            // Huruf kecil, sama dengan teams_guest_user.email.
            $table->string('email', 255);

            $table->enum('status', ['pending', 'added', 'failed'])->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->dateTime('next_attempt_at');
            $table->text('last_error')->nullable();
            $table->dateTime('added_at')->nullable();

            // Payload flow 6 ("ticket member added") yang dikirim SETELAH guest
            // masuk grup — pesan sambutan + @mention baru berarti kalau orangnya
            // sudah anggota. Null untuk guest dari approve tiket: mereka sudah
            // disebut di kartu flow 5.
            $table->json('flow_payload')->nullable();

            $table->timestamps();

            // Satu guest per tiket; penundaan ulang memperbarui baris yang sama.
            $table->unique(['ticket_id', 'email'], 'tpgm_ticket_email_unique');
            $table->index(['status', 'next_attempt_at'], 'tpgm_status_next_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams_pending_guest_member');
    }
};
