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
        'employee.section.engagement_rate.*', 'employee.section.hr_profile.*', 'employee.section.salary.*',
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
        'hr_general.leave_permit',              // Create = Log Leave / Permit, Edit = edit a logged application
        'hr_general.leave_permit.tab-inbox',    // Edit = approve / reject / ask for revision
        'hr_general.leave_permit.tab-types',    // Create = add a leave type, Edit = edit / (de)activate a type
        'general.my-letter-requests', // Global ESS: baris terkunci di layar, tetapi rutenya memakai menu.can
        // Tab Master Employee yang berisi daftar rekaman: Create/Delete = flag pada baris `.view`-nya (middleware
        // employee.section:<tab>,create|delete), Edit = slug `.update` seperti sebelumnya.
        'employee.section.address.view',
        'employee.section.identification.view',
        'employee.section.family.view',
        'employee.section.education.view',
        'employee.section.qualification.view',
        'employee.section.contract.view',
        'employee.section.bank.view',
        'employee.section.payment.view',
        'employee.section.attachment.view',
        'employee.section.salary.view',
    ],

    /*
     * hubs — halaman yang isinya TAB. Tiap slug di sini adalah satu tab dengan izin sendiri (satu role bisa diberi
     * sebagian tab saja). Urutan = urutan tab di layar. Hanya memengaruhi pengelompokan di halaman Menu Access.
     */
    'hubs' => [
        ['key' => 'attendance',     'label' => 'Attendance',          'tabs' => ['general.attendance', 'general.attendance.monthly', 'general.attendance.correction', 'general.settings.branches', 'general.settings.shifts', 'general.settings.attendance']],
        ['key' => 'overtime',       'label' => 'Overtime',            'tabs' => ['general.overtime', 'general.settings.overtime']],
        ['key' => 'reimbursement',  'label' => 'Reimbursement',       'tabs' => ['general.reimbursement', 'general.settings.reimbursement']],
        ['key' => 'purchase-req',   'label' => 'Purchase Request',    'tabs' => ['general.purchase-request', 'general.settings.purchase-request']],
        ['key' => 'cash-advance',   'label' => 'Cash Advance',        'tabs' => ['general.cash-advance', 'management.cash-advance-settings']],
        ['key' => 'approval-wf',    'label' => 'Approval Workflow',   'tabs' => ['general.approval-workflow.overtime', 'general.approval-workflow.reimbursement', 'general.approval-workflow.purchase-request', 'management.approval-workflow.cash-advance', 'management.approval-workflow.cash-advance-report']],
        ['key' => 'recruitment',    'label' => 'Recruitment',         'tabs' => ['general.recruitment', 'general.recruitment.candidates', 'general.recruitment.schedule', 'general.recruitment.jobs', 'general.recruitment.settings']],
        ['key' => 'offering',       'label' => 'Offering Letter',     'tabs' => ['general.recruitment.offers', 'general.recruitment.offers.settings']],
        ['key' => 'letters',        'label' => 'Docs & Letters',      'tabs' => ['general.letters.dashboard', 'general.letters.requests', 'general.letters.register', 'general.letters.compose', 'general.letter-templates']],
        ['key' => 'kpi-eval',       'label' => 'KPI Evaluation',      'tabs' => ['general.kpi-evaluation', 'general.kpi-evaluation.templates', 'general.kpi-evaluation.teams']],
        ['key' => 'my-kpi',         'label' => 'My KPI',              'tabs' => ['general.my-kpi', 'general.my-kpi.tab-self', 'general.my-kpi.tab-lead', 'general.my-kpi.tab-peer', 'general.my-kpi.tab-upward']],
        ['key' => 'leave-permit',   'label' => 'Leave & Permit',      'tabs' => ['hr_general.leave_permit', 'hr_general.leave_permit.tab-inbox', 'hr_general.leave_permit.tab-types', 'hr_general.leave_permit.tab-quotas', 'hr_general.leave_permit.tab-report']],
    ],

    // Section/tab pada halaman detail (slug `<prefix>.<seksi>.view|update|...`) dikelompokkan menurut awalan.
    'section_hubs' => [
        'employee.section.'         => 'Employee detail',
        'customer.section.'         => 'Business Partner detail',
        'my-profile.section.'       => 'My Profile',
        'delivery-project.'         => 'Project detail',
        'delivery-support.'         => 'Support detail',
    ],

    /*
     * crud_labels — slug yang hanya memakai SEBAGIAN kotak C/E/D: tampilkan hanya kotak yang berfungsi, dengan arti
     * yang sebenarnya sebagai tooltip (kotak lain tidak dirender, jadi tak ada yang mengira "Delete" berfungsi).
     */
    'crud_labels' => [
        'hr_general.leave_permit'             => ['c' => 'Log Leave / Permit (on behalf of an employee, all tabs)', 'e' => 'Edit a logged application (HR override)'],
        'hr_general.leave_permit.tab-inbox'   => ['e' => 'Approve / Reject / Ask for revision'],
        'hr_general.leave_permit.tab-types'   => ['c' => 'Add a leave type', 'e' => 'Edit or (de)activate a leave type'],
        'employee.section.address.view' => ['c' => 'Add a record to this tab', 'd' => 'Delete a record from this tab'],
        'employee.section.identification.view' => ['c' => 'Add a record to this tab', 'd' => 'Delete a record from this tab'],
        'employee.section.family.view' => ['c' => 'Add a record to this tab', 'd' => 'Delete a record from this tab'],
        'employee.section.education.view' => ['c' => 'Add a record to this tab', 'd' => 'Delete a record from this tab'],
        'employee.section.qualification.view' => ['c' => 'Add a record to this tab', 'd' => 'Delete a record from this tab'],
        'employee.section.contract.view' => ['c' => 'Add a record to this tab', 'd' => 'Delete a record from this tab'],
        'employee.section.bank.view' => ['c' => 'Add a record to this tab', 'd' => 'Delete a record from this tab'],
        'employee.section.payment.view' => ['c' => 'Add a record to this tab', 'd' => 'Delete a record from this tab'],
        'employee.section.attachment.view' => ['c' => 'Add a record to this tab', 'd' => 'Delete a record from this tab'],
        'employee.section.salary.view' => ['c' => 'Add a record to this tab', 'd' => 'Delete a record from this tab'],
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
        ['key' => 'hr-other',    'label' => 'HR & General (umbrella)',   'match' => ['general', 'general.*']],
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
