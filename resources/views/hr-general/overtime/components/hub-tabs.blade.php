{{--
    Daftar tab hub Overtime — dua tab (D175). Lihat components/hub-tabs.blade.php
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
            // Karyawan yang DITUNJUK sebagai penyetuju pada alur (peran atau nama)
            // otomatis dapat membuka halaman ini tanpa slug menu terpisah.
            'when'  => fn () => app(\App\Services\Overtime\OvertimeService::class)
                ->isDesignatedApprover((int) session('user.id')),
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
            'also'  => 'general.approval-workflow.overtime',
        ],
    ];
@endphp
@include('partials.hub-tabs', ['hubTabs' => $hubTabs])
