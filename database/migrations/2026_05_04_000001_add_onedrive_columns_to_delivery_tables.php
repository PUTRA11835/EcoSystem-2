<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('delivery_support') && !Schema::hasColumn('delivery_support', 'onedrive_folder_id')) {
            Schema::table('delivery_support', function (Blueprint $table) {
                $table->string('onedrive_folder_id')->nullable()->after('calculated_progress');
                $table->string('onedrive_folder_url', 1000)->nullable()->after('onedrive_folder_id');
            });
        }

        if (Schema::hasTable('delivery_projects') && !Schema::hasColumn('delivery_projects', 'onedrive_folder_id')) {
            Schema::table('delivery_projects', function (Blueprint $table) {
                $table->string('onedrive_folder_id')->nullable()->after('calculated_progress');
                $table->string('onedrive_folder_url', 1000)->nullable()->after('onedrive_folder_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('delivery_support')) {
            Schema::table('delivery_support', function (Blueprint $table) {
                $table->dropColumn(['onedrive_folder_id', 'onedrive_folder_url']);
            });
        }
        if (Schema::hasTable('delivery_projects')) {
            Schema::table('delivery_projects', function (Blueprint $table) {
                $table->dropColumn(['onedrive_folder_id', 'onedrive_folder_url']);
            });
        }
    }
};
