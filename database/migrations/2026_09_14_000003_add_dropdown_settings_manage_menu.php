<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Sub-permission slug for Dropdown Settings: separates VIEWING the page
 * (the existing 'management.employee.dropdown-settings' page slug) from
 * being allowed to actually add/edit/delete dropdown lists and their
 * values — same split as master.employee.create/.action for the Employee
 * master page. A role can now be granted read-only access to Dropdown
 * Settings (see it, but no Add/Edit/Delete buttons) independently of full
 * manage access, adjustable per role from Control Center → Menu Access.
 *
 * Sesuai aturan baku: menu BARU lahir aktif HANYA untuk EC Administrator.
 */
return new class extends Migration
{
    private const SLUG = 'management.employee.dropdown-settings.manage';

    public function up(): void
    {
        MenuRegistrar::register('management.employee.dropdown-settings', [
            self::SLUG => 'Manage Dropdown Lists & Values',
        ], 1, 'function');
    }

    public function down(): void
    {
        MenuRegistrar::remove([self::SLUG]);
    }
};
