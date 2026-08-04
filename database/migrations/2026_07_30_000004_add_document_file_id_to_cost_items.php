<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Simpan item ID OneDrive milik supporting document expense (Plan Cost).
 *
 * Sebelumnya hanya URL yang disimpan, sehingga aplikasi tidak punya pegangan
 * untuk (a) membuat ulang share link anonim dan (b) menghapus file lama saat
 * dokumen diganti/dihapus — file yatim menumpuk di folder "Plan Cost".
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['delivery_project_cost_items', 'delivery_support_cost_items'] as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'document_file_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->string('document_file_id', 255)->nullable()->after('document_name');
            });
        }
    }

    public function down(): void
    {
        foreach (['delivery_project_cost_items', 'delivery_support_cost_items'] as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'document_file_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('document_file_id');
            });
        }
    }
};
