<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Salinan lampiran internal note di OneDrive, untuk ditampilkan sebagai kartu
 * berkas asli di group chat Teams (docs/teams-sync-parity-design.md §6c).
 *
 * Kenapa tabel pendamping dan bukan kolom di `ticket_attachment`: tabel itu
 * dibagi dengan repo JARVIES dan tidak boleh ditambah kolom — pagar yang sama
 * dengan `ticket` dan `ticket_message` (design §2).
 *
 * Gunanya dua:
 *  - satu lampiran hanya diunggah SEKALI, walau pengirimannya diulang;
 *  - jejak salinan mana saja yang ada di OneDrive, supaya bisa dibersihkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_teams_file', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('ticket_id');

            // unique: satu lampiran = satu salinan di OneDrive.
            $table->unsignedBigInteger('ticket_attachment_id')->unique();

            $table->string('drive_item_id', 255);

            // GUID berkas di SharePoint — yang dipakai Teams sebagai `id`
            // lampiran bertipe `reference`.
            $table->string('attachment_guid', 64);

            // URL langsung berkasnya (webDavUrl), BUKAN share link.
            $table->text('content_url');

            // Nama final di OneDrive — bisa berbeda dari nama asli (disanitasi,
            // atau diberi akhiran saat bentrok).
            $table->string('name', 255);

            $table->timestamps();

            $table->index('ticket_id', 'ttf_ticket_idx');
        });

        Schema::table('ticket_teams_chat', function (Blueprint $table) {
            // Folder OneDrive berkas chat tiket ini. Izin baca diberikan di
            // folder, jadi diwarisi semua berkas di dalamnya.
            $table->string('files_folder_id', 255)->nullable()->after('topic');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_teams_chat', function (Blueprint $table) {
            $table->dropColumn('files_folder_id');
        });

        Schema::dropIfExists('ticket_teams_file');
    }
};
