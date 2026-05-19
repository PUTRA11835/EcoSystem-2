<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ticket_sla_events: tambah kolom jarvis_status yang belum ada di create migration
        Schema::table('ticket_sla_events', function (Blueprint $table) {
            if (!Schema::hasColumn('ticket_sla_events', 'jarvis_status')) {
                $table->string('jarvis_status', 50)->nullable()->after('event_type');
            }
        });

        // ticket_sla_pauses: tambah kolom yang diperlukan SlaService jika belum ada
        Schema::table('ticket_sla_pauses', function (Blueprint $table) {
            if (!Schema::hasColumn('ticket_sla_pauses', 'triggered_by_status')) {
                $table->string('triggered_by_status', 50)->nullable()->after('pause_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_sla_events', function (Blueprint $table) {
            if (Schema::hasColumn('ticket_sla_events', 'jarvis_status')) {
                $table->dropColumn('jarvis_status');
            }
        });

        Schema::table('ticket_sla_pauses', function (Blueprint $table) {
            if (Schema::hasColumn('ticket_sla_pauses', 'triggered_by_status')) {
                $table->dropColumn('triggered_by_status');
            }
        });
    }
};
