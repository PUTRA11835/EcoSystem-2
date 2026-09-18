<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Satu baris per tiket yang punya group chat Teams.
 *
 * TANPA foreign key ke `ticket` — tabel itu dibagi dengan repo Jarvies, dan
 * rancangan ini menetapkan tidak ada perubahan struktur pada tabel bersama
 * (docs/teams-chat-sync-design.md §2). Integritasnya dijaga di sisi aplikasi.
 *
 * Tabel ini juga memegang kursor polling: `last_message_at` adalah
 * lastModifiedDateTime pesan terakhir yang SUDAH diserap, dipakai langsung
 * sebagai $filter ke Graph.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_teams_chat', function (Blueprint $table) {
            $table->id();

            // unique: satu tiket hanya boleh punya satu group chat.
            $table->unsignedBigInteger('ticket_id')->unique();

            // id chat Teams, bentuknya "19:xxxxx@thread.v2".
            $table->string('chat_id', 255)->unique();

            // Nama grup saat dibuat. Untuk diagnosa saja — pencarian chat SELALU
            // lewat chat_id, tidak pernah dengan mencocokkan topic (itu pola
            // rapuh yang ditinggalkan bersama flow 5).
            $table->string('topic', 255)->nullable();

            $table->enum('created_via', ['graph', 'manual'])->default('graph');

            // Kill switch per tiket. Dimatikan manual, atau otomatis oleh
            // ambang poll_failures.
            $table->boolean('sync_enabled')->default(true);

            // Kursor polling. Diisi waktu SEKARANG saat baris dibuat: riwayat
            // chat sebelum fitur menyala sengaja tidak ditarik (design §7).
            $table->dateTime('last_message_at')->nullable();
            $table->dateTime('last_polled_at')->nullable();

            // Kegagalan polling beruntun. Direset ke 0 setiap polling berhasil;
            // melewati services.teams_sync.max_poll_failures -> sync_enabled
            // dimatikan + Log::warning, supaya chat yang chat_id-nya sudah mati
            // tidak dipanggil selamanya tiap menit.
            $table->unsignedInteger('poll_failures')->default(0);

            $table->timestamps();

            // Jalur baca command polling: ambil chat aktif, yang paling lama
            // tidak dipoll didahulukan.
            $table->index(['sync_enabled', 'last_polled_at'], 'ttc_active_polled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_teams_chat');
    }
};
