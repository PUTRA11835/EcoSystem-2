<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_message', function (Blueprint $table) {
            // JSON array of {name, address} — CC recipients of the email
            $table->json('cc_emails')->nullable()->after('email_in_reply_to');
        });

        Schema::table('staging_tickets', function (Blueprint $table) {
            $table->json('cc_emails')->nullable()->after('has_attachments');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_message', function (Blueprint $table) {
            $table->dropColumn('cc_emails');
        });

        Schema::table('staging_tickets', function (Blueprint $table) {
            $table->dropColumn('cc_emails');
        });
    }
};
