<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tambah kolom untuk alur verifikasi & ganti password pertama kali (customer baru).
 *
 * is_already_cp  : false  → customer baru, harus ganti password via email
 *                  true   → sudah pernah ganti password / employee (login normal)
 * cp_token       : token unik untuk link change-password di email
 * cp_token_expires_at : waktu kadaluarsa token (24 jam)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_users', function (Blueprint $table) {
            $table->boolean('is_already_cp')->default(false)->after('is_active');
            $table->string('cp_token', 64)->nullable()->unique()->after('is_already_cp');
            $table->timestamp('cp_token_expires_at')->nullable()->after('cp_token');
        });

        // Semua akun yang sudah ada (employee maupun customer lama)
        // dianggap sudah pernah ganti password → set true
        DB::table('auth_users')->update(['is_already_cp' => true]);
    }

    public function down(): void
    {
        Schema::table('auth_users', function (Blueprint $table) {
            $table->dropColumn(['is_already_cp', 'cp_token', 'cp_token_expires_at']);
        });
    }
};
