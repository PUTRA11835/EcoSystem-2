<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kpi_templates', function (Blueprint $table) {
            // Peer templates: named groups [{name, basis, value, members[]}] — every
            // member rates every other member of the same group.
            $table->json('peer_groups')->nullable()->after('subject_employees');
        });

        if (Schema::hasColumn('kpi_templates', 'peer_pairs')) {
            Schema::table('kpi_templates', function (Blueprint $table) {
                $table->dropColumn('peer_pairs');
            });
        }
    }

    public function down(): void
    {
        Schema::table('kpi_templates', function (Blueprint $table) {
            $table->dropColumn('peer_groups');
        });
    }
};
