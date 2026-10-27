<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Body Hire boleh berbagi satu IO number lintas delivery project dalam customer
     * yang sama. Aturan itu sudah ditegakkan di validasi DeliveryProjectController
     * (sameCompanyIoRule / Rule::unique untuk type lain), tetapi unique index lama
     * dari 2026_05_25 masih memblokir di level DB → "Server Error" saat simpan.
     * Unique index diganti index biasa (tetap cepat untuk lookup IO).
     */
    public function up(): void
    {
        if (Schema::hasIndex('delivery_projects', 'delivery_projects_io_number_unique')) {
            Schema::table('delivery_projects', function (Blueprint $table) {
                $table->dropUnique('delivery_projects_io_number_unique');
            });
        }

        if (!Schema::hasIndex('delivery_projects', 'delivery_projects_io_number_index')) {
            Schema::table('delivery_projects', function (Blueprint $table) {
                $table->index('io_number', 'delivery_projects_io_number_index');
            });
        }
    }

    public function down(): void
    {
        // Tidak mengembalikan unique index: data Body Hire yang berbagi IO akan
        // membuat pembuatan index gagal.
        if (Schema::hasIndex('delivery_projects', 'delivery_projects_io_number_index')) {
            Schema::table('delivery_projects', function (Blueprint $table) {
                $table->dropIndex('delivery_projects_io_number_index');
            });
        }
    }
};
