<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_message', function (Blueprint $table) {
            $table->text('sla_message')->nullable()->after('message_html');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_message', function (Blueprint $table) {
            $table->dropColumn('sla_message');
        });
    }
};
