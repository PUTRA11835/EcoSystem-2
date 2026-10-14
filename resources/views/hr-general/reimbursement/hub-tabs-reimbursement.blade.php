{{--
    Daftar tab hub Reimbursement — dua tab (D177). Lihat hub-tabs-attendance.blade.php
    untuk penjelasan pola secara umum.
--}}
@php
    $hubTabs = [
        [
            'label' => 'Dashboard',
            'icon'  => 'chart-pie',
            'route' => 'general.reimbursement.dashboard',
            'is'    => 'general/reimbursement/dashboard',
            'gate'  => 'general.reimbursement',
        ],
        [
            'label' => 'Reimbursement Management',
            'icon'  => 'receipt',
            'route' => 'general.reimbursement.index',
            'is'    => 'general/reimbursement',
            'gate'  => 'general.reimbursement',
            // 🔴 D179 — "menunggu SAYA", pola sama dengan Overtime di atas.
            'badge' => fn () => count(app(\App\Services\Reimbursement\ReimbursementService::class)
                ->pendingIdsFor((int) session('user.id'))),
        ],
        [
            'label' => 'Settings',
            'icon'  => 'sliders',
            'route' => 'general.reimbursement.settings.edit',
            'is'    => 'general/reimbursement/settings*',
            'gate'  => 'general.settings.reimbursement',
            // Approval Workflow adalah bagian tab ini, dengan slug sendiri (D180).
            'also'  => 'general.approval-workflow.reimbursement',
        ],
    ];
@endphp
@include('partials.hub-tabs', ['hubTabs' => $hubTabs])
