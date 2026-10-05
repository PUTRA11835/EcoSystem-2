<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Slug izin alat join date HR (HC-D64): mengisi join date data lama (satu per satu / impor CSV) dan mengirim pengingat.
 * Di bawah halaman Onboarding (tanpa menu/tab baru).
 *
 * ATURAN BAKU MenuRegistrar: slug baru HANYA aktif untuk EC Administrator; role lain diberi lewat Management → Role →
 * Menu Access. Fitur ini hanya muncul bagi pemegang slug, jadi merilisnya tidak mengubah perilaku siapa pun yang sudah live.
 *
 * Hanya menambah baris menu/role_menu; down() menghapusnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        MenuRegistrar::register('general.onboarding', [
            'general.onboarding.join-date' => 'Set Join Dates (HR tools)',
        ], 12);
    }

    public function down(): void
    {
        MenuRegistrar::remove(['general.onboarding.join-date']);
    }
};
