<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ticket') && !Schema::hasColumn('ticket', 'onedrive_deliverable_folder_id')) {
            Schema::table('ticket', function (Blueprint $table) {
                $table->string('onedrive_deliverable_folder_id')->nullable()->after('onedrive_folder_url');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ticket') && Schema::hasColumn('ticket', 'onedrive_deliverable_folder_id')) {
            Schema::table('ticket', function (Blueprint $table) {
                $table->dropColumn('onedrive_deliverable_folder_id');
            });
        }
    }
};
