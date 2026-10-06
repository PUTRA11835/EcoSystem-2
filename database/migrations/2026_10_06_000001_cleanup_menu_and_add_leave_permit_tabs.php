<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * (1) Buang menu mati `general.settings` ("HR Settings"): tak ada rute, view, maupun controller yang memakainya
 *     (audit 6 Okt 2026) — yang dipakai hanya anak-anaknya `general.settings.branches|shifts|attendance|...`,
 *     dan anak-anak itu BUKAN turunan baris ini di tabel menu (parent-nya `general`), jadi aman dihapus.
 *
 * (2) Halaman Leave & Permit punya 4 tab JavaScript tetapi hanya satu slug. Tambahkan satu slug per tab supaya
 *     role bisa diberi sebagian tab saja. Berbeda dari aturan baku MenuRegistrar (slug baru = admin saja), slug tab
 *     ini DIWARISKAN ke semua role yang sudah memegang `hr_general.leave_permit` — kalau tidak, HR yang sekarang
 *     bisa membuka halaman itu tiba-tiba kehilangan seluruh tab-nya saat migrasi ini dijalankan.
 *
 * (3) Dua halaman hidup yang gerbangnya memakai slug yang TIDAK ADA di tabel `menu` (audit 6 Okt 2026): rute, sidebar,
 *     dan MenuSeeder sudah memakainya, tetapi barisnya tak ada di DB ini — akibatnya tak bisa diberikan ke role mana
 *     pun dari Menu Access. Dibuat HANYA bila belum ada (grantToAdminOnly mencabut grant role lain, jadi tidak
 *     boleh dijalankan untuk slug yang sudah ada di produksi).
 */
return new class extends Migration
{
    private const PAGE = 'hr_general.leave_permit';

    private const TABS = [
        'hr_general.leave_permit.tab-inbox'  => 'Leave & Permit — Approval Inbox tab',
        'hr_general.leave_permit.tab-types'  => 'Leave & Permit — Master Leave Type tab',
        'hr_general.leave_permit.tab-quotas' => 'Leave & Permit — All Employee Quotas tab',
        'hr_general.leave_permit.tab-report' => 'Leave & Permit — Reports & Analytics tab',
    ];

    /** slug => [induk, nama, order_seq] — sama dengan MenuSeeder. */
    private const MISSING_PAGES = [
        'reporting.diagram-report'   => ['reporting', 'Diagram Report', 8],
        'control-center.audit-log'   => ['control-center', 'Audit Log', 8],
    ];

    public function up(): void
    {
        MenuRegistrar::remove(['general.settings']);

        foreach (self::MISSING_PAGES as $slug => [$parent, $name, $seq]) {
            if (!DB::table('menu')->where('slug', $slug)->exists()) {
                MenuRegistrar::register($parent, [$slug => $name], $seq, 'page');
            }
        }

        if (!MenuRegistrar::register(self::PAGE, self::TABS, 10, 'page')) {
            return;
        }

        $page = DB::table('menu')->where('slug', self::PAGE)->first();
        $tabIds = DB::table('menu')->whereIn('slug', array_keys(self::TABS))->pluck('id');
        $holders = DB::table('role_menu')->where('menu_id', $page->id)->get();
        $now = now();

        foreach ($tabIds as $tabId) {
            foreach ($holders as $h) {
                DB::table('role_menu')->updateOrInsert(
                    ['role_id' => $h->role_id, 'menu_id' => $tabId],
                    ['can_view' => 1, 'can_create' => 0, 'can_edit' => 0, 'can_delete' => 0, 'created_at' => $now, 'updated_at' => $now],
                );
            }
        }

        DB::table('employee_role_assignment')->whereIn('role_id', $holders->pluck('role_id'))->pluck('employee_id')->unique()
            ->each(function ($empId) {
                Cache::forget("perm_slugs_{$empId}");
                Cache::forget("perm_matrix_{$empId}");
            });
    }

    public function down(): void
    {
        MenuRegistrar::remove(array_keys(self::TABS));
        // Diagram Report / Audit Log sengaja tidak dihapus: halamannya hidup dan baris itu seharusnya memang ada.
        // `general.settings` sengaja tidak dibuat ulang: baris itu tak pernah berfungsi.
    }
};
