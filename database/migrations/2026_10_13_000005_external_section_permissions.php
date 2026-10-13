<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Izin per tab untuk KONSULTAN EXTERNAL — pasangan `employee.section.*` (13 Okt 2026).
 *
 *   employee.section.<tab>.view|update   → data karyawan INTERNAL  (HR, Master Employee)
 *   external.section.<tab>.view|update   → data konsultan EXTERNAL (RPMO, External → Consultants)
 *
 * Satu middleware (CheckEmployeeSectionAccess) memilih keluarga slug menurut JENIS target, persis seperti memilih
 * `my-profile.section.*` untuk diri sendiri. Create/Delete tab = kotak di baris `.view` (seperti Master Employee).
 *
 * TIDAK ADA YANG KEHILANGAN AKSES: setiap role yang sekarang memegang `employee.section.<tab>.*` otomatis menerima
 * `external.section.<tab>.*` dengan kotak yang sama. Setelah itu Admin bebas mencabut dari HR / memberikannya ke RPMO
 * di Management → Roles → Menu Access. Hanya menambah baris; down() menghapus slug baru (grant ikut terhapus).
 */
return new class extends Migration
{
    /** tab => label. Hanya tab yang berlaku bagi External (family/contract/payment/salary/compensation tidak). */
    public const TABS = [
        'basic_data'      => 'Basic Data',
        'address'         => 'Address',
        'identification'  => 'Identification',
        'education'       => 'Education',
        'qualification'   => 'Qualification',
        'bank'            => 'Bank Account',
        'attachment'      => 'Attachment',
        'hr_profile'      => 'HR Profile',
        'engagement'      => 'Engagement',
        'engagement_rate' => 'Engagement rate',
    ];

    public function up(): void
    {
        $groups = [];
        foreach (self::TABS as $tab => $label) {
            $groups["external.section.{$tab}"] = $label;
        }
        if (!MenuRegistrar::register('general.external', $groups, 60, 'group')) {
            return;   // induk belum ada (rilis lama): dilewati
        }

        $seq = 1;
        foreach (self::TABS as $tab => $label) {
            MenuRegistrar::register("external.section.{$tab}", [
                "external.section.{$tab}.view"   => "View {$label}",
                "external.section.{$tab}.update" => "Update {$label}",
            ], $seq);
        }

        // Salin grant: employee.section.<tab>.{view,update} → external.section.<tab>.{view,update}.
        $roles = collect();
        foreach (array_keys(self::TABS) as $tab) {
            foreach (['view', 'update'] as $kind) {
                $from = DB::table('menu')->where('slug', "employee.section.{$tab}.{$kind}")->value('id');
                $to   = DB::table('menu')->where('slug', "external.section.{$tab}.{$kind}")->value('id');
                if (!$from || !$to) {
                    continue;
                }
                foreach (DB::table('role_menu')->where('menu_id', $from)->get() as $g) {
                    DB::table('role_menu')->updateOrInsert(['role_id' => $g->role_id, 'menu_id' => $to], [
                        'can_view' => $g->can_view, 'can_create' => $g->can_create, 'can_edit' => $g->can_edit,
                        'can_delete' => $g->can_delete, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                    $roles->push($g->role_id);
                }
            }
        }

        DB::table('employee_role_assignment')->whereIn('role_id', $roles->unique())->pluck('employee_id')->unique()->each(function ($employeeId) {
            Cache::forget("perm_slugs_{$employeeId}");
            Cache::forget("perm_matrix_{$employeeId}");
        });
    }

    public function down(): void
    {
        $slugs = [];
        foreach (array_keys(self::TABS) as $tab) {
            array_push($slugs, "external.section.{$tab}.update", "external.section.{$tab}.view", "external.section.{$tab}");
        }
        MenuRegistrar::remove($slugs);
    }
};
