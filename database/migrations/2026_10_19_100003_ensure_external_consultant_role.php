<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Role "External Consultant": di DB lokal sudah ada (id 64), tetapi tidak pernah dibuat oleh migrasi/seeder — di produksi tidak ada,
 * sehingga konsultan baru hanya memegang EC User dan melihat menu karyawan yang tak relevan.
 *
 * Migrasi ini MENAMBAH saja: membuat role bila belum ada, lalu menambahkan grant yang belum ada (tidak ada grant yang dicabut atau
 * ditimpa — role lokal id 64 yang sudah punya grant sendiri tetap utuh). Penugasan role ke konsultan lama TIDAK dilakukan di sini
 * (lihat perintah `external:assign-role`, dry-run dulu); konsultan baru mendapat role ini otomatis lewat Add Consultant.
 */
return new class extends Migration
{
    /** slug => [view, create, edit, delete] — self-service konsultan: dashboard, profil sendiri, kalender, timesheet & invoice sendiri */
    private const GRANTS = [
        'dashboard' => [1, 0, 0, 0],
        'calendar' => [1, 0, 0, 0],
        'my-profile' => [1, 0, 0, 0],
        'my-profile.section.basic_data' => [1, 0, 0, 0], 'my-profile.section.basic_data.view' => [1, 0, 0, 0],
        'my-profile.section.address' => [1, 0, 0, 0], 'my-profile.section.address.view' => [1, 0, 0, 0],
        'my-profile.section.identification' => [1, 0, 0, 0], 'my-profile.section.identification.view' => [1, 0, 0, 0],
        'my-profile.section.bank' => [1, 0, 0, 0], 'my-profile.section.bank.view' => [1, 0, 0, 0],
        'my-profile.section.attachment' => [1, 0, 0, 0], 'my-profile.section.attachment.view' => [1, 0, 0, 0],
        'my-profile.section.hr_profile.view' => [1, 0, 0, 0],
        'general.external.my-timesheet' => [1, 1, 1, 1],
    ];

    public function up(): void
    {
        $roleId = DB::table('employee_role')->where('name', 'External Consultant')->value('id');
        if (!$roleId) {
            $roleId = DB::table('employee_role')->insertGetId([
                'name' => 'External Consultant', 'description' => 'External consultant (self-service: profile, timesheet & invoice)',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach (self::GRANTS as $slug => [$view, $create, $edit, $delete]) {
            $menuId = DB::table('menu')->where('slug', $slug)->value('id');
            if (!$menuId) {
                continue; // menu induk belum ada di lingkungan ini — dilewati
            }
            DB::table('role_menu')->insertOrIgnore([
                'role_id' => $roleId, 'menu_id' => $menuId, 'can_view' => $view, 'can_create' => $create,
                'can_edit' => $edit, 'can_delete' => $delete, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('employee_role_assignment')->where('role_id', $roleId)->pluck('employee_id')->each(function ($employeeId) {
            Cache::forget("perm_slugs_{$employeeId}");
            Cache::forget("perm_matrix_{$employeeId}");
        });
    }

    public function down(): void
    {
        // Hanya grant slug baru milik migrasi ini; role dan grant lama dibiarkan (bisa dipakai konsultan sungguhan).
        $roleId = DB::table('employee_role')->where('name', 'External Consultant')->value('id');
        $menuId = DB::table('menu')->where('slug', 'general.external.my-timesheet')->value('id');
        if ($roleId && $menuId) {
            DB::table('role_menu')->where('role_id', $roleId)->where('menu_id', $menuId)->delete();
        }
    }
};
