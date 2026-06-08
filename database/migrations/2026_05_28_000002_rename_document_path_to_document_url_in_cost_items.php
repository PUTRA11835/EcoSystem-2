<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_project_cost_items', function (Blueprint $table) {
            $table->renameColumn('document_path', 'document_url');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_project_cost_items', function (Blueprint $table) {
            $table->renameColumn('document_url', 'document_path');
        });
    }
};
