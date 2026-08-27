<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Menu Reporting → Resource Timeline.
 *
 * Sesuai aturan baku: menu BARU lahir aktif HANYA untuk EC Administrator dan
 * mati untuk seluruh role lain. Role lain menyusul lewat Control Center →
 * Menu Access, bukan dari migrasi ini.
 */
return new class extends Migration
{
    private const SLUG = 'reporting.resource-timeline';

    public function up(): void
    {
        MenuRegistrar::register('reporting', [
            self::SLUG => 'Resource Timeline',
        ], 9, 'page');
    }

    public function down(): void
    {
        MenuRegistrar::remove([self::SLUG]);
    }
};
