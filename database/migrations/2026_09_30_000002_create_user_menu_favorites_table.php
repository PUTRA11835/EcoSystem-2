<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menu Favorit sidebar per karyawan (HC-D25).
 *
 * KENAPA TABEL SENDIRI, BUKAN `auth_users.preferences`:
 * SettingsController::loadPreferences() MEMBUANG kunci yang tidak ada di
 * DEFAULT_PREFERENCES dan persistPreferences() menimpa seluruh JSON, jadi
 * favorit yang disimpan di sana hilang setiap kali pengguna menyimpan atau
 * mereset pengaturan tampilan. Tabel kecil terpisah tidak dapat terkena efek itu
 * dan tidak mengubah tabel `auth_users` yang kritis.
 *
 * YANG DISIMPAN: jalur URL (mis. `/general/onboarding`), BUKAN slug maupun URL
 * lengkap. Sidebar hanya menampilkan favorit yang tautannya MASIH ada di sidebar
 * hasil render (yang sudah disaring izin di server), sehingga favorit tidak dapat
 * dipakai untuk membuka halaman yang izinnya sudah dicabut.
 *
 * Batas jumlah (6) ditegakkan di SidebarFavoriteService, bukan di tabel, supaya
 * dapat diubah tanpa migrasi. Identitas pemilik selalu dari sesi, tidak pernah
 * dari request.
 *
 * Hanya menambah tabel — tidak ada tabel lama yang disentuh; down() menghapusnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_menu_favorites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            // 191 = batas aman indeks unik utf8mb4 pada MySQL lama.
            $table->string('menu_path', 191);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            // Karyawan dihapus → favoritnya ikut hilang (data pribadi tanpa makna sendiri).
            $table->foreign('employee_id')->references('employee_id')->on('employee')->cascadeOnDelete();

            $table->unique(['employee_id', 'menu_path'], 'uq_user_menu_favorites_employee_path');
            $table->index(['employee_id', 'sort_order'], 'idx_user_menu_favorites_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_menu_favorites');
    }
};
