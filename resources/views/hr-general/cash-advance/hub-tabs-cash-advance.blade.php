{{--
    Daftar tab hub Cash Advance (CA) — dua tab (D177). Lihat hub-tabs-attendance.blade.php
    untuk penjelasan pola secara umum.

    🔴 Tab "Settings" menunjuk ke rute `management.cash-advance-settings.edit`,
    BUKAN `general.cash-advance.settings.edit` — URL dan slug halaman itu
    sengaja TETAP di luar `general.*` (Keputusan D141/D177). Hanya tampilannya
    yang digabung lewat tab bar ini.
--}}
@php
    $hubTabs = [
        [
            'label' => 'Cash Advance (CA)',
            'icon'  => 'hand-holding-usd',
            'route' => 'general.cash-advance.index',
            'is'    => 'general/cash-advance',
            'gate'  => 'general.cash-advance',
            // 🔴 D179 — "menunggu SAYA", pola sama dengan Overtime di atas.
            // Cash Advance Report (CAR) TIDAK ikut di sini — CAR sengaja
            // tetap terpisah dari hub ini (D177); badge-nya sendiri
            // ditaruh langsung di baris sidebar CAR, bukan di sebuah tab.
            'badge' => fn () => count(app(\App\Services\CashAdvance\CashAdvanceService::class)
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
        ],
    ];
@endphp
@include('partials.hub-tabs', ['hubTabs' => $hubTabs])
