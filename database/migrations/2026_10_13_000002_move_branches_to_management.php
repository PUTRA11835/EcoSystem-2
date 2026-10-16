<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Branches pindah dari hub Attendance ke Management (master data yang dipakai
 * banyak menu: Attendance, Reimbursement, Purchase Request, Cash Advance, ...).
 *
 * Baris menu DIGANTI NAMA di tempat — bukan dibuat baru — sehingga seluruh grant
 * yang sudah dibagikan lewat Control Center tetap berlaku (nol regrant).
 *
 *   general.settings.branches         -> management.branches
 *   general.settings.branches.manage  -> management.branches.manage
 */
return new class extends Migration
{
    private const MAP = [
        'general.settings.branches'        => ['management.branches',        'Branches'],
        'general.settings.branches.manage' => ['management.branches.manage', 'Branches — Create / Edit / Delete'],
    ];

    public function up(): void
    {
        $management = DB::table('menu')->where('slug', 'management')->value('id');

        // Tanpa induk `management` (basis data kosong) tidak ada yang dipindah;
        // MenuSeeder yang membuatnya.
        if (!$management) {
            return;
        }

        $seq = (int) DB::table('menu')->where('parent_id', $management)->max('order_seq') + 1;

        foreach (self::MAP as $old => [$new, $name]) {
            DB::table('menu')->where('slug', $old)->update([
                'slug'       => $new,
                'name'       => $name,
                'parent_id'  => $management,
                'order_seq'  => $seq++,
                'updated_at' => now(),
            ]);
        }

        $this->flushCache(array_keys(self::MAP), array_column(self::MAP, 0));
    }

    public function down(): void
    {
        $general = DB::table('menu')->where('slug', 'general')->value('id');

        foreach (self::MAP as $old => [$new]) {
            DB::table('menu')->where('slug', $new)->update([
                'slug'       => $old,
                'parent_id'  => $general ?: DB::raw('parent_id'),
                'updated_at' => now(),
            ]);
        }
    }

    /** Cache izin per karyawan menyimpan daftar slug; buang yang terdampak. */
    private function flushCache(array $oldSlugs, array $newSlugs): void
    {
        $roleIds = DB::table('role_menu')
            ->join('menu', 'menu.id', '=', 'role_menu.menu_id')
            ->whereIn('menu.slug', $newSlugs)
            ->pluck('role_menu.role_id')
            ->unique();

        DB::table('employee_role_assignment')
            ->whereIn('role_id', $roleIds)
            ->pluck('employee_id')
            ->unique()
            ->each(function ($id) {
                \Illuminate\Support\Facades\Cache::forget("perm_slugs_{$id}");
                \Illuminate\Support\Facades\Cache::forget("perm_matrix_{$id}");
            });
    }
};
