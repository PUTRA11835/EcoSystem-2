{{--
    Daftar tab hub Purchase Request — dua tab (D177). Lihat components/hub-tabs.blade.php
    untuk penjelasan pola secara umum.
--}}
@php
    $hubTabs = [
        [
            'label' => 'Purchase Requests',
            'icon'  => 'cart-shopping',
            'route' => 'general.purchase-request.index',
            'is'    => 'general/purchase-request',
            'gate'  => 'general.purchase-request',
            // 🔴 D179 — "menunggu SAYA", pola sama dengan Overtime di atas.
            'badge' => fn () => count(app(\App\Services\PurchaseRequest\PurchaseRequestService::class)
                ->pendingIdsFor((int) session('user.id'))),
        ],
        [
            'label' => 'Purchase Orders',
            'icon'  => 'file-invoice',
            'route' => 'general.purchase-order.index',
            'is'    => 'general/purchase-order*',
            'gate'  => 'general.purchase-order',
        ],
        [
            'label' => 'Settings',
            'icon'  => 'sliders',
            'route' => 'general.purchase-request.settings.edit',
            'is'    => 'general/purchase-request/settings*',
            'gate'  => 'general.settings.purchase-request',
            'also'  => 'general.approval-workflow.purchase-request',
        ],
    ];
@endphp
@include('partials.hub-tabs', ['hubTabs' => $hubTabs])
