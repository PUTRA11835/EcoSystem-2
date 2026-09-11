<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Status pengiriman email untuk reply/deliverable helpdesk -> customer.
     * - email_status = null   : tidak ada percobaan email (pesan web murni)
     * - email_status = 'sent' : email berhasil dikirim (opsional, umumnya cukup email_message_id)
     * - email_status = 'failed': email GAGAL dikirim (draft tertinggal di M365) -> tampilkan di bubble
     * - email_error : alasan gagal yang ramah pengguna (mis. "Alamat email tujuan tidak valid")
     */
    public function up(): void
    {
        Schema::table('ticket_message', function (Blueprint $table) {
            $table->string('email_status', 20)->nullable()->after('email_message_id');
            $table->text('email_error')->nullable()->after('email_status');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_message', function (Blueprint $table) {
            $table->dropColumn(['email_status', 'email_error']);
        });
    }
};
