{{--
    Daftar tab hub Approval Workflow — lima tab, satu per modul yang punya
    alur persetujuan bertingkat (D180). Lihat hub-tabs-attendance.blade.php
    untuk penjelasan pola tab bar secara umum.

    🔴 SELURUH LIMA TAB 'strict' => true, dan itu SENGAJA, bukan kelalaian.
    Pola hub lain (Attendance/Overtime/dst.) membiarkan slug induk `general`
    meloloskan seluruh tab di dalamnya — masuk akal di sana karena tab-tabnya
    memang bagian dari payung fitur yang sama. Di sini BEDA: pemilik sistem
    eksplisit meminta slug BARU dengan "pengawasan ketat" dan "regrant manual"
    — kalau `general` tetap jadi jalan pintas, siapa pun yang sudah punya
    akses blanket otomatis melihat kelima tab ini tanpa pernah diberi izin
    SECARA SADAR, membatalkan seluruh maksud pemisahan hak yang diminta.
--}}
@php
    $hubTabs = [
        [
            'label'  => 'Overtime',
            'icon'   => 'clock',
            'route'  => 'general.approval-workflow.overtime',
            'is'     => 'general/approval-workflow/overtime',
            'gate'   => 'general.approval-workflow.overtime',
            'strict' => true,
        ],
        [
            'label'  => 'Reimbursement',
            'icon'   => 'receipt',
            'route'  => 'general.approval-workflow.reimbursement',
            'is'     => 'general/approval-workflow/reimbursement',
            'gate'   => 'general.approval-workflow.reimbursement',
            'strict' => true,
        ],
        [
            'label'  => 'Purchase Request',
            'icon'   => 'cart-shopping',
            'route'  => 'general.approval-workflow.purchase-request',
            'is'     => 'general/approval-workflow/purchase-request',
            'gate'   => 'general.approval-workflow.purchase-request',
            'strict' => true,
        ],
        [
            'label'  => 'Cash Advance',
            'icon'   => 'hand-holding-usd',
            'route'  => 'general.approval-workflow.cash-advance',
            'is'     => 'general/approval-workflow/cash-advance',
            'gate'   => 'management.approval-workflow.cash-advance',
            'strict' => true,
        ],
        [
            'label'  => 'Cash Advance Report',
            'icon'   => 'file-invoice-dollar',
            'route'  => 'general.approval-workflow.cash-advance-report',
            'is'     => 'general/approval-workflow/cash-advance-report',
            'gate'   => 'management.approval-workflow.cash-advance-report',
            'strict' => true,
        ],
    ];
@endphp
@include('partials.hub-tabs', ['hubTabs' => $hubTabs])
