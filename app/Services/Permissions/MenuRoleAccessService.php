<?php

namespace App\Services\Permissions;

use App\Models\EmployeeRole;
use Illuminate\Support\Facades\DB;

/**
 * Menu Access dari sudut pandang MENU (Menu List → "Siapa yang punya akses"): satu menu, banyak role.
 *
 * Tidak ada skema atau aturan baru: seluruh penulisan memakai RoleMenuAccessService::apply() PER ROLE, sehingga semua
 * pengaman yang sama tetap berlaku (role terlindungi, ESS global, lockout, riwayat `role_menu_changes`, audit, undo).
 * Kelas ini hanya (1) membaca matriks dari sisi menu, (2) menerjemahkan "role X → set/revoke" menjadi perubahan per role,
 * dan (3) membungkus beberapa role dalam SATU transaksi — gagal di satu role = tidak ada yang tersimpan.
 *
 * Cakupan (scope): `self` = menu itu saja; `subtree` = menu itu + seluruh turunannya (mis. satu kelompok seksi).
 */
class MenuRoleAccessService
{
    /** Ambang peringatan: perubahan yang menyentuh karyawan sebanyak ini ditandai di ringkasan. */
    public const LARGE_IMPACT = 20;

    public function __construct(private readonly RoleMenuAccessService $roles)
    {
    }

    // ───────────────────────────────────────────────────────────────────────────── baca

    /** @return array<string,mixed> */
    public function access(int $menuId): array
    {
        $menu = $this->menu($menuId);
        $rules = $this->roles->rules();
        $grants = DB::table('role_menu')->where('menu_id', $menuId)->get()->keyBy('role_id');

        $list = [];
        foreach (EmployeeRole::withCount('employees')->orderBy('name')->get() as $role) {
            $list[] = [
                'id' => (int) $role->id, 'name' => $role->name, 'description' => $role->description,
                'employees_count' => (int) $role->employees_count,
                'state' => MenuAccessRules::stateFromRow($grants->get($role->id)),
                'protected' => $rules->isProtected((int) $role->id, $menu->slug),
            ];
        }
        $withAccess = array_values(array_filter($list, fn ($r) => $r['state'] !== null));
        $employees = $withAccess
            ? (int) DB::table('employee_role_assignment')->whereIn('role_id', array_column($withAccess, 'id'))->distinct()->count('employee_id') : 0;

        $descendants = $this->descendantIds($menuId);
        $labels = (array) (config('menu_access.crud_labels')[$menu->slug] ?? null);
        $crud = $rules->crudEnforced($menu->slug);

        return [
            'menu' => [
                'id' => (int) $menu->id, 'name' => $menu->name, 'slug' => $menu->slug, 'type' => $menu->type, 'is_active' => (bool) $menu->is_active,
                'module' => $rules->moduleOf($menu->slug)['label'], 'class' => $rules->classOf($menu->slug),
                'path' => $this->path($menu),
                'children' => count($descendants),
                // Kotak C/E/D yang benar-benar ditegakkan server untuk menu ini (kosong = hanya View).
                'crud' => $crud ? array_values(array_filter(['c', 'e', 'd'], fn ($k) => $labels === [] || isset($labels[$k]))) : [],
                'crud_hints' => $labels,
            ],
            'roles' => $list,
            'totals' => ['roles' => count($withAccess), 'employees' => $employees],
        ];
    }

    /**
     * Riwayat perubahan menu INI (dari halaman ini maupun halaman Role). `only_this` = catatan hanya menyentuh menu ini,
     * sehingga aman dibatalkan dari sini.
     *
     * @return array<int,array<string,mixed>>
     */
    public function history(int $menuId, int $limit = 30): array
    {
        $menu = $this->menu($menuId);
        $rows = DB::table('role_menu_changes as c')
            ->join('employee_role as r', 'r.id', '=', 'c.role_id')
            ->leftJoin('employee_basic_data as b', 'b.employee_id', '=', 'c.actor_employee_id')
            ->whereRaw("JSON_CONTAINS(c.changes, JSON_OBJECT('menu_id', ?))", [$menuId])
            ->orderByDesc('c.id')->limit($limit)
            ->get(['c.id', 'c.role_id', 'r.name as role_name', 'c.kind', 'c.changes', 'c.reason', 'c.reverted_by_change_id', 'c.created_at',
                'c.actor_employee_id', 'b.first_name', 'b.last_name', 'b.nick_name']);

        $out = [];
        foreach ($rows as $row) {
            $entries = json_decode($row->changes, true) ?: [];
            $mine = collect($entries)->firstWhere('menu_id', $menuId);
            if (!$mine) {
                continue;
            }
            $out[] = [
                'id' => (int) $row->id, 'role_id' => (int) $row->role_id, 'role' => $row->role_name, 'kind' => $row->kind,
                'before' => $mine['before'] ?? null, 'after' => $mine['after'] ?? null,
                'only_this' => count($entries) === 1,
                'reverted' => $row->reverted_by_change_id !== null,
                'reason' => $row->reason,
                'actor' => trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')) ?: ($row->nick_name ?: ('#' . $row->actor_employee_id)),
                'at' => $row->created_at,
            ];
        }

        return $out;
    }

    // ───────────────────────────────────────────────────────────────────────────── tulis

    /**
     * Hitung perubahan TANPA menulis (ringkasan sebelum simpan).
     *
     * @param  array<int,array<string,mixed>>  $changes  [{role_id, action: 'set'|'revoke', can_create?, can_edit?, can_delete?}]
     * @return array<string,mixed>
     */
    public function plan(int $menuId, string $scope, array $changes): array
    {
        try {
            $built = $this->build($menuId, $scope, $changes);
        } catch (PermissionChangeException $e) {
            return ['ok' => false, 'code' => $e->codeName, 'message' => $e->getMessage()];
        }

        $rows = [];
        $byRole = [];
        foreach ($built['perRole'] as $roleId => $roleChanges) {
            $res = $this->roles->plan($roleId, $roleChanges);
            if (!$res['ok']) {
                return $res;
            }
            foreach ($res['rows'] as $r) {
                $rows[] = ['role_id' => $roleId, 'role' => $built['roleNames'][$roleId]] + $r;
            }
            $byRole[$roleId] = ['role_id' => $roleId, 'role' => $built['roleNames'][$roleId], 'employees' => $built['employees'][$roleId]] + MenuAccessRules::summarize($res['rows']);
        }
        $touched = array_keys(array_filter($byRole, fn ($r) => $r['total'] > 0));
        $employees = $touched ? (int) DB::table('employee_role_assignment')->whereIn('role_id', $touched)->distinct()->count('employee_id') : 0;

        $warnings = [];
        $sensitive = collect($rows)->contains(fn ($r) => $this->roles->rules()->classOf($r['slug']) === 'sensitive');
        if ($sensitive) {
            $warnings[] = 'This changes a sensitive permission (salary, bank, identity, payroll, roles…). Double-check the roles below.';
        }
        if ($employees >= self::LARGE_IMPACT && collect($rows)->contains(fn ($r) => in_array($r['kind'], ['revoke', 'change'], true))) {
            $warnings[] = "{$employees} employees are affected. People who lose access are signed out of that page immediately on their next request.";
        }
        if ($built['skipped']) {
            $warnings[] = $built['skipped'] . ' item(s) were skipped because they cannot be changed per role (always-on ESS items or inactive menus).';
        }

        return ['ok' => true, 'code' => 'ok', 'rows' => $rows, 'roles' => array_values($byRole), 'summary' => MenuAccessRules::summarize($rows),
            'employees_affected' => $employees, 'warnings' => $warnings, 'menus' => count($built['menuIds'])];
    }

    /**
     * Terapkan sebagai SATU transaksi lintas role. @return array<string,mixed> + change_ids
     */
    public function apply(int $menuId, string $scope, array $changes, int $actorId, ?string $reason): array
    {
        try {
            $built = $this->build($menuId, $scope, $changes);
            $ids = [];
            $summary = ['grant' => 0, 'revoke' => 0, 'change' => 0, 'total' => 0];
            DB::transaction(function () use ($built, $actorId, $reason, &$ids, &$summary) {
                foreach ($built['perRole'] as $roleId => $roleChanges) {
                    $res = $this->roles->apply($roleId, $roleChanges, $actorId, $reason);
                    if (!$res['ok']) {
                        // Satu role gagal → batalkan SELURUH perubahan (transaksi luar di-rollback oleh pengecualian ini).
                        throw new PermissionChangeException($res['code'], "{$built['roleNames'][$roleId]}: " . $res['message'], $res['conflicts'] ?? []);
                    }
                    if (!empty($res['change_id'])) {
                        $ids[] = (int) $res['change_id'];
                    }
                    foreach (['grant', 'revoke', 'change', 'total'] as $k) {
                        $summary[$k] += (int) ($res['summary'][$k] ?? 0);
                    }
                }
            });
        } catch (PermissionChangeException $e) {
            return ['ok' => false, 'code' => $e->codeName, 'message' => $e->getMessage()] + ($e->extra ? ['conflicts' => $e->extra] : []);
        }

        foreach (array_keys($built['perRole']) as $roleId) {
            $this->roles->flushCache($roleId);   // sekali lagi setelah commit luar
        }

        return ['ok' => true, 'code' => $ids ? 'ok' : 'noop', 'message' => $ids ? 'Access updated.' : 'No changes to save.', 'change_ids' => $ids, 'summary' => $summary];
    }

    /**
     * Batalkan beberapa catatan sekaligus (satu transaksi). Hanya catatan yang SELURUHNYA menyentuh menu dalam cakupan ini.
     *
     * @param  int[]  $changeIds
     */
    public function undo(int $menuId, array $changeIds, int $actorId): array
    {
        $this->menu($menuId);
        $allowed = array_flip(array_merge([$menuId], $this->descendantIds($menuId)));
        $changeIds = array_values(array_unique(array_map('intval', $changeIds)));
        if (!$changeIds || count($changeIds) > 200) {
            return ['ok' => false, 'code' => 'invalid', 'message' => 'Nothing to undo.'];
        }
        foreach (DB::table('role_menu_changes')->whereIn('id', $changeIds)->get(['id', 'changes']) as $row) {
            foreach (json_decode($row->changes, true) ?: [] as $e) {
                if (!isset($allowed[(int) ($e['menu_id'] ?? 0)])) {
                    return ['ok' => false, 'code' => 'out_of_scope', 'message' => 'That change also touched other menus. Undo it from the role’s own history instead.'];
                }
            }
        }

        $roleIds = DB::table('role_menu_changes')->whereIn('id', $changeIds)->pluck('role_id')->unique()->all();
        try {
            DB::transaction(function () use ($changeIds, $actorId) {
                foreach ($changeIds as $id) {
                    $res = $this->roles->undo($id, $actorId);
                    if (!$res['ok']) {
                        throw new PermissionChangeException($res['code'], $res['message'], $res['conflicts'] ?? []);
                    }
                }
            });
        } catch (PermissionChangeException $e) {
            return ['ok' => false, 'code' => $e->codeName, 'message' => $e->getMessage()] + ($e->extra ? ['conflicts' => $e->extra] : []);
        }
        foreach ($roleIds as $roleId) {
            $this->roles->flushCache((int) $roleId);
        }

        return ['ok' => true, 'code' => 'ok', 'message' => 'Change undone.'];
    }

    // ───────────────────────────────────────────────────────────────────────────── internal

    /**
     * Terjemahkan permintaan sisi-menu menjadi perubahan per role.
     *
     * @return array{perRole: array<int,array>, roleNames: array<int,string>, employees: array<int,int>, menuIds: int[], skipped: int}
     */
    private function build(int $menuId, string $scope, array $changes): array
    {
        $menu = $this->menu($menuId);
        $rules = $this->roles->rules();
        if (!in_array($scope, ['self', 'subtree'], true)) {
            throw new PermissionChangeException('invalid', 'Unknown scope.');
        }
        if (!$changes) {
            throw new PermissionChangeException('invalid', 'Choose at least one role to change.');
        }
        if (count($changes) > 500) {
            throw new PermissionChangeException('too_many', 'Too many roles in one save (max 500).');
        }

        $menuIds = $scope === 'subtree' ? array_merge([$menuId], $this->descendantIds($menuId)) : [$menuId];
        $menus = DB::table('menu')->whereIn('id', $menuIds)->get(['id', 'slug', 'is_active'])->keyBy('id');

        $roleModels = EmployeeRole::withCount('employees')->whereIn('id', array_map(fn ($c) => (int) ($c['role_id'] ?? 0), $changes))->get()->keyBy('id');
        $perRole = [];
        $names = [];
        $employees = [];
        $skipped = 0;
        $seen = [];
        foreach ($changes as $c) {
            $roleId = (int) ($c['role_id'] ?? 0);
            $role = $roleModels->get($roleId);
            if (!$role) {
                throw new PermissionChangeException('invalid_role', "Role #{$roleId} does not exist.");
            }
            if (isset($seen[$roleId])) {
                continue;
            }
            $seen[$roleId] = true;
            $revoke = ($c['action'] ?? 'set') === 'revoke';

            $list = [];
            foreach ($menuIds as $mid) {
                $m = $menus->get($mid);
                // Menu di cakupan luas yang tak bisa diubah per role dilewati; pada cakupan satu menu galatnya dibiarkan muncul.
                if ($scope === 'subtree' && ($rules->isGlobalEss($m->slug) || (!$revoke && !$m->is_active))) {
                    $skipped++;
                    continue;
                }
                $list[] = $revoke
                    ? ['menu_id' => $mid, 'revoke' => true]
                    : ['menu_id' => $mid, 'can_view' => true, 'can_create' => !empty($c['can_create']), 'can_edit' => !empty($c['can_edit']), 'can_delete' => !empty($c['can_delete'])];
            }
            $perRole[$roleId] = $list;
            $names[$roleId] = $role->name;
            $employees[$roleId] = (int) $role->employees_count;
        }

        return ['perRole' => $perRole, 'roleNames' => $names, 'employees' => $employees, 'menuIds' => $menuIds, 'skipped' => (int) ($skipped / max(1, count($perRole)))];
    }

    private function menu(int $menuId): object
    {
        $menu = DB::table('menu')->where('id', $menuId)->first(['id', 'parent_id', 'name', 'slug', 'type', 'is_active']);
        if (!$menu) {
            throw new PermissionChangeException('not_found', 'Menu not found.');
        }

        return $menu;
    }

    /** @return int[] id seluruh turunan (rekursif) */
    private function descendantIds(int $menuId): array
    {
        $children = DB::table('menu')->get(['id', 'parent_id'])->groupBy('parent_id');
        $out = [];
        $queue = [$menuId];
        while ($queue) {
            $id = array_shift($queue);
            foreach ($children->get($id, collect()) as $child) {
                if (!in_array((int) $child->id, $out, true)) {
                    $out[] = (int) $child->id;
                    $queue[] = (int) $child->id;
                }
            }
        }

        return $out;
    }

    /** Jalur induk, mis. "Master › Employee › Employee detail". @return string[] */
    private function path(object $menu): array
    {
        $names = [];
        $parentId = $menu->parent_id;
        $guard = 0;
        while ($parentId && $guard++ < 8) {
            $p = DB::table('menu')->where('id', $parentId)->first(['name', 'parent_id']);
            if (!$p) {
                break;
            }
            array_unshift($names, $p->name);
            $parentId = $p->parent_id;
        }

        return $names;
    }
}
