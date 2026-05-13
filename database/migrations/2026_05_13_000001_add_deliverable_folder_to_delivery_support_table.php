<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('delivery_support') && !Schema::hasColumn('delivery_support', 'onedrive_deliverable_folder_id')) {
            Schema::table('delivery_support', function (Blueprint $table) {
                $table->string('onedrive_deliverable_folder_id')->nullable()->after('onedrive_folder_url');
                $table->string('onedrive_deliverable_folder_url', 1000)->nullable()->after('onedrive_deliverable_folder_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('delivery_support')) {
            Schema::table('delivery_support', function (Blueprint $table) {
                $table->dropColumn(['onedrive_deliverable_folder_id', 'onedrive_deliverable_folder_url']);
            });
        }
    }
};
