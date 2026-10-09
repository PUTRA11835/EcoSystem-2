<?php

namespace App\Services\Permissions;

use Illuminate\Support\Str;

/**
 * Aturan MURNI (tanpa database/container) untuk halaman Menu Access — klasifikasi menu, normalisasi perubahan,
 * dan perbandingan keadaan. Konfigurasinya dari config/menu_access.php. Dipisah dari RoleMenuAccessService
 * agar bisa diuji unit cepat (pola yang sama dengan OnboardingRules).
 *
 * Keadaan sebuah hibah = null (tidak punya baris `role_menu`) ATAU ['v'=>1,'c'=>0|1,'e'=>0|1,'d'=>0|1].
 * `role_menu` hanya menyimpan baris yang DIBERIKAN (tidak ada can_view=0), jadi "dicabut" = tidak ada baris.
 */
final class MenuAccessRules
{
    public function __construct(private readonly array $config)
    {
    }

    /** @return array{key: string, label: string} */
    public function moduleOf(string $slug): array
    {
        foreach ($this->config['modules'] as $module) {
            if (Str::is($module['match'], $slug)) {
                return ['key' => $module['key'], 'label' => $module['label']];
            }
        }

        return $this->config['other_module'];
    }

    /** Urutan modul untuk kolom kiri (modul "lainnya" terakhir); `group` + `short` bila modul dilipat dalam satu blok. @return array<int, array{key: string, label: string, group?: string, short?: string, about?: string}> */
    public function modules(): array
    {
        $list = array_map(fn ($m) => ['key' => $m['key'], 'label' => $m['label']] + array_filter(['group' => $m['group'] ?? null, 'short' => $m['short'] ?? null, 'about' => $m['about'] ?? null]), $this->config['modules']);
        $list[] = $this->config['other_module'];

        return $list;
    }

    public function isGlobalEss(string $slug): bool
    {
        return in_array($slug, $this->config['global_ess'], true) || Str::is($this->config['global_ess_patterns'], $slug);
    }

    public function isSensitive(string $slug): bool
    {
        return Str::is($this->config['sensitive'], $slug);
    }

    /** Apakah kolom Create/Edit/Delete benar-benar ditegakkan server untuk slug ini. */
    public function crudEnforced(string $slug): bool
    {
        return in_array($slug, $this->config['crud_enforced'], true);
    }

    /** Kelas izin untuk tampilan: 'ess' (terkunci) | 'sensitive' | 'standard'. */
    public function classOf(string $slug): string
    {
        return $this->isGlobalEss($slug) ? 'ess' : ($this->isSensitive($slug) ? 'sensitive' : 'standard');
    }

    public function isProtected(int $roleId, string $slug): bool
    {
        return $roleId === (int) $this->config['protected_role_id'] && in_array($slug, $this->config['protected'], true);
    }

    /**
     * Normalisasi satu permintaan perubahan menjadi keadaan akhir.
     *
     *   {menu_id, revoke:true}                                  → after = null
     *   {menu_id, can_view?, can_create?, can_edit?, can_delete?} → V otomatis bila C/E/D; keempatnya mati → null
     *
     * Bila $crudAllowed = false (menu tak menegakkan C/E/D), kiriman C/E/D diabaikan dan flag lama yang sudah
     * tersimpan ($before) DIPERTAHANKAN apa adanya (tidak dihapus diam-diam; hanya V yang bisa diubah dari layar).
     *
     * @param  array{v:int,c:int,e:int,d:int}|null  $before  keadaan tersimpan saat ini
     * @return array{menu_id: int, after: array{v:int,c:int,e:int,d:int}|null}
     */
    public static function normalize(array $change, bool $crudAllowed = true, ?array $before = null): array
    {
        $menuId = (int) ($change['menu_id'] ?? 0);
        if ($menuId <= 0) {
            throw new \InvalidArgumentException('menu_id is required.');
        }
        if (!empty($change['revoke'])) {
            return ['menu_id' => $menuId, 'after' => null];
        }

        $flag = fn (string $k): int => !empty($change[$k]) ? 1 : 0;
        $c = $crudAllowed ? $flag('can_create') : (int) ($before['c'] ?? 0);
        $e = $crudAllowed ? $flag('can_edit') : (int) ($before['e'] ?? 0);
        $d = $crudAllowed ? $flag('can_delete') : (int) ($before['d'] ?? 0);
        // memberi C/E/D otomatis memberi View; pada menu tanpa penegakan C/E/D, flag lama TIDAK ikut menyalakan View
        $v = ($flag('can_view') || ($crudAllowed && ($c || $e || $d))) ? 1 : 0;

        return ['menu_id' => $menuId, 'after' => $v ? ['v' => 1, 'c' => $c, 'e' => $e, 'd' => $d] : null];
    }

    /** Ubah baris pivot role_menu menjadi keadaan ringkas (atau null). */
    public static function stateFromRow(?object $row): ?array
    {
        if ($row === null) {
            return null;
        }

        return ['v' => 1, 'c' => (int) $row->can_create, 'e' => (int) $row->can_edit, 'd' => (int) $row->can_delete];
    }

    /**
     * Dua keadaan hibah sama? Tidak peduli urutan kunci — kolom JSON MySQL mengurutkan ulang kunci (c,d,e,v), jadi
     * array hasil json_decode TIDAK === array yang dibuat dari baris pivot walau isinya sama (bug yang tertangkap uji undo).
     */
    public static function same(?array $a, ?array $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }
        foreach (['v', 'c', 'e', 'd'] as $k) {
            if ((int) ($a[$k] ?? 0) !== (int) ($b[$k] ?? 0)) {
                return false;
            }
        }

        return true;
    }

    /** @return 'none'|'grant'|'revoke'|'change' */
    public static function kind(?array $before, ?array $after): string
    {
        if (self::same($before, $after)) {
            return 'none';
        }
        if ($before === null) {
            return 'grant';
        }
        if ($after === null) {
            return 'revoke';
        }

        return 'change';
    }

    /**
     * Ringkasan hitungan dari daftar baris perubahan yang punya kunci 'kind'.
     *
     * @return array{grant:int, revoke:int, change:int, total:int}
     */
    public static function summarize(array $rows): array
    {
        $s = ['grant' => 0, 'revoke' => 0, 'change' => 0, 'total' => 0];
        foreach ($rows as $r) {
            if (isset($s[$r['kind']])) {
                $s[$r['kind']]++;
                $s['total']++;
            }
        }

        return $s;
    }

    /** Balikkan satu catatan riwayat menjadi permintaan perubahan (untuk undo). */
    public static function invert(array $changeRow): array
    {
        $before = $changeRow['before'] ?? null;

        return $before === null
            ? ['menu_id' => (int) $changeRow['menu_id'], 'revoke' => true]
            : ['menu_id' => (int) $changeRow['menu_id'], 'can_view' => true, 'can_create' => (bool) $before['c'], 'can_edit' => (bool) $before['e'], 'can_delete' => (bool) $before['d']];
    }
}
