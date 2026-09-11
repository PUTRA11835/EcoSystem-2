<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Menu "Module Group" di Management — pengelompokan module (mis. group
 * "Logistik" berisi module ABAP, FI, dll). order_seq 6 dipilih karena
 * children `management` yang sudah ada memakai 1-5 (lihat MenuSeeder).
 *
 * Lahir admin-only sesuai aturan baku MenuRegistrar — pemilik sistem yang
 * menentukan role mana yang di-grant lewat Control Center > Menu Access.
 */
return new class extends Migration
{
    public function up(): void
    {
        MenuRegistrar::register('management', [
            'management.module-groups' => 'Module Group',
        ], 6, 'page');
    }

    public function down(): void
    {
        MenuRegistrar::remove(['management.module-groups']);
    }
};
