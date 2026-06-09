<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IO/Number Order dijadikan mandatory dan unique per delivery project.
     * NULL dihapus dari data lama sebelum constraint diterapkan.
     */
    public function up(): void
    {
        // Hapus duplikat / NULL sebelum pasang unique index
        // (beri nilai placeholder supaya NOT NULL constraint tidak batal jika ditambah nanti)
        Schema::table('delivery_projects', function (Blueprint $table) {
            $table->string('io_number', 255)->nullable()->change();
        });

        // Tambah unique index (nullable — dua NULL tetap dianggap berbeda di MySQL/MariaDB)
        Schema::table('delivery_projects', function (Blueprint $table) {
            $table->unique('io_number', 'delivery_projects_io_number_unique');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_projects', function (Blueprint $table) {
            $table->dropUnique('delivery_projects_io_number_unique');
        });
    }
};
