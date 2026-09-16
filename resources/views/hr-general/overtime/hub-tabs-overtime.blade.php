{{--
    Daftar tab hub Overtime — dua tab (D175). Lihat hub-tabs-attendance.blade.php
    untuk penjelasan pola secara umum.
--}}
@php
    $hubTabs = [
        [
            'label' => 'Overtime Management',
            'icon'  => 'clock',
            'route' => 'general.overtime.index',
            'is'    => 'general/overtime',
            'gate'  => 'general.overtime',
            // 🔴 D179 — "menunggu SAYA", bukan seluruh yang pending: dihitung
            // lewat langkah persetujuan dokumen (siapa gilirannya sekarang),
            // persis kartu "Waiting for You" di halaman ini sendiri.
            'badge' => fn () => count(app(\App\Services\Overtime\OvertimeService::class)
                ->pendingIdsFor((int) session('user.id'))),
        ],
        [
            'label' => 'Settings',
            'icon'  => 'business-time',
            'route' => 'general.overtime.settings.edit',
            'is'    => 'general/overtime/settings*',
            'gate'  => 'general.settings.overtime',
        ],
    ];
@endphp
@include('partials.hub-tabs', ['hubTabs' => $hubTabs])
