<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom untuk menyimpan data email lengkap di staging_tickets.
 *
 * Sebelumnya email masuk langsung dibuatkan Ticket.
 * Sekarang email masuk disimpan dulu ke staging (channel='email'), dan
 * helpdesk yang memvalidasi apakah layak jadi tiket resmi atau tidak.
 *
 * Saat approve, data kolom ini digunakan untuk membuat TicketMessage pertama
 * + memproses attachment yang ada di email tersebut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staging_tickets', function (Blueprint $table) {
            // internetMessageId dari Graph (bukan Graph internal ID)
            // Digunakan sebagai reference & dedup
            $table->string('email_message_id', 500)->nullable()->after('email_thread_id');

            // Graph internal message ID (format AAMkAG...)
            // Dibutuhkan untuk fetch attachment dari Graph saat approve
            $table->string('graph_message_id', 500)->nullable()->after('email_message_id');

            // Nama pengirim email (display name)
            $table->string('sender_name', 255)->nullable()->after('submitted_by_email');

            // HTML body email yang sudah di-extract (tanpa quoted reply)
            // Disimpan agar bisa jadi isi TicketMessage pertama saat approve
            $table->longText('email_body_html')->nullable()->after('sender_name');

            // Flag: apakah email ini punya attachment
            // Jika true, attachment diproses saat approve
            $table->boolean('has_attachments')->default(false)->after('email_body_html');
        });
    }

    public function down(): void
    {
        Schema::table('staging_tickets', function (Blueprint $table) {
            $table->dropColumn([
                'email_message_id',
                'graph_message_id',
                'sender_name',
                'email_body_html',
                'has_attachments',
            ]);
        });
    }
};
