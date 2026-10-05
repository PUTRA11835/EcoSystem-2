<?php

/**
 * ============================================================================
 * MENU ACCESS — pengelompokan & klasifikasi untuk halaman Management › Role › Menu Access
 * ============================================================================
 *
 * Berkas ini HANYA memengaruhi TAMPILAN/validasi halaman Menu Access. Ia tidak mengubah siapa yang boleh
 * mengakses apa (itu tetap isi tabel `role_menu` dan middleware `menu:*`).
 *
 *  - modules       peta "modul" → pola slug (Str::is; urutan berarti: pola pertama yang cocok menang). Menu yang tak
 *                  cocok pola mana pun otomatis masuk modul "other" — menu baru TIDAK PERNAH tersembunyi.
 *  - global_ess    slug yang SELALU diizinkan backend untuk semua karyawan aktif (Employee::canAccessMenu), jadi
 *                  mencentang/mencabutnya di role tidak berefek. Ditampilkan terkunci sebagai "Global ESS".
 *  - sensitive     pola slug yang ditandai "Sensitif": ikon perisai + konfirmasi tambahan saat diberikan.
 *  - crud_enforced slug yang BENAR-BENAR menegakkan Create/Edit/Delete di server (middleware `menu.can:slug,aksi`)
 *                  atau di controller. Kolom C/E/D hanya ditampilkan untuk slug ini; menu lain cukup satu kotak "Access"
 *                  (View) karena C/E/D di sana hanya tersimpan, tidak berefek (audit 5 Okt 2026, HC-D63).
 *                  Daftar ini DIJAGA uji otomatis: tes memindai rute `menu.can:` dan gagal bila ada slug yang kurang.
 *  - protected     slug yang tak boleh dicabut dari role EC Administrator (mencegah terkunci dari halaman ini).
 */
return [

    'protected_role_id' => 1, // EC Administrator (App\Enums\RoleId::EC_ADMINISTRATOR)

    'protected' => ['management.roles', 'management.permissions'],

    'global_ess' => [
        'general.my-attendance',
        'my-leave-permit',
        'general.my-overtime',
        'general.my-reimbursement',
        'general.my-purchase-request',
        'general.my-kpi',
        'profile.my',
        'general.my-letter-requests',
    ],
    // awalan yang juga dianggap global oleh layar Menu Access lama (ess.my_leave_permit dst.)
    'global_ess_patterns' => ['ess', 'ess.*'],

    'sensitive' => [
        'management', 'management.*',
        'control-center', 'control-center.*',
        'general.approval-workflow', 'general.approval-workflow.*',
        'master.employee.action',
        'employee.section.contract.*', 'employee.section.payment.*', 'employee.section.bank.*',
        'employee.section.engagement_rate.*', 'employee.section.hr_profile.*',
        'general.onboarding.lock', 'general.onboarding.unlock', 'general.onboarding.join-date',
    ],

    'crud_enforced' => [
        'general.recruitment',
        'general.recruitment.settings',
        'general.recruitment.candidates',
        'general.recruitment.schedule',
        'general.recruitment.jobs',
        'general.recruitment.offers',
        'general.recruitment.offers.settings',
        'general.letter-templates',
        'general.letters.requests',
        'general.letters.register',
        'general.letters.compose',
    ],

    // Urutan tampil di kolom kiri. `match` = pola slug (Str::is). Pola pertama yang cocok menang.
    'modules' => [
        ['key' => 'ess',         'label' => 'My Workspace (ESS)',        'match' => ['ess', 'ess.*', 'general.my-*', 'my-leave-permit', 'profile.my', 'my-profile', 'my-profile.*', 'general.my-kpi*']],
        ['key' => 'hr-attendance', 'label' => 'HR · Attendance',         'match' => ['general.attendance', 'general.attendance.*', 'general.settings.branches*', 'general.settings.shifts*', 'general.settings.attendance*']],
        ['key' => 'hr-overtime', 'label' => 'HR · Overtime',             'match' => ['general.overtime', 'general.overtime.*', 'general.settings.overtime*']],
        ['key' => 'hr-reimb',    'label' => 'HR · Reimbursement',        'match' => ['general.reimbursement', 'general.reimbursement.*', 'general.settings.reimbursement*']],
        ['key' => 'hr-pr',       'label' => 'HR · Purchase Request',     'match' => ['general.purchase-request', 'general.purchase-request.*', 'general.settings.purchase-request*']],
        ['key' => 'hr-ca',       'label' => 'HR · Cash Advance',         'match' => ['general.cash-advance', 'general.cash-advance.*', 'general.cash-advance-report', 'general.cash-advance-report.*', 'management.cash-advance-settings*', 'management.approval-workflow.cash-advance*']],
        ['key' => 'hr-recruit',  'label' => 'HR · Recruitment & Offering', 'match' => [
            'general.recruitment', 'general.recruitment.*', 'general.offering-letter', 'general.offering-letter.*',
        ]],
        ['key' => 'hr-docs',  'label' => 'HR · Docs & Letters', 'match' => [
            'general.letter-templates', 'general.letter-templates.*', 'general.letters', 'general.letters.*',
        ]],
        ['key' => 'hr-kpi',      'label' => 'HR · KPI',                  'match' => ['general.kpi-evaluation', 'general.kpi-evaluation.*']],
        ['key' => 'hr-onboard',  'label' => 'HR · Onboarding & Command Center', 'match' => ['general.onboarding', 'general.onboarding.*', 'general.command-center*']],
        ['key' => 'hr-approval', 'label' => 'HR · Approval Workflow',    'match' => ['general.approval-workflow', 'general.approval-workflow.*']],
        ['key' => 'hr-leave',    'label' => 'HR · Leave & Permit',       'match' => ['hr_general', 'hr_general.*']],
        ['key' => 'hr-other',    'label' => 'HR & General (lainnya)',    'match' => ['general', 'general.*']],
        ['key' => 'employee',    'label' => 'Master · Employee',         'match' => ['employee', 'employee.*', 'master.employee*']],
        ['key' => 'customer',    'label' => 'Master · Customer',         'match' => ['customer', 'customer.*', 'master', 'master.customer*']],
        ['key' => 'ticketing',   'label' => 'Ticketing',                 'match' => ['tickets', 'tickets.*', 'ticket', 'ticket.*', 'room-chat', 'room-chat.*', 'ui', 'ui.*', 'staging']],
        ['key' => 'delivery',    'label' => 'Delivery',                  'match' => ['delivery', 'delivery.*', 'delivery-project.*', 'delivery-support.*']],
        ['key' => 'reporting',   'label' => 'Reporting',                 'match' => ['reporting', 'reporting.*']],
        ['key' => 'calendar',    'label' => 'Calendar & Timesheet',      'match' => ['calendar', 'calendar.*', 'timesheet', 'timesheet.*']],
        ['key' => 'sla-rpmo',    'label' => 'SLA & RPMO',                'match' => ['sla', 'sla.*', 'rpmo', 'rpmo.*']],
        ['key' => 'management',  'label' => 'Management',                'match' => ['management', 'management.*']],
        ['key' => 'control',     'label' => 'Control Center',            'match' => ['control-center', 'control-center.*']],
        ['key' => 'apps',        'label' => 'Aplikasi lain',             'match' => ['dashboard', 'ai-assistant', 'ai-research', 'financial', 'business', 'legal']],
    ],
    'other_module' => ['key' => 'other', 'label' => 'Lainnya (belum dikelompokkan)'],
];
