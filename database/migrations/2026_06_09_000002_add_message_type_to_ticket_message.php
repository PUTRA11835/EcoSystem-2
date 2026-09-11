<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_message', function (Blueprint $table) {
            $table->string('message_type', 32)->nullable()->after('is_internal_note');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_message', function (Blueprint $table) {
            $table->dropColumn('message_type');
        });
    }
};
