{{--
    Daftar tab hub Reimbursement — dua tab (D177). Lihat hub-tabs-attendance.blade.php
    untuk penjelasan pola secara umum.
--}}
@php
    $hubTabs = [
        [
            'label' => 'Reimbursement Management',
            'icon'  => 'receipt',
            'route' => 'general.reimbursement.index',
            'is'    => 'general/reimbursement',
            'gate'  => 'general.reimbursement',
        ],
        [
            'label' => 'Settings',
            'icon'  => 'sliders',
            'route' => 'general.reimbursement.settings.edit',
            'is'    => 'general/reimbursement/settings*',
            'gate'  => 'general.settings.reimbursement',
        ],
    ];
@endphp
@include('partials.hub-tabs', ['hubTabs' => $hubTabs])
