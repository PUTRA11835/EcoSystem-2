<?php

namespace App\Services\Permissions;

/**
 * Menyusun daftar `menu` mentah (satu baris per slug) menjadi BARIS TAMPIL untuk halaman Menu Access.
 *
 * Masalahnya: tabel `menu` menyimpan halaman, tab, dan izin aksi ("Approve / Reject", "Export Excel", "Update …")
 * sebagai baris yang setara. Ditampilkan apa adanya, admin melihat puluhan baris dengan satu kotak yang artinya
 * berbeda-beda. Di sini kita HANYA mengubah penyajian — tidak ada slug, grant, atau aturan penegakan yang berubah:
 *
 *   - Halaman  = satu baris. Kolom View/Create/Edit/Delete adalah kotak centang.
 *   - Izin aksi (slug fungsi) dilipat ke baris halaman pemiliknya: `create|add-new` → kolom Create,
 *     `edit|update` → Edit, `delete` → Delete, `manage` → kolom yang disebut namanya ("Create / Delete").
 *     Aksi lain (approve, export, review, …) masuk kolom "Other actions". Kotak itu tetap menulis ke slug fungsinya
 *     sendiri, jadi database & middleware tidak berubah.
 *   - Tab = baris sendiri (jenis `tab`) dengan izin sendiri, dikelompokkan per halaman/hub.
 *   - Seksi detail (`<base>.view` + `.edit/.update/.manage`) = satu baris tab; View-nya adalah slug `.view`.
 *
 * Kelas ini murni (tanpa database) supaya bisa diuji unit.
 *
 * Bentuk satu baris:
 *   key, kind (page|tab|section|action|group), name, slug, main (menu id untuk kolom View), menu_type,
 *   parent_key, hub {key,label,order}|null,
 *   cells {c,e,d}: null | {id, k:'v'|'c'|'e'|'d', label, slug, covers:[c,e,d...]},
 *   actions: [{id, k:'v', label, slug}], menu_ids: [id...]
 */
final class MenuAccessLayout
{
    private const UMBRELLA = 'general';

    public function __construct(private readonly array $config)
    {
    }

    /**
     * @param  array<int, array{id:int,parent_id:?int,name:string,slug:string,type:string}>  $menus  urutan = urutan tampil
     * @param  callable(string):bool|null  $crudEnforced  apakah slug menegakkan C/E/D di server
     * @return array<int, array<string,mixed>>
     */
    public function build(array $menus, ?callable $crudEnforced = null): array
    {
        $crudEnforced ??= fn (string $slug): bool => in_array($slug, (array) ($this->config['crud_enforced'] ?? []), true);

        $byId = $bySlug = $order = [];
        foreach ($menus as $i => $m) {
            $byId[$m['id']] = $m;
            $bySlug[$m['slug']] = $m;
            $order[$m['id']] = $i;
        }

        // slug → [hub, urutan tab]
        $hubOfSlug = [];
        foreach ((array) ($this->config['hubs'] ?? []) as $hubIndex => $hub) {
            foreach ($hub['tabs'] as $n => $slug) {
                $hubOfSlug[$slug] = ['key' => $hub['key'], 'label' => $hub['label'], 'order' => $hubIndex * 100 + $n];
            }
        }

        // Seksi: ada fungsi `<base>.view`
        $sections = [];   // base => ['view' => menu, 'group' => menu|null, 'fns' => [menu...]]
        foreach ($menus as $m) {
            if ($m['type'] === 'function' && str_ends_with($m['slug'], '.view')) {
                $base = substr($m['slug'], 0, -5);
                $sections[$base] = ['view' => $m, 'group' => $bySlug[$base] ?? null, 'fns' => []];
            }
        }
        $absorbed = [];   // menu id → true (tidak jadi baris sendiri)
        foreach ($sections as $base => &$sec) {
            if ($sec['group'] !== null) {
                $absorbed[$sec['group']['id']] = true;
            }
            $absorbed[$sec['view']['id']] = true;
        }
        unset($sec);
        foreach ($menus as $m) {
            if ($m['type'] !== 'function' || isset($absorbed[$m['id']]) || isset($hubOfSlug[$m['slug']])) {
                continue;
            }
            $base = $this->baseOf($m['slug']);
            if ($base !== null && isset($sections[$base])) {
                $sections[$base]['fns'][] = $m;
                $absorbed[$m['id']] = true;
            }
        }

        // Pemilik fungsi biasa
        $owned = [];   // owner id → [fn menus]
        foreach ($menus as $m) {
            if ($m['type'] !== 'function' || isset($absorbed[$m['id']]) || isset($hubOfSlug[$m['slug']])) {
                continue;
            }
            $owner = $this->ownerOf($m, $byId, $bySlug, $hubOfSlug, $absorbed);
            if ($owner !== null) {
                $parent = $m['parent_id'] ? ($byId[$m['parent_id']] ?? null) : null;
                if ($parent && $parent['type'] === 'function') {
                    $m['_via'] = $this->stripAction($parent['name']);   // sub-aksi dari sebuah aksi: selalu "Other actions"
                }
                $owned[$owner['id']][] = $m;
                $absorbed[$m['id']] = true;
            }
        }

        $rowKeyOfMenu = [];
        $rows = [];

        // Baris dari menu (halaman / tab hub / grup / fungsi yatim)
        foreach ($menus as $m) {
            if (isset($absorbed[$m['id']])) {
                continue;
            }
            $hub = $hubOfSlug[$m['slug']] ?? null;
            $kind = $hub ? 'tab' : ($m['type'] === 'function' ? 'action' : ($m['type'] === 'group' ? 'group' : 'page'));
            $key = 'm' . $m['id'];
            $row = $this->blank($key, $kind, $m['name'], $m['slug'], $m['id'], $m['type'], $m['parent_id'], $hub);
            $row['menu_ids'] = [$m['id']];

            if ($crudEnforced($m['slug'])) {
                foreach (['c' => 'Create', 'e' => 'Edit', 'd' => 'Delete'] as $k => $label) {
                    $row['cells'][$k] = ['id' => $m['id'], 'k' => $k, 'label' => $label, 'slug' => $m['slug'], 'covers' => [$k]];
                }
            }
            $this->fold($row, $owned[$m['id']] ?? [], $m['name']);
            $rows[$key] = $row;
            $rowKeyOfMenu[$m['id']] = $key;
        }

        // Baris dari seksi
        foreach ($sections as $base => $sec) {
            $view = $sec['view'];
            $name = $sec['group']['name'] ?? $this->stripAction($view['name']);
            $key = 's:' . $base;
            $parentId = $sec['group']['parent_id'] ?? $view['parent_id'];
            $hub = $this->sectionHub($base);
            $row = $this->blank($key, 'section', $name, $base, $view['id'], 'function', $parentId, $hub);
            $row['menu_ids'] = [$view['id']];
            $row['order_hint'] = $order[($sec['group']['id'] ?? $view['id'])];
            $this->fold($row, $sec['fns'], $name);
            $rows[$key] = $row;
        }

        // parent_key: baris milik menu induk (naik sampai ada baris)
        foreach ($rows as $key => &$row) {
            $pid = $row['_parent_id'];
            unset($row['_parent_id']);
            $row['parent_key'] = null;
            $guard = 0;
            while ($pid && $guard++ < 10) {
                if (isset($rowKeyOfMenu[$pid]) && $rowKeyOfMenu[$pid] !== $key) {
                    $row['parent_key'] = $rowKeyOfMenu[$pid];
                    break;
                }
                $pid = $byId[$pid]['parent_id'] ?? null;
            }
        }
        unset($row);

        // urutan: sesuai urutan menu aslinya
        $sorted = array_values($rows);
        usort($sorted, function ($a, $b) use ($order) {
            return ($a['order_hint'] ?? $order[$a['main']] ?? 0) <=> ($b['order_hint'] ?? $order[$b['main']] ?? 0);
        });
        foreach ($sorted as &$r) {
            unset($r['order_hint']);
        }

        return $sorted;
    }

    // ───────────────────────────────────────────────────────────── helpers

    private function blank(string $key, string $kind, string $name, string $slug, int $main, string $menuType, ?int $parentId, ?array $hub): array
    {
        return [
            'key' => $key, 'kind' => $kind, 'name' => $name, 'slug' => $slug, 'main' => $main, 'menu_type' => $menuType,
            '_parent_id' => $parentId, 'hub' => $hub,
            'cells' => ['c' => null, 'e' => null, 'd' => null], 'actions' => [], 'menu_ids' => [],
        ];
    }

    /** Lipat fungsi-fungsi ke kolom C/E/D atau "Other actions" pada $row. */
    private function fold(array &$row, array $fns, string $ownerName): void
    {
        foreach ($fns as $f) {
            $row['menu_ids'][] = $f['id'];
            $label = $this->stripAction($f['name']);
            if (isset($f['_via'])) {
                $row['actions'][] = ['id' => $f['id'], 'k' => 'v', 'label' => $f['_via'] . ' › ' . $label, 'name' => $f['name'], 'slug' => $f['slug']];
                continue;
            }
            $covers = array_values(array_filter(self::verbsOf($f), fn ($k) => $row['cells'][$k] === null));
            if ($covers === []) {
                $row['actions'][] = ['id' => $f['id'], 'k' => 'v', 'label' => $label, 'name' => $f['name'], 'slug' => $f['slug']];
                continue;
            }
            foreach ($covers as $k) {
                $row['cells'][$k] = ['id' => $f['id'], 'k' => 'v', 'label' => $label, 'name' => $f['name'], 'slug' => $f['slug'], 'covers' => $covers];
            }
        }
    }

    /** Kolom mana yang dikendalikan fungsi ini: subset ['c','e','d'] (kosong = aksi lain). */
    public static function verbsOf(array $fn): array
    {
        $last = strtolower(substr(strrchr('.' . $fn['slug'], '.'), 1));
        if (preg_match('/^(create|add-new|add|btn-create)(-|$)/', $last)) {
            return ['c'];
        }
        if (preg_match('/^(edit|update)(-|$)/', $last)) {
            return ['e'];
        }
        if (preg_match('/^delete(-|$)/', $last)) {
            return ['d'];
        }
        if ($last === 'manage' || $last === 'action') {
            $name = strtolower($fn['name']);
            $out = [];
            if (str_contains($name, 'create')) {
                $out[] = 'c';
            }
            if (str_contains($name, 'edit') || str_contains($name, 'update')) {
                $out[] = 'e';
            }
            if (str_contains($name, 'delete')) {
                $out[] = 'd';
            }

            return $out;
        }

        return [];
    }

    /** "KPI Evaluation — Approve / Reject" → "Approve / Reject"; "Documents — View" → "Documents". */
    private function stripAction(string $name): string
    {
        $parts = preg_split('/\s+[—–]\s+/u', $name);
        $tail = trim((string) end($parts));
        if (count($parts) > 1 && preg_match('/^(view|edit|update)$/i', $tail)) {
            return trim(implode(' — ', array_slice($parts, 0, -1)));
        }

        return count($parts) > 1 ? $tail : $name;
    }

    private function baseOf(string $slug): ?string
    {
        $pos = strrpos($slug, '.');

        return $pos === false ? null : substr($slug, 0, $pos);
    }

    private function sectionHub(string $base): ?array
    {
        foreach ((array) ($this->config['section_hubs'] ?? []) as $prefix => $label) {
            if (str_starts_with($base . '.', $prefix)) {
                return ['key' => 'sec:' . rtrim($prefix, '.'), 'label' => $label, 'order' => 100000];
            }
        }

        return null;
    }

    /**
     * Menu pemilik sebuah fungsi, urutan prioritas:
     *   1. awalan slug terpanjang yang berupa HALAMAN/tab (bukan payung `general`, bukan grup);
     *   2. menu induk di tabel (naik melewati fungsi induk) — halaman atau grup;
     *   3. awalan slug yang berupa GRUP.
     * null = fungsi berdiri sendiri.
     */
    private function ownerOf(array $fn, array $byId, array $bySlug, array $hubOfSlug, array $absorbed): ?array
    {
        $groupFallback = null;
        $slug = $fn['slug'];
        while (($pos = strrpos($slug, '.')) !== false) {
            $slug = substr($slug, 0, $pos);
            $cand = $bySlug[$slug] ?? null;
            if (!$cand || $cand['id'] === $fn['id'] || $slug === self::UMBRELLA || isset($absorbed[$cand['id']])) {
                continue;
            }
            if ($cand['type'] === 'page' || isset($hubOfSlug[$slug])) {
                return $cand;
            }
            if ($cand['type'] === 'group') {
                $groupFallback ??= $cand;
            }
        }

        $p = $fn['parent_id'] ? ($byId[$fn['parent_id']] ?? null) : null;
        $guard = 0;
        while ($p && $guard++ < 10) {
            if ($p['slug'] !== self::UMBRELLA && ($p['type'] !== 'function' || isset($hubOfSlug[$p['slug']])) && !isset($absorbed[$p['id']])) {
                return $p;
            }
            $p = $p['parent_id'] ? ($byId[$p['parent_id']] ?? null) : null;
        }

        return $groupFallback;
    }
}
