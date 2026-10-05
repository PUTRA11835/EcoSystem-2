<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;

/**
 * Slug izin blok Engagement konsultan (HC-D47).
 *
 *   employee.section.engagement[.view|.update]       — skema, vendor, client, peran, dikelola oleh, periode
 *   employee.section.engagement_rate[.view|.update]  — TARIF + mata uang (sensitif; slug terpisah)
 *
 * Hanya `employee.section.*` (data ORANG LAIN). TIDAK ada `my-profile.section.engagement*`: konsultan tidak
 * memiliki jalur untuk melihat/mengubah blok ini pada profilnya sendiri (CheckEmployeeSectionAccess menolak
 * karena slug-nya tidak ada; controller juga menolak target diri sendiri).
 *
 * ATURAN BAKU MenuRegistrar: slug baru hanya aktif untuk EC Administrator; role lain diberi lewat Control
 * Center → Menu Access. Perilaku pengguna yang sudah live TIDAK berubah.
 */
return new class extends Migration
{
    private const SLUGS = [
        'employee.section.engagement',
        'employee.section.engagement.view',
        'employee.section.engagement.update',
        'employee.section.engagement_rate',
        'employee.section.engagement_rate.view',
        'employee.section.engagement_rate.update',
    ];

    public function up(): void
    {
        MenuRegistrar::register('master.employee', ['employee.section.engagement' => 'Engagement'], 21, 'group');
        MenuRegistrar::register('employee.section.engagement', [
            'employee.section.engagement.view'   => 'View Engagement',
            'employee.section.engagement.update' => 'Update Engagement',
        ], 1);

        MenuRegistrar::register('master.employee', ['employee.section.engagement_rate' => 'Engagement Rate'], 22, 'group');
        MenuRegistrar::register('employee.section.engagement_rate', [
            'employee.section.engagement_rate.view'   => 'View Engagement Rate',
            'employee.section.engagement_rate.update' => 'Update Engagement Rate',
        ], 1);
    }

    public function down(): void
    {
        MenuRegistrar::remove(array_reverse(self::SLUGS));
    }
};
