<?php

namespace App\Services\Permissions;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\EmployeeRole;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

/**
 * Menu Access (izin role ↔ menu) — sisi database. Aturan murni ada di MenuAccessRules (HC-D63/D64).
 *
 * Prinsip:
 *  - SEMUA penulisan lewat apply(): satu transaksi, upsert/delete massal pada `role_menu`, satu baris riwayat
 *    (`role_menu_changes`), satu entri AuditLog, dan satu pembersihan cache izin di akhir.
 *  - Format `role_menu` TIDAK berubah (hanya baris yang diberikan; "dicabut" = baris dihapus).
 *  - Pengaman terkunci: setelah menulis, penanggung jawab (actor) WAJIB masih memegang management.roles dan
 *    management.permissions; bila tidak, seluruh transaksi dibatalkan. Slug itu juga tak boleh dicabut dari
 *    role EC Administrator.
 *  - Undo = perubahan baru yang membalik satu catatan riwayat — hanya bila baris yang disentuh masih bernilai
 *    "sesudah" milik catatan itu (jika sudah diubah orang lain → konflik ditampilkan, tak ada yang ditimpa).
 */
class RoleMenuAccessService
{
    private MenuAccessRules $rules;

    /** @var array<string,int>|null */
    private ?array $gateCounts = null;

    public function __construct(?MenuAccessRules $rules = null)
    {
        $this->rules = $rules ?? new MenuAccessRules((array) config('menu_access'));
    }

    public function rules(): MenuAccessRules
    {
        return $this->rules;
    }

    // ───────────────────────────────────────────────────────────────────────────── baca

    /** Seluruh menu + hibah role + metadata tampilan. */
    public function matrix(int $roleId): array
    {
        $role = EmployeeRole::withCount('employees')->findOrFail($roleId);

        $menus = DB::table('menu')->orderBy('parent_id')->orderBy('order_seq')->orderBy('id')
            ->get(['id', 'parent_id', 'name', 'slug', 'type', 'icon', 'order_seq', 'is_active']);
        $grants = DB::table('role_menu')->where('role_id', $roleId)->get()->keyBy('menu_id');
        $gate = $this->gateCounts();

        $list = [];
        foreach ($menus as $m) {
            $module = $this->rules->moduleOf($m->slug);
            $list[] = [
                'id' => (int) $m->id, 'parent_id' => $m->parent_id ? (int) $m->parent_id : null,
                'name' => $m->name, 'slug' => $m->slug, 'type' => $m->type, 'icon' => $m->icon,
                'is_active' => (bool) $m->is_active,
                'module' => $module['key'],
                'class' => $this->rules->classOf($m->slug),          // ess | sensitive | standard
                'crud' => $this->rules->crudEnforced($m->slug),      // C/E/D benar-benar ditegakkan server
                'protected' => $this->rules->isProtected($roleId, $m->slug),
                'gated_routes' => $gate[$m->slug] ?? 0,
            ];
        }

        $state = [];
        foreach ($grants as $menuId => $row) {
            $state[(int) $menuId] = MenuAccessRules::stateFromRow($row);
        }

        return [
            'role' => ['id' => (int) $role->id, 'name' => $role->name, 'description' => $role->description, 'employees_count' => (int) $role->employees_count],
            'modules' => $this->rules->modules(),
            'menus' => $list,
            'grants' => $state,
        ];
    }

    /** Selisih bila role $sourceId DISALIN ke $targetId (hanya daftar; tidak menulis apa pun). */
    public function copyDiff(int $targetId, int $sourceId): array
    {
        $rows = [];
        foreach ($this->differences($sourceId, $targetId) as $d) {
            if ($this->rules->classOf($d['slug']) === 'ess') {
                continue; // Global ESS tak bisa diubah per role (selalu diizinkan backend)
            }
            // dari sudut pandang target: setelah salin, target = source
            $rows[] = $d + ['kind' => MenuAccessRules::kind($d['b'], $d['a'])];
        }

        return ['rows' => $rows, 'summary' => MenuAccessRules::summarize($rows)];
    }

    /** Perbandingan dua role: hanya menu yang berbeda. */
    public function compare(int $aId, int $bId): array
    {
        $a = EmployeeRole::findOrFail($aId);
        $b = EmployeeRole::findOrFail($bId);

        return [
            'a' => ['id' => (int) $a->id, 'name' => $a->name],
            'b' => ['id' => (int) $b->id, 'name' => $b->name],
            'rows' => $this->differences($aId, $bId),
        ];
    }

    public function history(int $roleId, int $limit = 50): array
    {
        $rows = DB::table('role_menu_changes as c')
            ->leftJoin('employee_basic_data as b', 'b.employee_id', '=', 'c.actor_employee_id')
            ->where('c.role_id', $roleId)
            ->orderByDesc('c.id')->limit($limit)
            ->get(['c.id', 'c.kind', 'c.summary', 'c.changes', 'c.reason', 'c.reverts_change_id', 'c.reverted_by_change_id', 'c.created_at',
                'c.actor_employee_id', 'b.first_name', 'b.last_name', 'b.nick_name']);

        return $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'kind' => $r->kind,
            'summary' => json_decode($r->summary, true),
            'changes' => json_decode($r->changes, true),
            'reason' => $r->reason,
            'reverts_change_id' => $r->reverts_change_id ? (int) $r->reverts_change_id : null,
            'reverted' => $r->reverted_by_change_id !== null,
            'actor' => trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')) ?: ($r->nick_name ?: ('#' . $r->actor_employee_id)),
            'at' => $r->created_at,
        ])->all();
    }

    // ───────────────────────────────────────────────────────────────────────────── tulis

    /** Hitung perubahan TANPA menulis (untuk ringkasan sebelum Save). */
    public function plan(int $roleId, array $changes): array
    {
        try {
            $c = $this->compute($roleId, $changes);
        } catch (PermissionChangeException $e) {
            return ['ok' => false, 'code' => $e->codeName, 'message' => $e->getMessage()];
        }

        return ['ok' => true, 'code' => 'ok', 'rows' => $c['rows'], 'summary' => MenuAccessRules::summarize($c['rows'])];
    }

    /**
     * Terapkan perubahan sebagai SATU unit.
     *
     * @param  array<int, array>  $changes  [{menu_id, can_view?, can_create?, can_edit?, can_delete?} | {menu_id, revoke:true}]
     * @return array{ok: bool, code: string, message: string, change_id?: int|null, summary?: array, rows?: array, conflicts?: array}
     */
    public function apply(int $roleId, array $changes, int $actorId, ?string $reason = null, string $kind = 'apply', ?int $revertsChangeId = null): array
    {
        try {
            $result = DB::transaction(function () use ($roleId, $changes, $actorId, $reason, $kind, $revertsChangeId) {
                $c = $this->compute($roleId, $changes, lock: true);
                if (!$c['rows']) {
                    return ['change_id' => null, 'rows' => [], 'summary' => MenuAccessRules::summarize([]), 'noop' => true];
                }

                // Akses penanggung jawab SEBELUM menulis — pengaman terkunci hanya memicu bila perubahan ini yang menghilangkannya.
                $actor = Employee::find($actorId);
                $hadAccess = [];
                foreach ((array) config('menu_access.protected') as $slug) {
                    $hadAccess[$slug] = $actor && $actor->canAccessMenu($slug);
                }

                $now = now();
                if ($c['upserts']) {
                    DB::table('role_menu')->upsert(
                        array_map(fn ($u) => $u + ['created_at' => $now, 'updated_at' => $now], $c['upserts']),
                        ['role_id', 'menu_id'],
                        ['can_view', 'can_create', 'can_edit', 'can_delete', 'updated_at'],
                    );
                }
                if ($c['deletes']) {
                    DB::table('role_menu')->where('role_id', $roleId)->whereIn('menu_id', $c['deletes'])->delete();
                }

                // Pengaman terkunci — dievaluasi terhadap keadaan SETELAH tulis (masih dalam transaksi).
                foreach ($hadAccess as $slug => $had) {
                    if ($had && !$actor->canAccessMenu($slug)) {
                        throw new PermissionChangeException('lockout', "This change would remove your own access to “{$slug}”. Nothing was saved.");
                    }
                }

                $summary = MenuAccessRules::summarize($c['rows']);
                $changeId = DB::table('role_menu_changes')->insertGetId([
                    'role_id' => $roleId, 'actor_employee_id' => $actorId ?: null, 'kind' => $kind,
                    'summary' => json_encode($summary),
                    'changes' => json_encode(array_map(fn ($r) => ['menu_id' => $r['menu_id'], 'slug' => $r['slug'], 'name' => $r['name'], 'before' => $r['before'], 'after' => $r['after']], $c['rows'])),
                    'reason' => $reason !== null ? mb_substr(trim($reason), 0, 255) : null,
                    'reverts_change_id' => $revertsChangeId,
                    'created_at' => $now,
                ]);
                if ($revertsChangeId) {
                    DB::table('role_menu_changes')->where('id', $revertsChangeId)->update(['reverted_by_change_id' => $changeId]);
                }

                $this->audit($c['role'], $c['rows'], $summary, $kind);

                return ['change_id' => $changeId, 'rows' => $c['rows'], 'summary' => $summary, 'noop' => false];
            });
        } catch (PermissionChangeException $e) {
            return ['ok' => false, 'code' => $e->codeName, 'message' => $e->getMessage()] + ($e->extra ? ['conflicts' => $e->extra] : []);
        }

        if (!$result['noop']) {
            $this->flushCache($roleId);   // SEKALI, setelah commit
        }

        return ['ok' => true, 'code' => $result['noop'] ? 'noop' : 'ok', 'message' => $result['noop'] ? 'No changes to save.' : 'Access updated.']
            + ['change_id' => $result['change_id'], 'summary' => $result['summary'], 'rows' => $result['rows']];
    }

    /** Batalkan satu catatan riwayat (hanya bila belum dibatalkan & tak ada konflik). */
    public function undo(int $changeId, int $actorId): array
    {
        $row = DB::table('role_menu_changes')->where('id', $changeId)->first();
        if (!$row) {
            return ['ok' => false, 'code' => 'not_found', 'message' => 'History entry not found.'];
        }
        if ($row->reverted_by_change_id !== null) {
            return ['ok' => false, 'code' => 'already_reverted', 'message' => 'This change was already undone.'];
        }

        $roleId = (int) $row->role_id;
        $entries = json_decode($row->changes, true) ?: [];
        $current = DB::table('role_menu')->where('role_id', $roleId)->whereIn('menu_id', array_column($entries, 'menu_id'))->get()->keyBy('menu_id');

        $conflicts = [];
        foreach ($entries as $e) {
            $now = MenuAccessRules::stateFromRow($current->get($e['menu_id']));
            if (!MenuAccessRules::same($now, $e['after'] ?? null)) {
                $conflicts[] = ['menu_id' => $e['menu_id'], 'slug' => $e['slug'], 'name' => $e['name']];
            }
        }
        if ($conflicts) {
            return ['ok' => false, 'code' => 'conflict', 'message' => count($conflicts) . ' menu(s) were changed again after this entry, so it cannot be undone safely.', 'conflicts' => $conflicts];
        }

        return $this->apply($roleId, array_map([MenuAccessRules::class, 'invert'], $entries), $actorId, "Undo of change #{$changeId}", 'undo', $changeId);
    }

    // ───────────────────────────────────────────────────────────────────────────── internal

    /**
     * Normalisasi + validasi + hitung baris yang benar-benar berubah.
     *
     * @return array{role: EmployeeRole, rows: array, upserts: array, deletes: array}
     */
    private function compute(int $roleId, array $changes, bool $lock = false): array
    {
        $role = EmployeeRole::find($roleId);
        if (!$role) {
            throw new PermissionChangeException('not_found', 'Role not found.');
        }
        if (count($changes) > 2000) {
            throw new PermissionChangeException('too_many', 'Too many changes in one save (max 2000).');
        }

        $ids = [];
        foreach ($changes as $ch) {
            $ids[] = (int) ($ch['menu_id'] ?? 0);
        }
        $menus = DB::table('menu')->whereIn('id', array_unique($ids))->get(['id', 'name', 'slug', 'is_active'])->keyBy('id');
        $q = DB::table('role_menu')->where('role_id', $roleId)->whereIn('menu_id', array_unique($ids));
        $current = ($lock ? $q->lockForUpdate() : $q)->get()->keyBy('menu_id');

        $rows = [];
        $upserts = [];
        $deletes = [];
        $seen = [];
        foreach ($changes as $ch) {
            $menuId = (int) ($ch['menu_id'] ?? 0);
            $menu = $menus->get($menuId);
            if (!$menu) {
                throw new PermissionChangeException('unknown_menu', "Menu #{$menuId} does not exist.");
            }
            if (isset($seen[$menuId])) {
                continue; // permintaan ganda untuk menu yang sama: yang pertama menang
            }
            $seen[$menuId] = true;

            $before = MenuAccessRules::stateFromRow($current->get($menuId));
            $n = MenuAccessRules::normalize($ch, $this->rules->crudEnforced($menu->slug), $before);
            $after = $n['after'];
            $kind = MenuAccessRules::kind($before, $after);
            if ($kind === 'none') {
                continue;
            }
            if ($this->rules->isGlobalEss($menu->slug) && $this->rules->classOf($menu->slug) === 'ess' && $kind !== 'none') {
                // Global ESS selalu diizinkan backend untuk semua karyawan; mengubahnya di role tak berefek → tolak agar tak menyesatkan.
                throw new PermissionChangeException('ess_locked', "“{$menu->slug}” is a Global ESS item (always available to every employee); it cannot be changed per role.");
            }
            if ($after === null && $this->rules->isProtected($roleId, $menu->slug)) {
                throw new PermissionChangeException('protected', "“{$menu->slug}” cannot be revoked from this role (it would lock administrators out of Menu Access).");
            }
            if ($after !== null && !$menu->is_active) {
                throw new PermissionChangeException('inactive', "“{$menu->slug}” is inactive and cannot be granted.");
            }

            $rows[] = ['menu_id' => $menuId, 'slug' => $menu->slug, 'name' => $menu->name, 'before' => $before, 'after' => $after, 'kind' => $kind];
            if ($after === null) {
                $deletes[] = $menuId;
            } else {
                $upserts[] = ['role_id' => $roleId, 'menu_id' => $menuId, 'can_view' => 1, 'can_create' => $after['c'], 'can_edit' => $after['e'], 'can_delete' => $after['d']];
            }
        }

        return ['role' => $role, 'rows' => $rows, 'upserts' => $upserts, 'deletes' => $deletes];
    }

    /** Menu yang hibahnya berbeda antara role A dan B. @return array<int, array{menu_id:int, slug:string, name:string, module:string, a:?array, b:?array}> */
    private function differences(int $aId, int $bId): array
    {
        $menus = DB::table('menu')->orderBy('parent_id')->orderBy('order_seq')->orderBy('id')->get(['id', 'name', 'slug']);
        $ga = DB::table('role_menu')->where('role_id', $aId)->get()->keyBy('menu_id');
        $gb = DB::table('role_menu')->where('role_id', $bId)->get()->keyBy('menu_id');

        $out = [];
        foreach ($menus as $m) {
            $a = MenuAccessRules::stateFromRow($ga->get($m->id));
            $b = MenuAccessRules::stateFromRow($gb->get($m->id));
            if (!MenuAccessRules::same($a, $b)) {
                $out[] = ['menu_id' => (int) $m->id, 'slug' => $m->slug, 'name' => $m->name, 'module' => $this->rules->moduleOf($m->slug)['key'], 'a' => $a, 'b' => $b];
            }
        }

        return $out;
    }

    private function audit(EmployeeRole $role, array $rows, array $summary, string $kind): void
    {
        try {
            $fmt = fn (?array $s) => $s === null ? '—' : 'V' . ($s['c'] ? '+C' : '') . ($s['e'] ? '+E' : '') . ($s['d'] ? '+D' : '');
            $old = [];
            $new = [];
            foreach (array_slice($rows, 0, 200) as $r) {
                $old[$r['slug']] = $fmt($r['before']);
                $new[$r['slug']] = $fmt($r['after']);
            }
            AuditLog::recordAction(
                module: 'Role Access',
                auditableType: 'App\\Models\\EmployeeRole',
                auditableId: $role->id,
                event: 'updated',
                recordLabel: $role->name,
                description: ($kind === 'undo' ? 'undid a change to' : 'changed') . " menu access of role “{$role->name}”: {$summary['grant']} granted, {$summary['revoke']} revoked, {$summary['change']} changed",
                old: $old,
                new: $new,
            );
        } catch (\Throwable $e) {
            Log::warning('Role access: audit log gagal ditulis', ['role_id' => $role->id, 'error' => $e->getMessage()]);
        }
    }

    /** Hapus cache izin SEMUA pegawai pemegang role (slugs + matrix). */
    public function flushCache(int $roleId): void
    {
        $role = EmployeeRole::find($roleId);
        if (!$role) {
            return;
        }
        $role->employees()->pluck('employee.employee_id')->each(function ($empId) {
            Cache::forget("perm_slugs_{$empId}");
            Cache::forget("perm_matrix_{$empId}");
        });
    }

    /**
     * Berapa rute yang digerbang oleh tiap slug (`menu:a,b` dan `menu.can:slug,aksi`). Hanya petunjuk: slug tanpa rute
     * mungkin masih dipakai sidebar/Blade.
     *
     * @return array<string,int>
     */
    private function gateCounts(): array
    {
        if ($this->gateCounts !== null) {
            return $this->gateCounts;
        }
        $counts = [];
        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $mw) {
                if (!is_string($mw)) {
                    continue;
                }
                if (str_starts_with($mw, 'menu:')) {
                    foreach (explode(',', substr($mw, 5)) as $slug) {
                        $counts[$slug] = ($counts[$slug] ?? 0) + 1;
                    }
                } elseif (str_starts_with($mw, 'menu.can:')) {
                    $slug = explode(',', substr($mw, 9))[0];
                    $counts[$slug] = ($counts[$slug] ?? 0) + 1;
                }
            }
        }

        return $this->gateCounts = $counts;
    }
}
