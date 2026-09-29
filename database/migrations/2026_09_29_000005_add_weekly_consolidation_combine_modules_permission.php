<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Function permission "Combine Modules" untuk Weekly Consolidation — dipakai
 * WeeklyConsolidationController::canCombineModules() lewat canAccessMenu(),
 * menggantikan pengecekan role hardcoded (RoleId::TICKET_MANAGER_GROUP).
 *
 * Kenapa dipindah ke menu permission: pola yang sama persis sudah dipakai
 * di seluruh aplikasi ini untuk kapabilitas granular per-tiket (mis.
 * ticket.assign-pic, ui.ticket.manage-members) — admin bisa grant/cabut lewat
 * Menu Access kapan saja tanpa perlu deploy kode baru, lebih fleksibel &
 * lebih aman daripada dikunci di kode.
 *
 * Grant default SENGAJA disamakan persis dengan role yang sebelumnya sudah
 * bisa combine modules (RoleId::TICKET_MANAGER_GROUP: Admin/HOS/Helpdesk/
 * RPMO) — supaya perilaku hari ini TIDAK berubah, admin tinggal sesuaikan
 * nanti lewat Menu Access kalau perlu.
 */
return new class extends Migration
{
    private const SLUG = 'reporting.weekly-consolidation.combine-modules';

    public function up(): void
    {
        MenuRegistrar::register('reporting.weekly-consolidation', [
            self::SLUG => 'Combine Modules',
        ], 1, 'function');

        MenuRegistrar::grantToAdminAndRoles([self::SLUG], [
            'Delivery Support Head',
            'Delivery Support Service Helpdesk',
            'Delivery RPMO Head',
        ]);
    }

    public function down(): void
    {
        MenuRegistrar::remove([self::SLUG]);
    }
};
