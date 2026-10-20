<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guest tenant (consultant eksternal) yang sudah diundang lewat Graph.
 *
 * Kenapa perlu disimpan: Graph hanya menerima object id atau UPN untuk
 * `users('...')`, dan UPN guest bukan alamat emailnya
 * (`nama_gmail.com#EXT#@tenant.onmicrosoft.com`). Resolusi email -> id lewat
 * GET /users juga tertutup (app ini tidak punya User.Read.All). Satu-satunya
 * sumber id adalah respons POST /invitations — jadi id itu dicatat sekali di
 * sini, dan undangan tidak dikirim ulang ke orang yang sama tiap tiket baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teams_guest_user', function (Blueprint $table) {
            $table->id();

            // Huruf kecil semua; satu email = satu guest.
            $table->string('email', 255)->unique();

            $table->string('aad_object_id', 64);

            // Status dari respons undangan (PendingAcceptance / Completed).
            // Tidak diperbarui setelah orangnya menerima undangan — Graph tidak
            // memberi tahu, dan membacanya butuh User.Read.All. Informatif saja.
            $table->string('invite_status', 32)->nullable();

            $table->timestamp('invited_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams_guest_user');
    }
};
