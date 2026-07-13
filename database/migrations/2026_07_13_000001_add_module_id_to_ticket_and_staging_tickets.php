<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kolom `module` (string) lama tetap dipertahankan sebagai referensi/patokan
        // untuk pengisian module_id secara manual. Tidak ada migrasi data otomatis di sini.
        Schema::table('ticket', function (Blueprint $table) {
            $table->unsignedBigInteger('module_id')->nullable()->after('module');
            $table->foreign('module_id')->references('id')->on('modules')->onDelete('set null');
        });

        Schema::table('staging_tickets', function (Blueprint $table) {
            $table->unsignedBigInteger('module_id')->nullable()->after('module');
            $table->foreign('module_id')->references('id')->on('modules')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('ticket', function (Blueprint $table) {
            $table->dropForeign(['module_id']);
            $table->dropColumn('module_id');
        });

        Schema::table('staging_tickets', function (Blueprint $table) {
            $table->dropForeign(['module_id']);
            $table->dropColumn('module_id');
        });
    }
};
