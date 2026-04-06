<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // staging_tickets: kolom sudah ada (dibuat oleh migration Jarvies)
        // Hanya tambahkan ke tabel ticket
        Schema::table('ticket', function (Blueprint $table) {
            $table->string('name',   255)->nullable()->after('description');
            $table->string('no_hp',  255)->nullable()->after('name');
            $table->string('module', 255)->nullable()->after('no_hp');
            $table->string('client', 255)->nullable()->after('module');
        });
    }

    public function down(): void
    {
        Schema::table('ticket', function (Blueprint $table) {
            $table->dropColumn(['name', 'no_hp', 'module', 'client']);
        });
    }
};
