<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_projects', function (Blueprint $table) {
            $table->renameColumn('project_owner_id', 'project_manager_id');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_projects', function (Blueprint $table) {
            $table->renameColumn('project_manager_id', 'project_owner_id');
        });
    }
};
