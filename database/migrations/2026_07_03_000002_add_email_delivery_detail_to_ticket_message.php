<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Detail pengiriman email per pesan agar status bisa 3 tingkat:
     * - email_status = 'sent'/null : terkirim ke SEMUA penerima (normal).
     * - email_status = 'partial'   : terkirim ke sebagian; sebagian gagal (alamat salah/bounce).
     * - email_status = 'failed'    : tidak terkirim ke SIAPAPUN.
     *
     * - email_recipients        : daftar SEMUA alamat tujuan (To+CC) saat dikirim — dipakai
     *   untuk memutuskan partial vs total saat NDR/bounce datang belakangan (async).
     * - email_failed_recipients : daftar alamat yang GAGAL (invalid pra-kirim atau bounce),
     *   diakumulasi lintas NDR; dipakai membangun teks alasan.
     */
    public function up(): void
    {
        Schema::table('ticket_message', function (Blueprint $table) {
            $table->json('email_recipients')->nullable()->after('email_error');
            $table->json('email_failed_recipients')->nullable()->after('email_recipients');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_message', function (Blueprint $table) {
            $table->dropColumn(['email_recipients', 'email_failed_recipients']);
        });
    }
};
