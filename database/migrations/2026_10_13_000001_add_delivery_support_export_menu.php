<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Slug izin Delivery Support → Export Excel.
 *
 * Sebelumnya export hanya dijaga izin halaman (delivery.support), jadi semua
 * yang bisa membuka list otomatis bisa mengekspor. Default sekarang hanya EC
 * Administrator; role lain diberi akses lewat Control Center → Menu Access.
 */
return new class extends Migration
{
    private const SLUG = 'delivery-support.export';

    public function up(): void
    {
        $registered = MenuRegistrar::register('delivery.support', [
            self::SLUG => 'Export Delivery Support',
        ], 12, 'function');

        if ($registered) {
            MenuRegistrar::grantToAdminOnly([self::SLUG]);
        }
    }

    public function down(): void
    {
        MenuRegistrar::remove([self::SLUG]);
    }
};
