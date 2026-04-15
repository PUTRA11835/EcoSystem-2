<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah remember_token ke auth_users untuk mendukung fitur "Keep me signed in".
 * Token disimpan sebagai hash SHA-256 dari cookie value yang dikirim ke browser.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_users', function (Blueprint $table) {
            $table->string('remember_token', 100)->nullable()->after('cp_token_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('auth_users', function (Blueprint $table) {
            $table->dropColumn('remember_token');
        });
    }
};
