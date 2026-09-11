<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menambah function menu agar Menu Access bisa mengatur aksi (bukan cuma
 * "boleh lihat halaman"):
 *
 *  - Delivery → Project: satu function per section halaman project detail,
 *    sehingga sebuah role bisa dibatasi "hanya boleh edit Delivery Information"
 *    sementara section lain tetap terlihat tapi read-only.
 *  - Reporting → Collection Outlook: izin mengubah status penagihan TOP
 *    (Open/Paid/Delay + Paid Date) langsung dari matrix.
 *
 * Grant awal mengikuti role yang SUDAH punya akses ke menu induknya, supaya
 * role existing tidak kehilangan kemampuan yang selama ini mereka punya.
 * (ID role tidak di-hardcode — lihat catatan divergensi ID role hasil
 * migrate:fresh.)
 */
return new class extends Migration
{
    private const PROJECT_FUNCTIONS = [
        ['slug' => 'delivery-project.edit-general-info',   'name' => 'Edit General Information',  'order_seq' => 2],
        ['slug' => 'delivery-project.edit-delivery-info',  'name' => 'Edit Delivery Information', 'order_seq' => 3],
        ['slug' => 'delivery-project.manage-team',         'name' => 'Manage Team Members',       'order_seq' => 4],
        ['slug' => 'delivery-project.manage-documents',    'name' => 'Manage Documents',          'order_seq' => 5],
        ['slug' => 'delivery-project.manage-issue-log',    'name' => 'Manage Issue Log',          'order_seq' => 6],
        ['slug' => 'delivery-project.manage-risk',         'name' => 'Manage Risk Register',      'order_seq' => 7],
        ['slug' => 'delivery-project.edit-location-info',  'name' => 'Edit Location Information', 'order_seq' => 8],
        ['slug' => 'delivery-project.manage-planning',     'name' => 'Manage Planning',           'order_seq' => 9],
        ['slug' => 'delivery-project.manage-plan-cost',    'name' => 'Manage Plan Cost',          'order_seq' => 10],
        ['slug' => 'delivery-project.delete-project',      'name' => 'Delete Project',            'order_seq' => 11],
    ];

    private const OUTLOOK_FUNCTIONS = [
        ['slug' => 'reporting.collection-outlook.edit', 'name' => 'Edit Payment Status', 'order_seq' => 1],
    ];

    public function up(): void
    {
        $this->addFunctions('delivery.project', self::PROJECT_FUNCTIONS);
        $this->addFunctions('reporting.collection-outlook', self::OUTLOOK_FUNCTIONS);
    }

    public function down(): void
    {
        $slugs = array_merge(
            array_column(self::PROJECT_FUNCTIONS, 'slug'),
            array_column(self::OUTLOOK_FUNCTIONS, 'slug'),
        );

        foreach ($slugs as $slug) {
            $menu = DB::table('menu')->where('slug', $slug)->first();
            if (!$menu) {
                continue;
            }
            DB::table('role_menu')->where('menu_id', $menu->id)->delete();
            DB::table('menu')->where('id', $menu->id)->delete();
        }
    }

    /**
     * Buat function menu di bawah $parentSlug dan beri akses ke setiap role yang
     * saat ini boleh melihat menu induknya.
     */
    private function addFunctions(string $parentSlug, array $functions): void
    {
        $now    = now();
        $parent = DB::table('menu')->where('slug', $parentSlug)->first();

        if (!$parent) {
            return; // menu induk belum ada → tidak ada yang bisa di-attach
        }

        $roleIds = DB::table('role_menu')
            ->where('menu_id', $parent->id)
            ->where('can_view', true)
            ->pluck('role_id');

        foreach ($functions as $fn) {
            $existing = DB::table('menu')->where('slug', $fn['slug'])->first();

            $menuId = $existing->id ?? DB::table('menu')->insertGetId([
                'parent_id'  => $parent->id,
                'name'       => $fn['name'],
                'slug'       => $fn['slug'],
                'type'       => 'function',
                'route_name' => null,
                'icon'       => null,
                'order_seq'  => $fn['order_seq'],
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($roleIds as $roleId) {
                DB::table('role_menu')->updateOrInsert(
                    ['role_id' => $roleId, 'menu_id' => $menuId],
                    ['can_view' => true, 'can_create' => false, 'can_edit' => false, 'can_delete' => false, 'created_at' => $now, 'updated_at' => $now]
                );
            }
        }
    }
};
