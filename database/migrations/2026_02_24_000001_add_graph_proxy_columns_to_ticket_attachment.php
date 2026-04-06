<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom yang diperlukan untuk proxy attachment via Microsoft Graph.
 *
 * graph_message_id : Graph internal message ID — diperlukan untuk endpoint
 *                    GET /users/{sender}/messages/{msgId}/attachments/{attId}
 *
 * content_id       : Content-ID (CID) dari inline attachment.
 *                    Dipakai untuk mengganti referensi cid:xxx di HTML email
 *                    dengan URL proxy /attachments/{id}.
 *
 * Catatan: file_path tetap ada untuk backward compatibility (record lama
 * yang sudah disimpan lokal sebelum perubahan strategi ini).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_attachment', function (Blueprint $table) {
            if (!Schema::hasColumn('ticket_attachment', 'graph_message_id')) {
                $table->string('graph_message_id', 500)->nullable()->after('graph_attachment_id');
            }
            if (!Schema::hasColumn('ticket_attachment', 'content_id')) {
                $table->string('content_id', 500)->nullable()->after('graph_message_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_attachment', function (Blueprint $table) {
            foreach (['graph_message_id', 'content_id'] as $col) {
                if (Schema::hasColumn('ticket_attachment', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
