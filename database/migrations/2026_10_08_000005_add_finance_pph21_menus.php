<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Menu Access grup "Commercial & Finance" (Payroll / BPJS / PPh 21) — mulai dengan PPh 21 (Fase 1).
 *
 *   finance                        (group, akar; hanya pengelompok di Menu Access)
 *   └─ finance.pph21               (group; induk tab)
 *      └─ finance.pph21.settings   page   C buat tahun pajak · E ubah tarif
 *
 * ATURAN BAKU MenuRegistrar: slug baru hanya aktif untuk EC Administrator; pembagian ke role Finance/HR
 * dilakukan di Management → Roles → Menu Access. Tidak mengubah perilaku siapa pun yang sudah live.
 * Modul Payroll dan BPJS menambah slug-nya sendiri pada fase masing-masing (di bawah akar `finance` yang sama).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('menu')->where('slug', 'finance')->exists()) {
            DB::table('menu')->insert([
                'parent_id' => null, 'name' => 'Commercial & Finance', 'slug' => 'finance', 'type' => 'group',
                'route_name' => null, 'icon' => null, 'order_seq' => 17, 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            // Akar baru: hanya EC Administrator pada awalnya (role lain dibuka di Menu Access).
            MenuRegistrar::grantToAdminOnly(['finance']);
        }

        MenuRegistrar::register('finance', ['finance.pph21' => 'PPh 21'], 30, 'group');
        MenuRegistrar::register('finance.pph21', ['finance.pph21.settings' => 'Settings'], 1, 'page');

        // Satu tab, kotak C/E ditegakkan di server (menu.can) — EC Administrator mulai dengan keduanya.
        MenuRegistrar::grantToAdminAndRoles(['finance.pph21.settings'], [], ['create', 'edit']);

        $this->flushPermCache();
    }

    public function down(): void
    {
        MenuRegistrar::remove(['finance.pph21.settings', 'finance.pph21']);
        // Akar `finance` ikut dihapus hanya bila sudah tidak punya anak (Payroll/BPJS menambah miliknya sendiri).
        $root = DB::table('menu')->where('slug', 'finance')->first();
        if ($root && !DB::table('menu')->where('parent_id', $root->id)->exists()) {
            MenuRegistrar::remove(['finance']);
        }
        $this->flushPermCache();
    }

    private function flushPermCache(): void
    {
        DB::table('employee_role_assignment')->pluck('employee_id')->unique()->each(function ($employeeId) {
            Cache::forget("perm_slugs_{$employeeId}");
            Cache::forget("perm_matrix_{$employeeId}");
        });
    }
};
