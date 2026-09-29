<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Perluas akses menu 'ai-research' ke setiap role yang sudah punya
 * 'tickets.inbox' — sebelum ini HANYA EC Administrator yang punya grant
 * 'ai-research' (dicek langsung ke DB saat merancang fitur ini), sementara
 * tombol "Ask AI" baru di halaman tiket (lihat AiResearchController::
 * openForTicket()) sengaja ditujukan untuk team lead & member tiket biasa —
 * role yang sama yang sudah bisa membuka daftar tiket.
 *
 * Pola migrasi ini SAMA PERSIS dengan 2026_08_13_000001_
 * activate_master_section_permissions.php (backfill grant ke role yang
 * sudah punya permission "induk"), bukan MenuRegistrar::register() biasa —
 * 'ai-research' BUKAN slug baru, jadi bukan pelanggaran aturan baku
 * "slug baru = admin-only", ini murni memperluas siapa yang sudah dianggap
 * layak memakai fitur yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->backfill('tickets.inbox', 'ai-research');
    }

    public function down(): void
    {
        // Kembali ke status quo sebelum migrasi ini: cabut grant 'ai-research'
        // dari role yang TIDAK PUNYA-nya sebelum migrasi ini berjalan. Karena
        // kita tidak merekam siapa saja yang sudah punya sebelumnya, cara
        // paling aman adalah cabut dari SEMUA KECUALI EC Administrator (satu-
        // satunya yang terkonfirmasi sudah punya grant ini sebelum migrasi).
        $menuId = DB::table('menu')->where('slug', 'ai-research')->value('id');
        if (!$menuId) {
            return;
        }

        $ecAdminRoleId = DB::table('employee_role')->where('name', 'EC Administrator')->value('id');

        $affectedRoleIds = DB::table('role_menu')
            ->where('menu_id', $menuId)
            ->when($ecAdminRoleId, fn ($q) => $q->where('role_id', '!=', $ecAdminRoleId))
            ->pluck('role_id');

        if ($affectedRoleIds->isEmpty()) {
            return;
        }

        $this->flushCacheForRoles($affectedRoleIds);

        DB::table('role_menu')
            ->where('menu_id', $menuId)
            ->whereIn('role_id', $affectedRoleIds)
            ->delete();
    }

    /**
     * Beri view pada $targetSlug ke tiap role yang sudah punya $parentSlug.
     * Grant yang sudah ada dibiarkan (tidak dobel, tidak ditimpa).
     */
    private function backfill(string $parentSlug, string $targetSlug): void
    {
        $parentId = DB::table('menu')->where('slug', $parentSlug)->value('id');
        $targetId = DB::table('menu')->where('slug', $targetSlug)->value('id');
        if (!$parentId || !$targetId) {
            return;
        }

        $roleIds = DB::table('role_menu')
            ->where('menu_id', $parentId)
            ->where('can_view', true)
            ->pluck('role_id');

        if ($roleIds->isEmpty()) {
            return;
        }

        $existingRoleIds = DB::table('role_menu')
            ->where('menu_id', $targetId)
            ->whereIn('role_id', $roleIds)
            ->pluck('role_id')
            ->flip();

        $now = now();
        $rows = [];
        foreach ($roleIds as $roleId) {
            if ($existingRoleIds->has($roleId)) {
                continue;
            }

            $rows[] = [
                'role_id'    => $roleId,
                'menu_id'    => $targetId,
                'can_view'   => true,
                'can_create' => false,
                'can_edit'   => false,
                'can_delete' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows) {
            DB::table('role_menu')->insert($rows);
        }

        $this->flushCacheForRoles($roleIds);
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
