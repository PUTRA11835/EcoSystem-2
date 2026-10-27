<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Undangan guest dikirim EcoSystem sendiri, bukan oleh Microsoft.
 *
 * Email undangan bawaan Microsoft ("Microsoft Invitations on behalf of ...")
 * masuk folder Spam Gmail (26 Okt 2026): dikirim infrastruktur Microsoft atas
 * nama domain kita, sementara DMARC eclectic.co.id = quarantine dan DKIM domain
 * belum aktif. Jadi POST /invitations kini memakai sendInvitationMessage=false,
 * dan tautan penerimaannya (inviteRedeemUrl) dikirim lewat mailbox support
 * bersama tautan langsung ke group chat tiket.
 *
 * redeem_url disimpan karena hanya muncul sekali, di respons undangan — padahal
 * emailnya baru dikirim belakangan, saat guest benar-benar masuk grup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams_guest_user', function (Blueprint $table) {
            $table->text('redeem_url')->nullable()->after('invite_status');

            // Email pertama (yang memuat langkah "terima undangan") sudah
            // terkirim. Email berikutnya cukup tautan ke chat tiketnya.
            $table->timestamp('onboarded_at')->nullable()->after('invited_at');
        });
    }

    public function down(): void
    {
        Schema::table('teams_guest_user', function (Blueprint $table) {
            $table->dropColumn(['redeem_url', 'onboarded_at']);
        });
    }
};
