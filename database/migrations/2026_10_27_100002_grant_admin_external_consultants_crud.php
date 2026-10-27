<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hasil UAT: tombol "Add Consultant" tidak tampil bagi admin. Tombol itu menuntut Create pada `general.external.consultants`, sedangkan
 * slug ini lahir hanya dengan View. EC Administrator diberi Create/Edit/Delete (seperti slug External lain); role lain tetap diputuskan
 * pemilik sistem lewat Control Center → Menu Access. Idempotent; hanya menyentuh baris EC Administrator.
 */
return new class extends Migration
{
    public function up(): void
    {
        $role = (int) DB::table('employee_role')->where('name', 'EC Administrator')->value('id');
        $menu = (int) DB::table('menu')->where('slug', 'general.external.consultants')->value('id');
        if (!$role || !$menu) {
            return;
        }
        DB::table('role_menu')->updateOrInsert(
            ['role_id' => $role, 'menu_id' => $menu],
            ['can_view' => 1, 'can_create' => 1, 'can_edit' => 1, 'can_delete' => 1, 'updated_at' => now()]
        );
        DB::table('cache')->where('key', 'like', '%perm_%')->delete();
    }

    public function down(): void
    {
        // Pemberian izin tidak dicabut otomatis.
    }
};
