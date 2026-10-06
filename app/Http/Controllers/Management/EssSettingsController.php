<?php

namespace App\Http\Controllers\Management;

use App\Http\Controllers\Controller;
use App\Models\AppConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EssSettingsController extends Controller
{
    /**
     * 🔴 D182 — Item ESS yang SENGAJA tidak bisa dikelompokkan lewat fitur ini.
     *
     *   - `logout`   : sakelarnya sudah lama tidak terhubung ke rendering apa
     *                  pun di sidebar (tombol Logout selalu tampil dari tempat
     *                  lain, tidak dijaga essConfig sama sekali) — cacat lama
     *                  yang ditemukan saat menulis fitur ini, di luar cakupan
     *                  permintaan, jadi TIDAK diperbaiki di sini. Dikeluarkan
     *                  dari daftar supaya admin tidak mengelompokkan sesuatu
     *                  yang tidak pernah benar-benar tampil di sidebar.
     *   - `events_calendar`, `my_timesheet` : SUDAH punya dropdown sendiri yang
     *                  dipatok kode ("Calendar", lihat sidebar.blade.php) sejak
     *                  sebelum fitur ini ada. Membiarkan keduanya ikut dikelola
     *                  di sini akan membuat satu item bisa "dua tempat sekaligus"
     *                  — dikeluarkan supaya grouping baru dan grouping lama tidak
     *                  saling tumpang tindih.
     */
    public const UNGROUPABLE_ITEMS = ['logout', 'events_calendar', 'my_timesheet'];

    /**
     * Master list of ESS menu items.
     */
    public const ESS_ITEMS = [
        'home' => [
            'name'  => 'Home',
            'route' => 'dashboard',
            'icon'  => 'fas fa-home',
        ],
        'my_profile' => [
            'name'  => 'My Profile',
            'route' => 'profile.my',
            'icon'  => 'fas fa-user-circle',
        ],
        'logout' => [
            'name'  => 'Logout',
            'route' => 'logout',
            'icon'  => 'fas fa-sign-out-alt',
        ],
        'my_attendance' => [
            'name'  => 'My Attendance',
            'route' => 'general.my-attendance.index',
            'icon'  => 'fas fa-user-clock',
        ],
        'my_leave_permit' => [
            'name'  => 'My Leave and Permit',
            'route' => 'my-leave-permit',
            'icon'  => 'fas fa-calendar-check',
        ],
        'overtime' => [
            'name'  => 'Overtime',
            'route' => 'general.my-overtime.index',
            'icon'  => 'fas fa-business-time',
        ],
        'paystub' => [
            'name'  => 'Paystub',
            'route' => null,
            'icon'  => 'fas fa-file-invoice-dollar',
        ],
        // Nama tampilan diselaraskan menjadi "Reimbursement" (26 Agu 2026) agar
        // sama dengan sebutan modulnya di seluruh aplikasi. KUNCI array tetap
        // `expense_reimbursement` karena kunci itulah yang tersimpan di JSON
        // `ess_menu_settings`; menggantinya membuat setelan tersimpan tidak lagi
        // cocok dan sakelarnya diam-diam kembali ke bawaan.
        'expense_reimbursement' => [
            'name'  => 'Reimbursement',
            'route' => 'general.my-reimbursement.index',
            'icon'  => 'fas fa-receipt',
        ],
        // Menunjuk halaman sungguhan sejak 2 Sep 2026 (sebelumnya null =
        // coming-soon). KUNCI array `purchase_request` SENGAJA tidak diubah:
        // kunci itulah yang tersimpan di JSON `ess_menu_settings` (tabel
        // app_config). Menggantinya membuat setelan yang sudah disimpan tidak
        // lagi cocok dan sakelarnya diam-diam kembali ke bawaan — pelajaran yang
        // sama dengan `expense_reimbursement`.
        'purchase_request' => [
            'name'  => 'Purchase Request',
            'route' => 'general.my-purchase-request.index',
            'icon'  => 'fas fa-shopping-cart',
        ],
        // Menunjuk halaman sungguhan sejak 8 Sep 2026 (sebelumnya null =
        // coming-soon). 🔴 KUNCI array `advance_payment_ca` SENGAJA tidak
        // diubah: kunci itulah yang tersimpan di JSON `ess_menu_settings`
        // (tabel app_config). Menggantinya membuat setelan yang sudah
        // disimpan tidak lagi cocok dan sakelarnya diam-diam kembali ke
        // bawaan — pelajaran yang sama dengan `expense_reimbursement` dan
        // `purchase_request`.
        //
        // Nama tampilannya POLOS mengikuti aturan penamaan D151: sisi ESS
        // tanpa singkatan, sisi admin memakai (CA) / (CAR).
        'advance_payment_ca' => [
            'name'  => 'Cash Advance',
            'route' => 'general.my-cash-advance.index',
            'icon'  => 'fas fa-hand-holding-usd',
        ],
        // Nama diselaraskan dengan aturan D151 (ESS polos). `route` TETAP null:
        // halamannya baru dibangun di langkah R2, dan menunjuk nama rute yang
        // belum ada menghasilkan 500, bukan halaman kosong. KUNCI array tidak
        // diubah — kunci itulah yang tersimpan di `ess_menu_settings`.
        'advance_payment_car' => [
            'name'  => 'Cash Advance Report',
            'route' => 'general.my-cash-advance-report.index',
            'icon'  => 'fas fa-file-contract',
        ],
        'loans' => [
            'name'  => 'Loans',
            'route' => null,
            'icon'  => 'fas fa-landmark',
        ],
        'my_letter_requests' => [
            'name'  => 'My Letter Requests',
            'route' => 'general.my-letter-requests.index',
            'icon'  => 'fas fa-envelope-open-text',
        ],
        'my_kpis' => [
            'name'  => 'My KPI',
            'route' => 'general.my-kpi.index',
            'icon'  => 'fas fa-chart-line',
        ],
        'events_calendar' => [
            'name'  => 'Events Calendar',
            'route' => 'calendar.events',
            'icon'  => 'fas fa-calendar-alt',
        ],
        'my_timesheet' => [
            'name'  => 'My Timesheet',
            'route' => 'calendar.timesheets',
            'icon'  => 'fas fa-clock',
        ],
        'ai_assistant' => [
            'name'  => 'AI Assistant',
            'route' => 'ai-assistant',
            'icon'  => 'fas fa-robot',
        ],
        'ai_research' => [
            'name'  => 'AI Research',
            'route' => 'ai-research',
            'icon'  => 'fas fa-magnifying-glass-chart',
        ],
    ];

    /**
     * Display ESS Settings page in Menu Management folder.
     */
    public function index()
    {
        $currentSettings = static::getEssSettings();

        return view('management.ess-settings.index', [
            'items'          => static::ESS_ITEMS,
            'settings'       => $currentSettings,
            'groups'         => static::getEssGroups(),
            'groupableItems' => array_diff(array_keys(static::ESS_ITEMS), static::UNGROUPABLE_ITEMS),
        ]);
    }

    /**
     * Save global ESS menu settings — visibility AND group placement together,
     * satu tombol "Save Changes" yang sama seperti sebelum D182.
     */
    public function update(Request $request)
    {
        $enabledKeys = $request->input('enabled_items', []);

        $newSettings = [];
        foreach (static::ESS_ITEMS as $key => $item) {
            $newSettings[$key] = in_array($key, $enabledKeys);
        }

        AppConfig::setJson('ess_menu_settings', $newSettings, 'Global visibility settings for ESS menu items');

        // 🔴 D182 — penempatan grup dikirim FORM YANG SAMA
        // (`group_assignments[<item_key>]`), disimpan terpisah dari
        // `ess_menu_settings` supaya kedua fitur (nyala/mati vs pengelompokan)
        // tetap bisa dibaca dan diaudit sendiri-sendiri.
        $groups           = static::getEssGroups()['groups'];
        $validGroupIds    = array_column($groups, 'id');
        $rawAssignments   = (array) $request->input('group_assignments', []);
        $newAssignments   = [];

        foreach (static::UNGROUPABLE_ITEMS as $ungroupable) {
            unset($rawAssignments[$ungroupable]);
        }

        foreach ($rawAssignments as $itemKey => $groupId) {
            if (array_key_exists($itemKey, static::ESS_ITEMS) && in_array($groupId, $validGroupIds, true)) {
                $newAssignments[$itemKey] = $groupId;
            }
        }

        AppConfig::setJson('ess_menu_groups', [
            'groups'      => $groups,
            'assignments' => $newAssignments,
        ], 'ESS sidebar menu groups (D182) — which flat ESS items are folded into which sidebar dropdown');

        if ($request->wantsJson()) {
            return response()->json([
                'success'  => true,
                'message'  => 'ESS Settings updated successfully.',
                'settings' => $newSettings,
            ]);
        }

        return redirect()->back()->with('success', 'ESS Settings updated successfully.');
    }

    /**
     * Retrieve array of ESS menu visibility settings [key => bool].
     */
    public static function getEssSettings(): array
    {
        $defaults = [];
        foreach (static::ESS_ITEMS as $key => $item) {
            $defaults[$key] = true;
        }

        $saved = AppConfig::getJson('ess_menu_settings', []);
        return array_merge($defaults, $saved);
    }

    /**
     * 🔴 D182 — Kelompok dropdown ESS yang dapat diatur admin, plus penempatan
     * tiap item ke dalamnya.
     *
     * Mengembalikan bentuk yang SUDAH DIBERSIHKAN (self-healing): penempatan
     * yang menunjuk grup yang sudah dihapus, atau item yang sudah tidak ada
     * di ESS_ITEMS (mis. berkas ini di-deploy versi lama), dibuang diam-diam
     * daripada membuat sidebar melempar galat. Sidebar tanpa grup apa pun yang
     * cocok tetap harus terlihat identik dengan sebelum fitur ini ada — itulah
     * kenapa `assignments` kosong (bawaan instalasi baru) berarti SELURUH item
     * tetap flat, persis seperti sebelum D182.
     *
     * @return array{groups: array<int,array{id:string,label:string,icon:string}>, assignments: array<string,string>}
     */
    public static function getEssGroups(): array
    {
        $data   = AppConfig::getJson('ess_menu_groups', []);
        $groups = is_array($data['groups'] ?? null) ? $data['groups'] : [];

        // Baris cacat (tanpa id/label) dibuang — tidak pernah ditulis fitur ini
        // sendiri, tapi berjaga-jaga bila JSON diedit manual dari luar.
        $groups = array_values(array_filter($groups, fn ($g) => !empty($g['id']) && !empty($g['label'])));

        $validGroupIds = array_column($groups, 'id');
        $validItemKeys = array_diff(array_keys(static::ESS_ITEMS), static::UNGROUPABLE_ITEMS);

        $rawAssignments = is_array($data['assignments'] ?? null) ? $data['assignments'] : [];
        $assignments    = [];

        foreach ($rawAssignments as $itemKey => $groupId) {
            if (in_array($itemKey, $validItemKeys, true) && in_array($groupId, $validGroupIds, true)) {
                $assignments[$itemKey] = $groupId;
            }
        }

        return ['groups' => $groups, 'assignments' => $assignments];
    }

    /** Buat grup dropdown ESS baru. Anggotanya kosong sampai di-assign lewat "Save Changes". */
    public function storeGroup(Request $request)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'icon'  => ['nullable', 'string', 'max:60'],
        ]);

        $stored = AppConfig::getJson('ess_menu_groups', ['groups' => [], 'assignments' => []]);
        $groups = is_array($stored['groups'] ?? null) ? $stored['groups'] : [];

        // 🔴 id STABIL, terpisah dari label — mengganti nama grup nanti TIDAK
        // BOLEH melepas anggotanya (pelajaran yang sama dengan KUNCI ESS_ITEMS
        // yang sengaja tidak pernah diubah meski nama tampilannya berubah).
        $groups[] = [
            'id'    => 'grp_' . Str::slug($data['label'], '_') . '_' . Str::lower(Str::random(4)),
            'label' => trim($data['label']),
            'icon'  => trim($data['icon'] ?? '') ?: 'fas fa-layer-group',
        ];

        AppConfig::setJson('ess_menu_groups', [
            'groups'      => $groups,
            'assignments' => $stored['assignments'] ?? [],
        ], 'ESS sidebar menu groups (D182)');

        return redirect()->route('management.ess-settings.index')->with('success', 'Group "' . $data['label'] . '" created. Assign items to it below, then Save Changes.');
    }

    /** Ganti nama/ikon grup. Anggotanya TIDAK berubah — id-nya tidak ikut diganti. */
    public function updateGroup(Request $request, string $group)
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:60'],
            'icon'  => ['nullable', 'string', 'max:60'],
        ]);

        $stored = AppConfig::getJson('ess_menu_groups', ['groups' => [], 'assignments' => []]);
        $groups = is_array($stored['groups'] ?? null) ? $stored['groups'] : [];
        $found  = false;

        foreach ($groups as &$g) {
            if (($g['id'] ?? null) === $group) {
                $g['label'] = trim($data['label']);
                $g['icon']  = trim($data['icon'] ?? '') ?: ($g['icon'] ?? 'fas fa-layer-group');
                $found      = true;
            }
        }
        unset($g);

        if (!$found) {
            return redirect()->route('management.ess-settings.index')->with('error', 'That group no longer exists.');
        }

        AppConfig::setJson('ess_menu_groups', [
            'groups'      => $groups,
            'assignments' => $stored['assignments'] ?? [],
        ], 'ESS sidebar menu groups (D182)');

        return redirect()->route('management.ess-settings.index')->with('success', 'Group updated.');
    }

    /**
     * Hapus grup. Anggotanya kembali FLAT (ungrouped), bukan ikut terhapus —
     * item-nya tetap ada di ESS_ITEMS dan tetap bisa dilihat lewat sakelar
     * visibility yang sudah ada; yang hilang hanya wadah dropdown-nya.
     */
    public function destroyGroup(string $group)
    {
        $stored = AppConfig::getJson('ess_menu_groups', ['groups' => [], 'assignments' => []]);
        $groups = is_array($stored['groups'] ?? null) ? $stored['groups'] : [];
        $assignments = is_array($stored['assignments'] ?? null) ? $stored['assignments'] : [];

        AppConfig::setJson('ess_menu_groups', [
            'groups'      => array_values(array_filter($groups, fn ($g) => ($g['id'] ?? null) !== $group)),
            'assignments' => array_filter($assignments, fn ($gid) => $gid !== $group),
        ], 'ESS sidebar menu groups (D182)');

        return redirect()->route('management.ess-settings.index')->with('success', 'Group deleted. Its items are now shown ungrouped.');
    }
}
