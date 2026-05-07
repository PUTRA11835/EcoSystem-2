<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ticket') && !Schema::hasColumn('ticket', 'onedrive_folder_id')) {
            Schema::table('ticket', function (Blueprint $table) {
                $table->string('onedrive_folder_id')->nullable()->after('folder');
                $table->string('onedrive_folder_url', 1000)->nullable()->after('onedrive_folder_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ticket')) {
            Schema::table('ticket', function (Blueprint $table) {
                $table->dropColumn(['onedrive_folder_id', 'onedrive_folder_url']);
            });
        }
    }
};
