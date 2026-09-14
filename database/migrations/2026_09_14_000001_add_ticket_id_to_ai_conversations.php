<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Room AI Research BERSAMA per tiket — dipicu saat StagingTicketController::
 * approve() (bukan lagi saat tombol "Ask AI Research" diklik pertama kali).
 * ticket_id NULL untuk semua room lama (privat, dibuat lewat klik) DAN untuk
 * conversation AI Research umum yang tidak terkait tiket — cuma diisi untuk
 * room bersama yang baru.
 *
 * Aman dari data lama: room bersama pakai pola conversation_id BARU
 * ("ticket-team-{id}"), beda dari pola lama ("ticket-{id}") yang dipakai
 * room privat — jadi tidak pernah bentrok dengan unique constraint
 * ai_conv_owner_unique (employee_id + assistant + conversation_id) yang
 * sudah ada, dan constraint itu TIDAK perlu diubah sama sekali. employee_id
 * di baris room bersama tetap NOT NULL seperti sekarang, isinya cuma
 * metadata "siapa yang approve tiket ini" — bukan penentu akses (lihat
 * TicketTeamAccess::canAccessAiResearch() yang menggerbangi akses baca/tulis
 * room bersama berdasarkan keanggotaan tim tiket, bukan employee_id ini).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->foreignId('ticket_id')
                ->nullable()
                ->after('employee_id')
                ->constrained('ticket', 'ticket_id')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ticket_id');
        });
    }
};
