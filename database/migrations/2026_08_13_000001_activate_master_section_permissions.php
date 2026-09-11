<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Mengaktifkan izin section Master Employee & Business Partner.
 *
 * LATAR: migrasi 2026_07_02_193812 membuat baris menu
 * `employee.section.{key}.view|update` dan `customer.section.{key}.view|update`
 * di bawah Master > Employee / Business Partner, TAPI:
 *
 *   1. tidak ada satu baris kode pun yang membaca slug tsb — halaman detail
 *      selalu tampil dan selalu bisa diedit berapa pun checkbox dimatikan;
 *   2. tidak ada satu pun grant di `role_menu` — bahkan EC Administrator
 *      tidak memilikinya.
 *
 * Sejak sekarang slug tsb ditegakkan (EmployeeController::show,
 * CustomerController::show, middleware employee.section/customer.section).
 * Karena poin 2, menegakkannya tanpa backfill akan menyembunyikan SELURUH
 * section dari SEMUA orang. Migrasi ini karena itu memberi view+update ke
 * role yang HARI INI sudah bisa membuka halaman Master-nya — status quo
 * dipertahankan, lalu admin mencabut sendiri lewat Control Center.
 *
 * Ini bukan pelanggaran aturan "slug baru = admin-only" (App\Support\MenuRegistrar):
 * slug-slug ini bukan izin baru, dan grant di sini tidak menambah kemampuan
 * siapa pun — hanya merekam kemampuan yang sudah mereka punya supaya tidak
 * hilang mendadak saat gate-nya dinyalakan.
 *
 * Catatan: `master.employee.action` / `master.customer.action` SENGAJA tidak
 * di-backfill. Keduanya memang sudah dimaksudkan sebagai izin terbatas
 * (seeder: EC Administrator saja) dan selama ini tidak berlaku karena
 * endpoint-nya tak dijaga. Itulah bug yang diperbaiki.
 */
return new class extends Migration
{
    private array $employeeSections = [
        'basic_data', 'address', 'identification', 'family', 'education',
        'qualification', 'contract', 'bank', 'payment', 'attachment',
    ];

    private array $customerSections = [
        'basic_data', 'address', 'identification', 'contact', 'bank',
        'credential', 'history', 'attachment',
    ];

    /** Slug yang tidak pernah dibaca kode mana pun — menyesatkan admin. */
    private array $deadSlugs = [
        'delivery-support.edit-type',
        'ticket.confirm-assignment',
        'ui.ticket.internal-notes',
    ];

    public function up(): void
    {
        $this->backfill('master.employee', 'employee.section', $this->employeeSections);
        $this->backfill('master.customer', 'customer.section', $this->customerSections);

        $this->dropSlugs($this->deadSlugs);
    }

    public function down(): void
    {
        // Cabut kembali grant section (kembali ke keadaan "tidak ada grant").
        $slugs = [];
        foreach ($this->employeeSections as $key) {
            $slugs[] = "employee.section.{$key}.view";
            $slugs[] = "employee.section.{$key}.update";
        }
        foreach ($this->customerSections as $key) {
            $slugs[] = "customer.section.{$key}.view";
            $slugs[] = "customer.section.{$key}.update";
        }

        $menuIds = DB::table('menu')->whereIn('slug', $slugs)->pluck('id');
        if ($menuIds->isNotEmpty()) {
            $this->flushCacheForRoles(
                DB::table('role_menu')->whereIn('menu_id', $menuIds)->pluck('role_id')
            );
            DB::table('role_menu')->whereIn('menu_id', $menuIds)->delete();
        }

        // Slug mati tidak dibuat ulang: tidak ada kode yang memakainya, jadi
        // mengembalikannya hanya memunculkan lagi checkbox yang tidak berefek.
    }

    /**
     * Beri view+update pada seluruh section $prefix kepada tiap role yang
     * sudah punya $parentSlug. Grant yang sudah ada dibiarkan (tidak dobel).
     */
    private function backfill(string $parentSlug, string $prefix, array $sections): void
    {
        $parentId = DB::table('menu')->where('slug', $parentSlug)->value('id');
        if (!$parentId) {
            return;
        }

        $roleIds = DB::table('role_menu')
            ->where('menu_id', $parentId)
            ->where('can_view', true)
            ->pluck('role_id');

        if ($roleIds->isEmpty()) {
            return;
        }

        $slugs = [];
        foreach ($sections as $key) {
            $slugs[] = "{$prefix}.{$key}.view";
            $slugs[] = "{$prefix}.{$key}.update";
        }

        $menuIds = DB::table('menu')->whereIn('slug', $slugs)->pluck('id');
        if ($menuIds->isEmpty()) {
            return;
        }

        $existing = DB::table('role_menu')
            ->whereIn('menu_id', $menuIds)
            ->whereIn('role_id', $roleIds)
            ->get()
            ->map(fn ($r) => "{$r->role_id}:{$r->menu_id}")
            ->flip();

        $now  = now();
        $rows = [];
        foreach ($menuIds as $menuId) {
            foreach ($roleIds as $roleId) {
                if ($existing->has("{$roleId}:{$menuId}")) {
                    continue;
                }
                $rows[] = [
                    'role_id'    => $roleId,
                    'menu_id'    => $menuId,
                    'can_view'   => true,
                    'can_create' => false,
                    'can_edit'   => false,
                    'can_delete' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows) {
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('role_menu')->insert($chunk);
            }
        }

        $this->flushCacheForRoles($roleIds);
    }

    private function dropSlugs(array $slugs): void
    {
        $menuIds = DB::table('menu')->whereIn('slug', $slugs)->pluck('id');
        if ($menuIds->isEmpty()) {
            return;
        }

        $this->flushCacheForRoles(
            DB::table('role_menu')->whereIn('menu_id', $menuIds)->pluck('role_id')
        );

        DB::table('role_menu')->whereIn('menu_id', $menuIds)->delete();
        DB::table('menu')->whereIn('id', $menuIds)->delete();
    }

    /**
     * Buang cache `perm_slugs_{employee_id}` milik anggota role terdampak —
     * tanpa ini perubahan baru terasa setelah TTL 60 menit habis.
     * Lihat App\Http\Middleware\ShareMenuPermissions.
     */
    private function flushCacheForRoles($roleIds): void
    {
        $roleIds = collect($roleIds)->unique()->values();
        if ($roleIds->isEmpty()) {
            return;
        }

        DB::table('employee_role_assignment')
            ->whereIn('role_id', $roleIds)
            ->pluck('employee_id')
            ->unique()
            ->each(fn ($empId) => \Illuminate\Support\Facades\Cache::forget("perm_slugs_{$empId}"));
    }
};
