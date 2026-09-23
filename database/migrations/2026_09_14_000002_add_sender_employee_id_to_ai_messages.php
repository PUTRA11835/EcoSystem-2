<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Atribusi "siapa yang tanya" — cuma relevan untuk room AI Research BERSAMA
 * (lihat 2026_09_14_000001_add_ticket_id_to_ai_conversations.php), di mana
 * lebih dari satu employee bisa menulis ke satu conversation yang sama.
 * NULL untuk baris role 'assistant', NULL juga untuk pesan seed sistem
 * (hasil AI Analyzer yang di-seed otomatis saat approve, bukan pertanyaan
 * orang), dan NULL untuk semua pesan di room privat lama (di sana pengirim
 * sudah pasti si pemilik conversation, tidak perlu dicatat ulang).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->foreignId('sender_employee_id')
                ->nullable()
                ->after('role')
                ->constrained('employee', 'employee_id')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sender_employee_id');
        });
    }
};
