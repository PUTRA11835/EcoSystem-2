<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_projects', function (Blueprint $table) {
            // Rename pic -> project_owner
            $table->renameColumn('pic', 'project_owner');

            // New fields
            $table->string('high_level_risk')->nullable()->after('project_type');
            $table->string('io_number')->nullable()->after('high_level_risk');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_projects', function (Blueprint $table) {
            $table->renameColumn('project_owner', 'pic');
            $table->dropColumn(['high_level_risk', 'io_number']);
        });
    }
};
