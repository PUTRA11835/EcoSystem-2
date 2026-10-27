<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Izin khusus tarif kontrak konsultan External (`general.external.contracts.rate`, "External Contract rate").
 *
 * Hasil UAT: Head RPMO perlu mengisi dan melihat Rate kontrak konsultan, tetapi izin lama `general.contracts.salary` ("View Salary")
 * juga membuka gaji karyawan INTERNAL. Slug baru hanya mengatur tarif konsultan; pemegang "View Salary" tidak kehilangan akses.
 * Aturan baku MenuRegistrar: slug baru aktif HANYA untuk EC Administrator; pemilik sistem membagikannya ke role lain
 * (mis. Delivery RPMO Head) lewat Control Center → Menu Access.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('menu')->where('slug', 'general.external')->exists()) {
            return;
        }
        MenuRegistrar::register('general.external', ['general.external.contracts.rate' => 'External Contract rate'], 21, 'function');
    }

    public function down(): void
    {
        MenuRegistrar::remove(['general.external.contracts.rate']);
    }
};
