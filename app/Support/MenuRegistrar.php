<?php

namespace App\Support;

use App\Enums\RoleId;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Pendaftaran menu / slug izin baru dari migrasi.
 *
 * ATURAN BAKU (jangan diubah tanpa keputusan eksplisit):
 *
 *   Menu atau slug izin BARU selalu lahir dalam keadaan
 *   AKTIF (tercentang) HANYA untuk role EC Administrator,
 *   dan MATI untuk SELURUH role lain.
 *
 * Alasannya: pemilik sistem yang memutuskan siapa boleh apa, lewat Control
 * Center → Menu Access. Kalau migrasi ikut membagikan grant (mis. mewarisi
 * dari menu induk), izin menyebar diam-diam ke role yang tidak pernah
 * disetujui siapa pun, dan admin tidak punya cara tahu itu terjadi.
 *
 * Pemakaian di migrasi:
 *
 *     use App\Support\MenuRegistrar;
 *
 *     public function up(): void
 *     {
 *         MenuRegistrar::register('delivery.project', [
 *             'delivery-project.wricef.view' => 'WRICEF Log — View',
 *         ], 61);
 *     }
 *
 *     public function down(): void
 *     {
 *         MenuRegistrar::remove(['delivery-project.wricef.view']);
 *     }
 *
 * Kedua method sudah membuang cache `perm_slugs_*` yang terdampak, jadi
 * perubahannya langsung terasa tanpa `php artisan cache:clear` manual.
 * Lihat App\Http\Middleware\ShareMenuPermissions.
 */
class MenuRegistrar
{
    /**
     * Buat menu di bawah $parentSlug lalu beri grant HANYA ke EC Administrator.
     *
     * @param  array<string,string>  $menus  [slug => nama tampilan]
     * @param  int   $startSeq  order_seq baris pertama; berikutnya menaik.
     * @param  string $type     'function' untuk slug izin, 'menu' untuk item sidebar.
     * @return bool  false bila menu induknya belum ada (migrasi dilewati).
     */
    public static function register(string $parentSlug, array $menus, int $startSeq = 100, string $type = 'function'): bool
    {
        $parent = DB::table('menu')->where('slug', $parentSlug)->first();
        if (!$parent) {
            return false;
        }

        $seq = $startSeq;
        foreach ($menus as $slug => $name) {
            self::ensureMenu($parent->id, $slug, $name, $seq++, $type);
        }

        self::grantToAdminOnly(array_keys($menus));

        return true;
    }

    /**
     * Hapus menu beserta seluruh grant-nya (dipakai di down()).
     *
     * @param  string[]  $slugs
     */
    public static function remove(array $slugs): void
    {
        foreach ($slugs as $slug) {
            $menu = DB::table('menu')->where('slug', $slug)->first();
            if (!$menu) {
                continue;
            }

            $roleIds = DB::table('role_menu')->where('menu_id', $menu->id)->pluck('role_id');

            DB::table('role_menu')->where('menu_id', $menu->id)->delete();
            DB::table('menu')->where('id', $menu->id)->delete();

            self::flushPermCache($roleIds);
        }
    }

    /**
     * Beri grant slug ke EC Administrator saja, dan cabut dari role lain.
     *
     * Pencabutan disengaja: method ini adalah satu-satunya sumber kebenaran
     * untuk keadaan awal sebuah slug, jadi menjalankannya ulang selalu
     * mengembalikan slug ke kondisi baku.
     *
     * @param  string[]  $slugs
     */
    public static function grantToAdminOnly(array $slugs): void
    {
        $menuIds = DB::table('menu')->whereIn('slug', $slugs)->pluck('id');
        if ($menuIds->isEmpty()) {
            return;
        }

        $adminRoleId = self::adminRoleId();

        // Role yang cache izinnya perlu dibuang: yang kehilangan grant + admin.
        $affected = DB::table('role_menu')
            ->whereIn('menu_id', $menuIds)
            ->pluck('role_id')
            ->push($adminRoleId)
            ->unique();

        DB::table('role_menu')->whereIn('menu_id', $menuIds)->delete();

        $now  = now();
        $rows = $menuIds->map(fn ($menuId) => [
            'role_id'    => $adminRoleId,
            'menu_id'    => $menuId,
            'can_view'   => true,
            'can_create' => false,
            'can_edit'   => false,
            'can_delete' => false,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        DB::table('role_menu')->insert($rows);

        self::flushPermCache($affected);
    }

    /**
     * ID role EC Administrator.
     *
     * Diresolve lewat NAMA lebih dulu, karena `migrate:fresh` bisa menghasilkan
     * ID role yang berbeda dari DB produksi. RoleId::EC_ADMINISTRATOR dipakai
     * sebagai cadangan terakhir.
     */
    public static function adminRoleId(): int
    {
        return (int) (DB::table('employee_role')->where('name', 'EC Administrator')->value('id')
            ?? RoleId::EC_ADMINISTRATOR->value);
    }

    // ── internal ────────────────────────────────────────────────────────────

    private static function ensureMenu(int $parentId, string $slug, string $name, int $seq, string $type): void
    {
        if (DB::table('menu')->where('slug', $slug)->exists()) {
            return;
        }

        $now = now();

        DB::table('menu')->insert([
            'parent_id'  => $parentId,
            'name'       => $name,
            'slug'       => $slug,
            'type'       => $type,
            'route_name' => null,
            'icon'       => null,
            'order_seq'  => $seq,
            'is_active'  => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Buang cache izin milik employee dari role yang grant-nya berubah.
     *
     * ShareMenuPermissions menyimpan daftar slug per user selama 60 menit
     * (`perm_slugs_{employee_id}`), dan invalidasinya hanya dipanggil dari
     * Role/Menu/EmployeeController. Migrasi menulis `role_menu` langsung lewat
     * DB facade, jadi tanpa ini menu baru tidak muncul sampai cache kedaluwarsa.
     */
    private static function flushPermCache($roleIds): void
    {
        if ($roleIds->isEmpty()) {
            return;
        }

        DB::table('employee_role_assignment')
            ->whereIn('role_id', $roleIds)
            ->pluck('employee_id')
            ->unique()
            ->each(fn ($empId) => Cache::forget("perm_slugs_{$empId}"));
    }
}
