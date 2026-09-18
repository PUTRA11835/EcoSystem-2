<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jembatan antara satu baris `ticket_message` dan satu pesan di Teams.
 *
 * Tabel pendamping, BUKAN kolom baru di `ticket_message` — tabel itu dibagi
 * dengan repo Jarvies (design §2).
 *
 * Unique (chat_id, teams_message_id) adalah jantung anti-loop dan anti-duplikat.
 * Dua alasan kenapa bentuknya gabungan dan kenapa ia wajib, keduanya terbukti
 * di spike 17 Sep 2026:
 *
 *  1. teams_message_id ternyata epoch-milidetik 13 digit (mis. 1789614743752),
 *     sama dengan createdDateTime — TIDAK unik lintas chat. Unique pada kolomnya
 *     sendiri akan salah menolak pesan sah dari chat lain.
 *  2. Pesan bergambar punya lastModifiedDateTime ~7 detik SESUDAH createdDateTime
 *     (Teams memproses gambar belakangan; lastEditedDateTime tetap null), jadi
 *     pesan yang sudah diserap MUNCUL LAGI di jendela polling berikutnya. Tanpa
 *     dedupe ini, pesan bergambar pasti dobel — bukan mungkin dobel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_message_teams', function (Blueprint $table) {
            $table->id();

            // Satu ticket_message hanya boleh punya satu pasangan Teams.
            $table->unsignedBigInteger('ticket_message_id')->unique();

            $table->string('chat_id', 255);

            // NULL selama pesan keluar belum dikonfirmasi id-nya oleh Teams —
            // flow Power Automate tidak selalu mengembalikan id pesan.
            $table->string('teams_message_id', 64)->nullable();

            // 'in'  = berasal dari Teams, diserap jadi internal note
            // 'out' = internal note EcoSystem yang dikirim ke Teams
            // Dipakai juga sebagai pagar arah keluar: yang 'in' TIDAK PERNAH
            // dikirim balik ke Teams (design §8).
            $table->enum('direction', ['in', 'out']);

            $table->timestamps();

            // Tidak ada index tambahan untuk ticket_message_id: kolom itu sudah
            // unique di atas, dan unique index sudah melayani pencarian.
            $table->unique(['chat_id', 'teams_message_id'], 'tmt_chat_message_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_message_teams');
    }
};
