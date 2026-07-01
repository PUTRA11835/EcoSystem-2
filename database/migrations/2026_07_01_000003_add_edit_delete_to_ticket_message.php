<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_message', function (Blueprint $table) {
            $table->boolean('is_deleted')->default(false)->after('is_internal_note');
            $table->timestamp('edited_at')->nullable()->after('is_deleted');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_message', function (Blueprint $table) {
            $table->dropColumn(['is_deleted', 'edited_at']);
        });
    }
};
