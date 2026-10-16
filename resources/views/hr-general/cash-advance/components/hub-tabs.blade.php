{{--
    Daftar tab hub Cash Advance Management (sisi Finance) — Dashboard, CA, CAR, Settings (D177, direvisi: CAR kini tab). Lihat components/hub-tabs.blade.php
    untuk penjelasan pola secara umum.

    🔴 Tab "Settings" menunjuk ke rute `management.cash-advance-settings.edit`,
    BUKAN `general.cash-advance.settings.edit` — URL dan slug halaman itu
    sengaja TETAP di luar `general.*` (Keputusan D141/D177). Hanya tampilannya
    yang digabung lewat tab bar ini.
--}}
@php
    $hubTabs = [
        [
            'label'  => 'Dashboard',
            'icon'   => 'chart-pie',
            'route'  => 'general.cash-advance.dashboard',
            'is'     => 'general/cash-advance/dashboard',
            'gate'   => 'general.cash-advance-dashboard',
            // Own slug (Menu Access → Cash Advance Management → Dashboard); `general` alone does not open it.
            'strict' => true,
        ],
        [
            'label' => 'Cash Advance (CA)',
            'icon'  => 'hand-holding-usd',
            'route' => 'general.cash-advance.index',
            'is'    => 'general/cash-advance',
            'gate'  => 'general.cash-advance',
            // 🔴 D179 — "menunggu SAYA", pola sama dengan Overtime di atas.
            'badge' => fn () => count(app(\App\Services\CashAdvance\CashAdvanceService::class)
                ->pendingIdsFor((int) session('user.id'))),
        ],
        [
            'label' => 'Cash Advance Report (CAR)',
            'icon'  => 'file-invoice-dollar',
            'route' => 'general.cash-advance-report.index',
            'is'    => 'general/cash-advance-report',
            'gate'  => 'general.cash-advance-report',
            'badge' => fn () => count(app(\App\Services\CashAdvance\CashAdvanceReportService::class)
                ->pendingIdsFor((int) session('user.id'))),
        ],
        [
            'label'  => 'Settings',
            'icon'   => 'sliders',
            'route'  => 'management.cash-advance-settings.edit',
            'is'     => 'management/cash-advance-settings*',
            'gate'   => 'management.cash-advance-settings',
            // 🔴 'strict' — lihat hub-tabs.blade.php. Slug ini sengaja di luar
            // payung `general.*` (D141); seorang holder `general` polos TANPA
            // slug ini TIDAK boleh melihat tab ini sama sekali.
            'strict' => true,
            'also'   => ['management.approval-workflow.cash-advance', 'management.approval-workflow.cash-advance-report'],
        ],
    ];
@endphp
@include('partials.hub-tabs', ['hubTabs' => $hubTabs])
