<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom `to_emails` ke tabel ticket.
 *
 * Menyimpan daftar recipient TO (primary customer + recipient tambahan yang
 * ditambahkan helpdesk di form reply) agar bertahan antar reply/reload —
 * mirror perilaku `cc_emails`.
 *
 * Format: JSON array of {name, address} atau array of strings
 * Contoh: ["a@b.com","c@d.com"] atau [{"name":"A","address":"a@b.com"}]
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('ticket', 'to_emails')) {
            Schema::table('ticket', function (Blueprint $table) {
                $table->json('to_emails')->nullable()->after('cc_emails');
            });
        }
    }

    public function down(): void
    {
        Schema::table('ticket', function (Blueprint $table) {
            $table->dropColumn('to_emails');
        });
    }
};
