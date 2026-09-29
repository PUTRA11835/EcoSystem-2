<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Perbaikan grant menu Weekly Consolidation: module lead yang sesungguhnya di
 * sistem ini pegang role "Modul Lead" (employee_role id 15) — BUKAN "Delivery
 * Support User" seperti asumsi migrasi 2026_09_29_000002 sebelumnya. Tanpa
 * baris ini, ke-7 employee yang sudah punya role Modul Lead (mis. Aaliya
 * Stenya Sekaton, Fauziah Afshoh) tidak bisa melihat menu
 * reporting.weekly-consolidation sama sekali, walau nanti sudah di-assign ke
 * modul lewat Manage Leads.
 *
 * grantToAdminAndRoles() bersifat menimpa (role di luar daftar dicabut), jadi
 * daftar role lama dari migrasi 000002 harus ikut disertakan di sini, bukan
 * cuma "Modul Lead" saja.
 */
return new class extends Migration
{
    private const SLUG = 'reporting.weekly-consolidation';

    private const ROLES_WITH_FIX = [
        'Delivery Support User',
        'Delivery Support Head',
        'Delivery Support Service Helpdesk',
        'Delivery RPMO Head',
        'Modul Lead',
    ];

    private const ROLES_BEFORE_FIX = [
        'Delivery Support User',
        'Delivery Support Head',
        'Delivery Support Service Helpdesk',
        'Delivery RPMO Head',
    ];

    public function up(): void
    {
        MenuRegistrar::grantToAdminAndRoles([self::SLUG], self::ROLES_WITH_FIX);
    }

    public function down(): void
    {
        MenuRegistrar::grantToAdminAndRoles([self::SLUG], self::ROLES_BEFORE_FIX);
    }
};
