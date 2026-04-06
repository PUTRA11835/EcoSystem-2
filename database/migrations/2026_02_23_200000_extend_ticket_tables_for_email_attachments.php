<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extend ticket tables agar mendukung attachment dari email.
 *
 * ticket_message:
 *   + message_html  : body HTML asli dari email (untuk render rich text & inline image)
 *
 * ticket_attachment:
 *   + file_path     : path relatif di storage (public disk)
 *   + file_name     : nama file asli
 *   + file_size     : ukuran file dalam byte
 *   + mime_type     : content-type (image/png, application/pdf, dll)
 *   + is_inline     : true jika gambar inline CID dalam email
 *   + graph_attachment_id : ID attachment dari MS Graph (untuk referensi)
 *   ~ link_url      : dibuat nullable (dulu required)
 *   ~ uploaded_by_id / uploaded_by_type : dibuat nullable (email sender tidak selalu ada di DB)
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── ticket_message: tambah kolom message_html ────────────────────────
        if (!Schema::hasColumn('ticket_message', 'message_html')) {
            Schema::table('ticket_message', function (Blueprint $table) {
                $table->longText('message_html')->nullable()->after('message');
            });
        }

        // ── ticket_attachment: tambah kolom file info ────────────────────────
        Schema::table('ticket_attachment', function (Blueprint $table) {
            if (!Schema::hasColumn('ticket_attachment', 'file_path')) {
                $table->string('file_path')->nullable()->after('link_url');
            }
            if (!Schema::hasColumn('ticket_attachment', 'file_name')) {
                $table->string('file_name')->nullable()->after('file_path');
            }
            if (!Schema::hasColumn('ticket_attachment', 'file_size')) {
                $table->unsignedBigInteger('file_size')->nullable()->after('file_name');
            }
            if (!Schema::hasColumn('ticket_attachment', 'mime_type')) {
                $table->string('mime_type', 100)->nullable()->after('file_size');
            }
            if (!Schema::hasColumn('ticket_attachment', 'is_inline')) {
                $table->boolean('is_inline')->default(false)->after('mime_type');
            }
            if (!Schema::hasColumn('ticket_attachment', 'graph_attachment_id')) {
                $table->string('graph_attachment_id')->nullable()->after('is_inline');
            }

            // Jadikan nullable agar attachment dari email bisa diinsert tanpa FK ke employee/customer
            $table->string('link_url')->nullable()->change();
            $table->unsignedBigInteger('uploaded_by_id')->nullable()->change();
        });

        // Ganti kolom uploaded_by_type (enum → string) supaya bisa nullable dan terima 'system'
        // Pakai raw SQL karena Laravel tidak bisa ubah enum + nullable sekaligus
        \DB::statement("ALTER TABLE ticket_attachment MODIFY COLUMN uploaded_by_type VARCHAR(20) NULL");
    }

    public function down(): void
    {
        Schema::table('ticket_message', function (Blueprint $table) {
            if (Schema::hasColumn('ticket_message', 'message_html')) {
                $table->dropColumn('message_html');
            }
        });

        Schema::table('ticket_attachment', function (Blueprint $table) {
            foreach (['file_path', 'file_name', 'file_size', 'mime_type', 'is_inline', 'graph_attachment_id'] as $col) {
                if (Schema::hasColumn('ticket_attachment', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
