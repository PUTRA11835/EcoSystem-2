<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ticket_deliverables.body_text was really a per-file INTERNAL description
 * (never sent to the customer). "Body text" now means the email body typed at
 * send time, which is not stored — so the column is renamed to avoid the clash.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('ticket_deliverables', 'body_text')) {
            Schema::table('ticket_deliverables', function (Blueprint $table) {
                $table->renameColumn('body_text', 'description');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('ticket_deliverables', 'description')) {
            Schema::table('ticket_deliverables', function (Blueprint $table) {
                $table->renameColumn('description', 'body_text');
            });
        }
    }
};
