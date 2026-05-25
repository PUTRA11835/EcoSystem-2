<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_sla', function (Blueprint $table) {
            if (!Schema::hasColumn('ticket_sla', 'session_start_at')) {
                $table->timestamp('session_start_at')->nullable()->after('first_responded_at');
            }
            $table->timestamp('solution_started_at')->nullable()->after('session_start_at');
        });
    }

    public function down(): void
    {
        Schema::table('ticket_sla', function (Blueprint $table) {
            $table->dropColumn('solution_started_at');
            if (Schema::hasColumn('ticket_sla', 'session_start_at')) {
                $table->dropColumn('session_start_at');
            }
        });
    }
};
