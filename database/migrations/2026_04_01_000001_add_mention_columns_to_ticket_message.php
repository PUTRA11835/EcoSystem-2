<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ticket_message', function (Blueprint $table) {
            if (!Schema::hasColumn('ticket_message', 'mentioned_employee_ids')) {
                $table->json('mentioned_employee_ids')->nullable()->after('cc_emails');
            }
            if (!Schema::hasColumn('ticket_message', 'mentioned_role_ids')) {
                $table->json('mentioned_role_ids')->nullable()->after('mentioned_employee_ids');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ticket_message', function (Blueprint $table) {
            $table->dropColumn(['mentioned_employee_ids', 'mentioned_role_ids']);
        });
    }
};
