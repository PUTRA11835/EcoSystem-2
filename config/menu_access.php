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
        'employee.section.compensation.*',
        'finance.payroll', 'finance.payroll.*',
        'general.onboarding.lock', 'general.onboarding.unlock', 'general.onboarding.join-date',
        'general.contracts.salary',
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
        'general.contracts.list',
        'general.contracts.templates',
        'general.inventory.items',               // Create = add a stock line, Edit = edit / adjust stock, Delete = delete a line
        'general.inventory.assets',              // Create = add an asset, Edit = edit / assign, Delete = delete an asset
        'general.inventory.settings',            // Create = add a dropdown option, Edit = rename / re-order / switch off, Delete = delete an unused option
        'finance.payroll.periods',               // Create = new period / adjustment, Edit = calculate / remove adjustment, Delete = delete an open period
        'finance.payroll.settings',              // Edit = change calculation policy
        'finance.bpjs.letters',                  // Create = generate a BPJS letter, Delete = void a BPJS letter
        'finance.bpjs.settings',                 // Create = save a new BPJS setting version
        'finance.pph21.settings',                // Create = new tax year, Edit = edit PTKP / progressive / TER rates
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
        ['key' => 'contract',      'label' => 'Contract',             'tabs' => ['general.contracts.list', 'general.contracts.templates']],
        ['key' => 'inventory',     'label' => 'Inventory & Assets',   'tabs' => ['general.inventory.overview', 'general.inventory.items', 'general.inventory.assets', 'general.inventory.settings']],
        ['key' => 'payroll',       'label' => 'Payroll',              'tabs' => ['finance.payroll.periods', 'finance.payroll.settings', 'finance.payroll.simulation']],
        ['key' => 'bpjs',          'label' => 'BPJS',                 'tabs' => ['finance.bpjs.settings', 'finance.bpjs.report', 'finance.bpjs.letters']],
        ['key' => 'pph21',         'label' => 'PPh 21',               'tabs' => ['finance.pph21.settings', 'finance.pph21.report']],
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
        'general.contracts.list'              => ['c' => 'Create a contract', 'e' => 'Edit a contract / change its status', 'd' => 'Delete a Draft contract'],
        'finance.payroll.periods'             => ['c' => 'Create a period or an adjustment', 'e' => 'Calculate / recalculate, remove an adjustment', 'd' => 'Delete an open period'],
        'finance.payroll.settings'            => ['e' => 'Change the payroll calculation policy'],
        'finance.bpjs.letters'                => ['c' => 'Generate a BPJS letter', 'd' => 'Void a BPJS letter'],
        'finance.bpjs.settings'               => ['c' => 'Add a new BPJS setting version (earlier versions are never changed)'],
        'finance.pph21.settings'              => ['c' => 'Create a tax year (copy of another year)', 'e' => 'Edit PTKP, progressive and TER rates'],
        'general.inventory.items'             => ['c' => 'Add an inventory line', 'e' => 'Edit a line / adjust its stock', 'd' => 'Delete a line'],
        'general.inventory.assets'            => ['c' => 'Add an asset', 'e' => 'Edit an asset / assign it to an employee', 'd' => 'Delete an asset'],
        'general.inventory.settings'          => ['c' => 'Add a dropdown option', 'e' => 'Rename / re-order / switch an option on or off', 'd' => 'Delete an option nobody uses'],
        'general.contracts.templates'         => ['c' => 'Add a template', 'e' => 'Edit a template', 'd' => 'Delete a template (not a system default)'],
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
    // `group` (opsional) = modul dengan group yang sama dilipat jadi satu blok di kolom kiri halaman Menu Access;
    // `short` = nama pendeknya di dalam blok itu. `about` (opsional) = penjelasan satu kalimat, tampil di atas daftar baris modul itu.
    'modules' => [
        ['key' => 'ess',         'label' => 'My Workspace (ESS)',        'group' => 'My Workspace', 'short' => 'Self-service (ESS)', 'match' => ['ess', 'ess.*', 'general.my-*', 'my-leave-permit', 'profile.my', 'my-profile', 'my-profile.*', 'general.my-kpi*']],
        ['key' => 'hr-attendance', 'label' => 'HR · Attendance',         'group' => 'Human Resources', 'short' => 'Attendance', 'match' => ['general.attendance', 'general.attendance.*', 'general.settings.branches*', 'general.settings.shifts*', 'general.settings.attendance*']],
        ['key' => 'hr-overtime', 'label' => 'HR · Overtime',             'group' => 'Human Resources', 'short' => 'Overtime', 'match' => ['general.overtime', 'general.overtime.*', 'general.settings.overtime*']],
        ['key' => 'hr-reimb',    'label' => 'HR · Reimbursement',        'group' => 'Human Resources', 'short' => 'Reimbursement', 'match' => ['general.reimbursement', 'general.reimbursement.*', 'general.settings.reimbursement*']],
        ['key' => 'hr-pr',       'label' => 'HR · Purchase Request',     'group' => 'Human Resources', 'short' => 'Purchase Request', 'match' => ['general.purchase-request', 'general.purchase-request.*', 'general.settings.purchase-request*']],
        ['key' => 'hr-ca',       'label' => 'HR · Cash Advance',         'group' => 'Human Resources', 'short' => 'Cash Advance', 'match' => ['general.cash-advance', 'general.cash-advance.*', 'general.cash-advance-report', 'general.cash-advance-report.*', 'management.cash-advance-settings*', 'management.approval-workflow.cash-advance*']],
        ['key' => 'hr-recruit',  'label' => 'HR · Recruitment & Offering', 'group' => 'Human Resources', 'short' => 'Recruitment & Offering', 'match' => [
            'general.recruitment', 'general.recruitment.*', 'general.offering-letter', 'general.offering-letter.*',
        ]],
        ['key' => 'hr-contract', 'label' => 'HR · Contract', 'group' => 'Human Resources', 'short' => 'Contract', 'match' => ['general.contracts', 'general.contracts.*']],
        ['key' => 'ga-inventory', 'label' => 'General Affairs · Inventory & Assets', 'match' => ['general.inventory', 'general.inventory.*']],
        ['key' => 'finance',     'label' => 'Commercial & Finance',     'match' => ['finance', 'finance.*']],
        ['key' => 'hr-docs',  'label' => 'HR · Docs & Letters', 'group' => 'Human Resources', 'short' => 'Docs & Letters', 'match' => [
            'general.letter-templates', 'general.letter-templates.*', 'general.letters', 'general.letters.*',
        ]],
        ['key' => 'hr-kpi',      'label' => 'HR · KPI',                  'group' => 'Human Resources', 'short' => 'KPI', 'match' => ['general.kpi-evaluation', 'general.kpi-evaluation.*']],
        ['key' => 'hr-onboard',  'label' => 'HR · Onboarding & Command Center', 'group' => 'Human Resources', 'short' => 'Onboarding & Command Center', 'match' => ['general.onboarding', 'general.onboarding.*', 'general.command-center*']],
        ['key' => 'hr-approval', 'label' => 'HR · Approval Workflow',    'group' => 'Human Resources', 'short' => 'Approval Workflow', 'match' => ['general.approval-workflow', 'general.approval-workflow.*']],
        ['key' => 'hr-leave',    'label' => 'HR · Leave & Permit',       'group' => 'Human Resources', 'short' => 'Leave & Permit', 'match' => ['hr_general', 'hr_general.*']],
        ['key' => 'hr-other',    'label' => 'HR & General · root access', 'group' => 'Human Resources', 'short' => 'Root access (General)',
            'about' => 'Only the parent row "HR & General" (slug general): the root above every HR & General page. The sidebar and the older tab bars use it as a fallback; pages with their own tab permission ignore it.', 'match' => ['general', 'general.*']],
        ['key' => 'employee',    'label' => 'Master · Employee',         'group' => 'Master', 'short' => 'Employee', 'match' => ['employee', 'employee.*', 'master.employee*']],
        ['key' => 'customer',    'label' => 'Master · Customer',         'group' => 'Master', 'short' => 'Customer', 'match' => ['customer', 'customer.*', 'master', 'master.customer*']],
        ['key' => 'ticketing',   'label' => 'Ticketing',                 'group' => 'Work & Service', 'short' => 'Ticketing', 'match' => ['tickets', 'tickets.*', 'ticket', 'ticket.*', 'room-chat', 'room-chat.*', 'ui', 'ui.*', 'staging']],
        ['key' => 'delivery',    'label' => 'Delivery',                  'group' => 'Work & Service', 'short' => 'Delivery', 'match' => ['delivery', 'delivery.*', 'delivery-project.*', 'delivery-support.*']],
        ['key' => 'reporting',   'label' => 'Reporting',                 'match' => ['reporting', 'reporting.*']],
        ['key' => 'calendar',    'label' => 'Calendar & Timesheet',      'group' => 'My Workspace', 'short' => 'Calendar & Timesheet', 'match' => ['calendar', 'calendar.*', 'timesheet', 'timesheet.*']],
        ['key' => 'sla-rpmo',    'label' => 'SLA & RPMO',                'group' => 'Work & Service', 'short' => 'SLA & RPMO', 'match' => ['sla', 'sla.*', 'rpmo', 'rpmo.*']],
        ['key' => 'management',  'label' => 'Management',                'group' => 'Administration', 'short' => 'Management', 'match' => ['management', 'management.*']],
        ['key' => 'control',     'label' => 'Control Center',            'group' => 'Administration', 'short' => 'Control Center', 'match' => ['control-center', 'control-center.*']],
        ['key' => 'apps',        'label' => 'Aplikasi lain',             'match' => ['dashboard', 'ai-assistant', 'ai-research', 'financial', 'business', 'legal']],
    ],
    'other_module' => ['key' => 'other', 'label' => 'Lainnya (belum dikelompokkan)'],
];
