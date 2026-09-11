<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu Access granular per SECTION untuk Delivery → Project dan Delivery → Support.
 *
 * Sebelumnya satu section = satu slug yang membundel semua aksi (mis.
 * `delivery-project.manage-team` berarti lihat + tambah + edit + hapus), dan
 * Delivery Support tidak punya gating section sama sekali. Sekarang tiap
 * section punya slug per aksi supaya mapping role bisa presisi:
 *
 *   <modul>.<section>.view    → section tampil (tanpa ini section disembunyikan)
 *   <modul>.<section>.edit    → boleh mengubah data yang sudah ada
 *   <modul>.<section>.manage  → boleh menambah & menghapus data
 *
 * Section yang memang cuma punya form edit (General/Location/Approval/...)
 * sengaja TIDAK diberi slug `.manage` — biar tidak ada checkbox mati di modal
 * Menu Access.
 *
 * Slug section lama Delivery Project dihapus, tapi grant-nya dipindahkan dulu
 * ke slug baru sehingga tidak ada role yang kehilangan kemampuan.
 */
return new class extends Migration
{
    /**
     * Definisi section: [slug dasar, label, [aksi...]].
     * Slug final = "<base>.<aksi>".
     */
    private const PROJECT_SECTIONS = [
        ['base' => 'delivery-project.general',       'name' => 'General Information',       'actions' => ['view', 'edit']],
        ['base' => 'delivery-project.delivery-data', 'name' => 'Delivery Data',             'actions' => ['view', 'edit']],
        ['base' => 'delivery-project.delivery-info', 'name' => 'Delivery Information & TOP', 'actions' => ['view', 'edit', 'manage']],
        ['base' => 'delivery-project.team',          'name' => 'Team Members',              'actions' => ['view', 'edit', 'manage']],
        ['base' => 'delivery-project.documents',     'name' => 'Documents',                 'actions' => ['view', 'edit', 'manage']],
        ['base' => 'delivery-project.issue-log',     'name' => 'Issue Log',                 'actions' => ['view', 'edit', 'manage']],
        ['base' => 'delivery-project.risk',          'name' => 'Risk Register',             'actions' => ['view', 'edit', 'manage']],
        ['base' => 'delivery-project.location',      'name' => 'Location Information',      'actions' => ['view', 'edit']],
        ['base' => 'delivery-project.planning',      'name' => 'Planning',                  'actions' => ['view', 'edit', 'manage']],
        ['base' => 'delivery-project.plan-cost',     'name' => 'Plan Cost',                 'actions' => ['view', 'edit', 'manage']],
    ];

    private const SUPPORT_SECTIONS = [
        ['base' => 'delivery-support.general',      'name' => 'Support Information',       'actions' => ['view', 'edit']],
        ['base' => 'delivery-support.approval',     'name' => 'Approval Information',      'actions' => ['view', 'edit']],
        ['base' => 'delivery-support.financial',    'name' => 'Financial Information & TOP', 'actions' => ['view', 'edit', 'manage']],
        ['base' => 'delivery-support.team',         'name' => 'Team',                      'actions' => ['view', 'manage']],
        ['base' => 'delivery-support.customer-pic', 'name' => 'Customer PIC',              'actions' => ['view', 'edit']],
        ['base' => 'delivery-support.activities',   'name' => 'Activities & Planning',     'actions' => ['view', 'edit', 'manage']],
        ['base' => 'delivery-support.sla',          'name' => 'SLA Configuration',         'actions' => ['view', 'edit', 'manage']],
        ['base' => 'delivery-support.plan-cost',    'name' => 'Plan Cost',                 'actions' => ['view', 'edit', 'manage']],
        ['base' => 'delivery-support.documents',    'name' => 'Documents & Folder',        'actions' => ['view', 'edit', 'manage']],
    ];

    /** Aksi page-level baru (sejajar delivery-project.delete-project). */
    private const SUPPORT_EXTRA = [
        ['slug' => 'delivery-support.delete-support', 'name' => 'Delete Delivery Support'],
    ];

    private const ACTION_LABEL = [
        'view'   => 'View',
        'edit'   => 'Edit',
        'manage' => 'Create / Delete',
    ];

    /**
     * Slug lama → slug baru yang mewarisi grant-nya. `.view` TIDAK ada di sini:
     * sebelumnya semua section tetap terlihat oleh siapa pun yang bisa membuka
     * halaman detail, jadi `.view` diwariskan dari menu induk (lihat up()).
     */
    private const GRANT_MIGRATION = [
        'delivery-project.edit-general-info'  => ['delivery-project.general.edit'],
        'delivery-project.edit-delivery-info' => ['delivery-project.delivery-data.edit', 'delivery-project.delivery-info.edit', 'delivery-project.delivery-info.manage'],
        'delivery-project.manage-team'        => ['delivery-project.team.edit', 'delivery-project.team.manage'],
        'delivery-project.manage-documents'   => ['delivery-project.documents.edit', 'delivery-project.documents.manage'],
        'delivery-project.manage-issue-log'   => ['delivery-project.issue-log.edit', 'delivery-project.issue-log.manage'],
        'delivery-project.manage-risk'        => ['delivery-project.risk.edit', 'delivery-project.risk.manage'],
        'delivery-project.edit-location-info' => ['delivery-project.location.edit'],
        'delivery-project.manage-planning'    => ['delivery-project.planning.edit', 'delivery-project.planning.manage'],
        'delivery-project.manage-plan-cost'   => ['delivery-project.plan-cost.edit', 'delivery-project.plan-cost.manage'],
        'delivery-support.manage-customer-pic'=> ['delivery-support.customer-pic.edit'],
    ];

    /** Slug lama yang dihapus setelah grant-nya dipindahkan. */
    private const RETIRED_SLUGS = [
        'delivery-project.edit-general-info',
        'delivery-project.edit-delivery-info',
        'delivery-project.manage-team',
        'delivery-project.manage-documents',
        'delivery-project.manage-issue-log',
        'delivery-project.manage-risk',
        'delivery-project.edit-location-info',
        'delivery-project.manage-planning',
        'delivery-project.manage-plan-cost',
        'delivery-support.manage-customer-pic',
    ];

    public function up(): void
    {
        $this->createMenus('delivery.project', self::PROJECT_SECTIONS, 20);
        $this->createMenus('delivery.support', self::SUPPORT_SECTIONS, 20);

        $support = DB::table('menu')->where('slug', 'delivery.support')->first();
        if ($support) {
            foreach (self::SUPPORT_EXTRA as $i => $fn) {
                $this->ensureMenu($support->id, $fn['slug'], $fn['name'], 10 + $i);
            }
        }

        // 1. `.view` diwariskan dari menu induk supaya tidak ada role yang
        //    tiba-tiba kehilangan section yang selama ini mereka lihat.
        $this->grantFromParent('delivery.project', $this->slugsWithAction(self::PROJECT_SECTIONS, 'view'));
        $this->grantFromParent('delivery.support', $this->slugsWithAction(self::SUPPORT_SECTIONS, 'view'));

        // 2. Delivery Support belum pernah punya gating tulis per section —
        //    siapa pun yang bisa membuka halaman detail bisa mengedit. Supaya
        //    perilaku hari ini tidak berubah, `.edit`/`.manage` Support juga
        //    diwariskan dari menu induk (kecuali customer-pic yang sudah punya
        //    slug sendiri, di-handle oleh GRANT_MIGRATION).
        $this->grantFromParent('delivery.support', array_diff(
            array_merge(
                $this->slugsWithAction(self::SUPPORT_SECTIONS, 'edit'),
                $this->slugsWithAction(self::SUPPORT_SECTIONS, 'manage'),
                array_column(self::SUPPORT_EXTRA, 'slug'),
            ),
            ['delivery-support.customer-pic.edit'],
        ));

        // 3. Grant tulis Delivery Project dipindahkan dari slug lamanya.
        foreach (self::GRANT_MIGRATION as $oldSlug => $newSlugs) {
            $this->copyGrants($oldSlug, $newSlugs);
        }

        // 4. Slug lama dipensiunkan.
        foreach (self::RETIRED_SLUGS as $slug) {
            $this->dropMenu($slug);
        }
    }

    public function down(): void
    {
        $slugs = array_merge(
            $this->allSlugs(self::PROJECT_SECTIONS),
            $this->allSlugs(self::SUPPORT_SECTIONS),
            array_column(self::SUPPORT_EXTRA, 'slug'),
        );

        foreach ($slugs as $slug) {
            $this->dropMenu($slug);
        }

        // Bangun ulang slug lama dari menu induknya (grant per-role tidak bisa
        // dipulihkan persis; disamakan dengan role yang bisa melihat induknya).
        $legacy = [
            'delivery-project.edit-general-info'   => ['Edit General Information',  2],
            'delivery-project.edit-delivery-info'  => ['Edit Delivery Information', 3],
            'delivery-project.manage-team'         => ['Manage Team Members',       4],
            'delivery-project.manage-documents'    => ['Manage Documents',          5],
            'delivery-project.manage-issue-log'    => ['Manage Issue Log',          6],
            'delivery-project.manage-risk'         => ['Manage Risk Register',      7],
            'delivery-project.edit-location-info'  => ['Edit Location Information', 8],
            'delivery-project.manage-planning'     => ['Manage Planning',           9],
            'delivery-project.manage-plan-cost'    => ['Manage Plan Cost',          10],
        ];

        $parent = DB::table('menu')->where('slug', 'delivery.project')->first();
        if ($parent) {
            foreach ($legacy as $slug => [$name, $seq]) {
                $this->ensureMenu($parent->id, $slug, $name, $seq);
            }
            $this->grantFromParent('delivery.project', array_keys($legacy));
        }

        $supportParent = DB::table('menu')->where('slug', 'delivery.support')->first();
        if ($supportParent) {
            $this->ensureMenu($supportParent->id, 'delivery-support.manage-customer-pic', 'Manage Customer PIC', 3);
            $this->grantFromParent('delivery.support', ['delivery-support.manage-customer-pic']);
        }
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function slugsWithAction(array $sections, string $action): array
    {
        $out = [];
        foreach ($sections as $s) {
            if (in_array($action, $s['actions'], true)) {
                $out[] = $s['base'] . '.' . $action;
            }
        }
        return $out;
    }

    private function allSlugs(array $sections): array
    {
        $out = [];
        foreach ($sections as $s) {
            foreach ($s['actions'] as $a) {
                $out[] = $s['base'] . '.' . $a;
            }
        }
        return $out;
    }

    private function createMenus(string $parentSlug, array $sections, int $startSeq): void
    {
        $parent = DB::table('menu')->where('slug', $parentSlug)->first();
        if (!$parent) {
            return;
        }

        $seq = $startSeq;
        foreach ($sections as $section) {
            foreach ($section['actions'] as $action) {
                $this->ensureMenu(
                    $parent->id,
                    $section['base'] . '.' . $action,
                    $section['name'] . ' — ' . self::ACTION_LABEL[$action],
                    $seq++,
                );
            }
        }
    }

    private function ensureMenu(int $parentId, string $slug, string $name, int $seq): int
    {
        $existing = DB::table('menu')->where('slug', $slug)->first();
        if ($existing) {
            return $existing->id;
        }

        $now = now();

        return DB::table('menu')->insertGetId([
            'parent_id'  => $parentId,
            'name'       => $name,
            'slug'       => $slug,
            'type'       => 'function',
            'route_name' => null,
            'icon'       => null,
            'order_seq'  => $seq,
            'is_active'  => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /** Beri $slugs ke setiap role yang boleh melihat $parentSlug. */
    private function grantFromParent(string $parentSlug, array $slugs): void
    {
        $parent = DB::table('menu')->where('slug', $parentSlug)->first();
        if (!$parent || empty($slugs)) {
            return;
        }

        $roleIds = DB::table('role_menu')
            ->where('menu_id', $parent->id)
            ->where('can_view', true)
            ->pluck('role_id');

        $this->grant($roleIds, $slugs);
    }

    /** Salin grant dari slug lama ke slug-slug baru. */
    private function copyGrants(string $fromSlug, array $toSlugs): void
    {
        $from = DB::table('menu')->where('slug', $fromSlug)->first();
        if (!$from) {
            return;
        }

        $roleIds = DB::table('role_menu')
            ->where('menu_id', $from->id)
            ->where('can_view', true)
            ->pluck('role_id');

        $this->grant($roleIds, $toSlugs);
    }

    private function grant($roleIds, array $slugs): void
    {
        $now     = now();
        $menuIds = DB::table('menu')->whereIn('slug', $slugs)->pluck('id');

        foreach ($menuIds as $menuId) {
            foreach ($roleIds as $roleId) {
                DB::table('role_menu')->updateOrInsert(
                    ['role_id' => $roleId, 'menu_id' => $menuId],
                    ['can_view' => true, 'can_create' => false, 'can_edit' => false, 'can_delete' => false, 'created_at' => $now, 'updated_at' => $now],
                );
            }
        }
    }

    private function dropMenu(string $slug): void
    {
        $menu = DB::table('menu')->where('slug', $slug)->first();
        if (!$menu) {
            return;
        }
        DB::table('role_menu')->where('menu_id', $menu->id)->delete();
        DB::table('menu')->where('id', $menu->id)->delete();
    }
};
