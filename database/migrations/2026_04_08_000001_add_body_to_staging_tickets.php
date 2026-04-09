<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom `body` ke staging_tickets.
 *
 * Kolom ini menyimpan HTML body dari web form Jarvies (isi deskripsi detail).
 * Sudah ada di StagingTicket model $fillable dan StagingTicketService,
 * namun belum pernah didefinisikan di migration.
 *
 * Saat staging di-approve (channel=web), isi body ini dijadikan
 * TicketMessage pertama agar history chat tidak kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staging_tickets', function (Blueprint $table) {
            // HTML body dari Quill editor (Jarvies web form)
            $table->longText('body')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('staging_tickets', function (Blueprint $table) {
            $table->dropColumn('body');
        });
    }
};
