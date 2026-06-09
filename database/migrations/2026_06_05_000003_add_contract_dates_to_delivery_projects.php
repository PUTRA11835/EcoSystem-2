<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_projects', function (Blueprint $table) {
            // Contract window — manually entered from the actual contract document.
            // Acts as the boundary for all project planning dates.
            // NOTE: the existing start_date/end_date columns stay as the derived
            // planning span (still consumed by the Jarvies mobile API).
            $table->date('contract_start_date')->nullable()->after('go_live_estimated');
            $table->date('contract_end_date')->nullable()->after('contract_start_date');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_projects', function (Blueprint $table) {
            $table->dropColumn(['contract_start_date', 'contract_end_date']);
        });
    }
};
