<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom `cc_emails` ke tabel ticket.
 *
 * Disalin dari staging_tickets.cc_emails saat approve.
 * Digunakan sebagai sumber CC untuk semua email reply berikutnya
 * pada ticket tersebut, agar CC customer selalu ikut menerima balasan.
 *
 * Format: JSON array of {name, address} atau array of strings
 * Contoh: ["a@b.com","c@d.com"] atau [{"name":"A","address":"a@b.com"}]
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket', function (Blueprint $table) {
            $table->json('cc_emails')->nullable()->after('email_thread_id');
        });
    }

    public function down(): void
    {
        Schema::table('ticket', function (Blueprint $table) {
            $table->dropColumn('cc_emails');
        });
    }
};
