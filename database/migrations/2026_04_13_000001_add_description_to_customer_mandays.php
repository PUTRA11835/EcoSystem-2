<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_mandays', function (Blueprint $table) {
            // Short title / description of the proposal (e.g. "Propose Mandays 1", "Additional MD for New FM")
            $table->string('description', 255)->nullable()->after('version');
            // Notes filled by PIC when creating / updating the proposal (context, reason for additional MD, etc.)
            $table->text('proposal_notes')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('customer_mandays', function (Blueprint $table) {
            $table->dropColumn(['description', 'proposal_notes']);
        });
    }
};
