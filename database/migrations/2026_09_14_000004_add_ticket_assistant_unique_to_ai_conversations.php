<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menutup race condition di AiResearchController::openForTicket(): room
 * BERSAMA untuk tiket LAMA (approve()-nya terjadi sebelum room bersama ada)
 * dibuat lewat firstOrCreate(), yang bukan atomic murni — SELECT lalu INSERT,
 * bukan satu operasi. Tanpa constraint ini, dua anggota tim yang klik tombol
 * "Ask AI Research" nyaris bersamaan untuk tiket yang SAMA-SAMA belum punya
 * room bisa sama-sama lolos dari SELECT (belum ada baris) lalu sama-sama
 * INSERT — dua baris "bersama" untuk satu tiket yang sama, memecah thread
 * yang seharusnya satu.
 *
 * unique(ticket_id, assistant) menutup ini di level database: INSERT kedua
 * akan gagal dengan duplicate-key, ditangkap di controller (lihat catch di
 * openForTicket()) yang lalu mengambil baris pemenangnya.
 *
 * AMAN untuk data lama: ticket_id NULL untuk semua room privat/ad-hoc, dan
 * MySQL memperlakukan tiap NULL sebagai berbeda satu sama lain di unique
 * index (tidak dianggap bentrok) — jadi constraint ini TIDAK menyentuh baris
 * mana pun yang ticket_id-nya NULL, cuma menegakkan "maksimal satu baris per
 * (ticket_id, assistant)" untuk baris yang ticket_id-nya TERISI, yaitu
 * persis room bersama yang baru ada sejak fitur ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->unique(['ticket_id', 'assistant'], 'ai_conv_ticket_assistant_unique');
        });
    }

    public function down(): void
    {
        // ticket_id punya foreign key (migrasi add_ticket_id_to_ai_conversations)
        // yang butuh SATU index apa pun ber-prefix ticket_id untuk tetap sah di
        // InnoDB — index tunggal bawaan foreignId() sudah tidak ada lagi begitu
        // unique index komposit ini ada (dibuktikan lewat SHOW INDEX saat
        // migrasi ini ditulis: cuma ai_conv_ticket_assistant_unique yang
        // menutupi ticket_id). Tanpa menambahkan index pengganti DULU, drop di
        // bawah gagal dengan error 1553 "needed in a foreign key constraint".
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->index('ticket_id', 'ai_conv_ticket_fk_idx');
        });

        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropUnique('ai_conv_ticket_assistant_unique');
        });
    }
};
