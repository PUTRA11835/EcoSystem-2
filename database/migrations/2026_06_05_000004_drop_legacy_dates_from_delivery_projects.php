<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The legacy start_date/end_date columns were derived from planning min/max.
     * They are replaced by the manually-entered contract_start_date/contract_end_date.
     * Jarvies mobile is being adjusted to the new contract fields.
     */
    public function up(): void
    {
        Schema::table('delivery_projects', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_projects', 'start_date')) {
                $table->dropColumn('start_date');
            }
            if (Schema::hasColumn('delivery_projects', 'end_date')) {
                $table->dropColumn('end_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('delivery_projects', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('phase');
            $table->date('end_date')->nullable()->after('start_date');
        });
    }
};
