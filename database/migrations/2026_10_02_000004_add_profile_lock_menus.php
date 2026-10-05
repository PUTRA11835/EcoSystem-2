<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Slug izin kunci profil (H3.11; HC-D29): Verify & Lock dan Unlock, di bawah halaman Onboarding.
 *
 *   general.onboarding.lock    — HR menekan "Verify & Lock" setelah progres 100%
 *   general.onboarding.unlock  — HR/admin membuka kunci (alasan wajib, tercatat)
 *
 * Dipisah agar pemegang izin dapat berbeda (mis. banyak HR boleh mengunci, hanya sedikit yang boleh membuka).
 * ATURAN BAKU MenuRegistrar: slug baru HANYA aktif untuk EC Administrator; role lain diberi lewat Control Center
 * → Menu Access. Kunci baru berlaku hanya untuk karyawan yang DIKUNCI HR, jadi merilis fitur ini tidak mengubah
 * perilaku pegawai yang sudah live.
 *
 * Hanya menambah baris menu/role_menu; down() menghapusnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        MenuRegistrar::register('general.onboarding', [
            'general.onboarding.lock'   => 'Verify & Lock Profile',
            'general.onboarding.unlock' => 'Unlock Profile',
        ], 10);
    }

    public function down(): void
    {
        MenuRegistrar::remove(['general.onboarding.lock', 'general.onboarding.unlock']);
    }
};
