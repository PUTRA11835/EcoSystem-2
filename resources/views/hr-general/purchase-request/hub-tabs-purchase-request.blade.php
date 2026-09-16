{{--
    Daftar tab hub Purchase Request — dua tab (D177). Lihat hub-tabs-attendance.blade.php
    untuk penjelasan pola secara umum.
--}}
@php
    $hubTabs = [
        [
            'label' => 'Purchase Request Management',
            'icon'  => 'cart-shopping',
            'route' => 'general.purchase-request.index',
            'is'    => 'general/purchase-request',
            'gate'  => 'general.purchase-request',
            // 🔴 D179 — "menunggu SAYA", pola sama dengan Overtime di atas.
            'badge' => fn () => count(app(\App\Services\PurchaseRequest\PurchaseRequestService::class)
                ->pendingIdsFor((int) session('user.id'))),
        ],
        [
            'label' => 'Settings',
            'icon'  => 'sliders',
            'route' => 'general.purchase-request.settings.edit',
            'is'    => 'general/purchase-request/settings*',
            'gate'  => 'general.settings.purchase-request',
        ],
    ];
@endphp
@include('partials.hub-tabs', ['hubTabs' => $hubTabs])
