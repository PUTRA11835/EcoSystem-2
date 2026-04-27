<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kolom scale opsional — value list final masih didiskusikan, jadi disimpan
        // sebagai VARCHAR bebas (bukan ENUM) supaya tidak butuh migrasi ulang
        // saat opsi diputuskan. Validasi nilai dilakukan di controller.
        Schema::table('staging_tickets', function (Blueprint $table) {
            $table->string('scale', 50)->nullable()->after('ticket_priority');
        });

        Schema::table('ticket', function (Blueprint $table) {
            $table->string('scale', 50)->nullable()->after('ticket_type');
        });
    }

    public function down(): void
    {
        Schema::table('staging_tickets', function (Blueprint $table) {
            $table->dropColumn('scale');
        });

        Schema::table('ticket', function (Blueprint $table) {
            $table->dropColumn('scale');
        });
    }
};
