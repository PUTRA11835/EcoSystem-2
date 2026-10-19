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
        'external.section.bank.*', 'external.section.hr_profile.*', 'external.section.engagement_rate.*',
        'finance.payroll', 'finance.payroll.*',
        'general.onboarding.lock', 'general.onboarding.unlock', 'general.onboarding.join-date',
        'general.contracts.salary',
        'general.external.timesheet.amount',   // rates, gross amounts and invoice amounts of external timesheets
        'general.offboarding.settlement',       // final settlement amounts (severance, compensation, tax, net)
        'general.contracts.signing',   // send / forward / release a contract for signature — Legal and RPMO
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
        'general.contracts.settings',            // Create = add a position type, Edit = rename / switch an option, Delete = delete an unused position type
        'general.external.contracts',            // Create = add a contract, Edit = edit / change status, Delete = delete a Draft
        'general.external.timesheet',            // Create = fill a timesheet on behalf of a consultant, Edit = change one / delete a Draft
        'general.offboarding.list',              // Create = start an offboarding, Edit = checklist / settlement / deactivate / complete, Delete = cancel
        'general.offboarding.settings',          // Create = add a task, Edit = switches and tasks, Delete = delete a task
        'finance.legal-basis',                   // Create = add a rule or version, Edit = change / mark reviewed, Delete = supersede
        'general.external.timesheet.settings',   // approvers of the timesheet and invoice steps; per-consultant exceptions
        'general.external.contracts.settings',   // the same boxes, for the external contract forms
        'general.external.assignment-letters',   // Create = generate an assignment letter
        'general.external.employment-certificates', // Create = generate an employment certificate
        'general.external.reference-letters',    // Create = generate a reference letter
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
        'calendar.events',                       // Create / Edit / Delete = make / change / remove COMPANY-WIDE events (anyone keeps personal ones) — enforced in CalendarController
        'management.holidays',                   // Create / Edit / Delete = add / change / remove a holiday
        // Tab Master Employee yang berisi daftar rekaman: Create/Delete = flag pada baris `.view`-nya (middleware
        // employee.section:<tab>,create|delete), Edit = slug `.update` seperti sebelumnya.
        'general.external.consultants',          // Create = add a consultant
        'external.section.address.view', 'external.section.identification.view', 'external.section.education.view',
        'external.section.qualification.view', 'external.section.bank.view', 'external.section.attachment.view',
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
        ['key' => 'attendance',     'label' => 'Attendance',          'tabs' => ['general.attendance', 'general.attendance.monthly', 'general.attendance.correction', 'general.settings.shifts', 'general.settings.attendance']],
        ['key' => 'overtime',       'label' => 'Overtime Management', 'tabs' => ['general.overtime', 'general.settings.overtime', 'general.approval-workflow.overtime']],
        ['key' => 'reimbursement',  'label' => 'Reimbursement Management', 'tabs' => ['general.reimbursement', 'general.settings.reimbursement', 'general.approval-workflow.reimbursement']],
        ['key' => 'purchase-req',   'label' => 'Purchase Management', 'tabs' => ['general.purchase-request', 'general.purchase-order', 'general.settings.purchase-request', 'general.approval-workflow.purchase-request']],
        ['key' => 'cash-advance',   'label' => 'Cash Advance Management', 'tabs' => ['general.cash-advance-dashboard', 'general.cash-advance', 'general.cash-advance-report', 'management.cash-advance-settings', 'management.approval-workflow.cash-advance', 'management.approval-workflow.cash-advance-report']],
        ['key' => 'my-cash-advance', 'label' => 'My Cash Advance',    'tabs' => ['general.my-cash-advance', 'general.my-cash-advance-report']],
        ['key' => 'recruitment',    'label' => 'Recruitment',         'tabs' => ['general.recruitment', 'general.recruitment.candidates', 'general.recruitment.schedule', 'general.recruitment.jobs', 'general.recruitment.settings']],
        ['key' => 'offering',       'label' => 'Offering Letter',     'tabs' => ['general.recruitment.offers', 'general.recruitment.offers.settings']],
        ['key' => 'letters',        'label' => 'Docs & Letters',      'tabs' => ['general.letters.dashboard', 'general.letters.requests', 'general.letters.register', 'general.letters.compose', 'general.letter-templates']],
        ['key' => 'contract',      'label' => 'Contract',             'tabs' => ['general.contracts.list', 'general.contracts.templates', 'general.contracts.settings']],
        ['key' => 'external',      'label' => 'External',             'tabs' => ['general.external.consultants', 'general.external.timesheet', 'general.external.timesheet.invoices', 'general.external.timesheet.settings', 'general.external.my-timesheet', 'general.external.contracts', 'general.external.contracts.settings', 'general.external.assignment-letters', 'general.external.employment-certificates', 'general.external.reference-letters']],
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
        'external.section.'         => 'External consultant detail',
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
        'general.contracts.settings'          => ['c' => 'Add a position type', 'e' => 'Rename / switch a contract type or position type on or off', 'd' => 'Delete a position type nobody uses'],
        'general.external.contracts.settings' => ['c' => 'Add a position type', 'e' => 'Rename / switch a contract type or position type on or off', 'd' => 'Delete a position type nobody uses'],
        'general.offboarding.list'            => ['c' => 'Start an offboarding', 'e' => 'Save the checklist, calculate the settlement, deactivate access, complete', 'd' => 'Cancel an offboarding'],
        'general.offboarding.settings'        => ['c' => 'Add a checklist task', 'e' => 'Change the switches and tasks', 'd' => 'Delete a checklist task'],
        'finance.legal-basis'                 => ['c' => 'Add a rule or a new version', 'e' => 'Edit an entry / mark it reviewed', 'd' => 'Mark a version superseded'],
        'general.external.timesheet'          => ['c' => 'Fill a timesheet on behalf of a consultant', 'e' => 'Change a timesheet on behalf of a consultant / delete a Draft'],
        'general.external.timesheet.settings' => ['c' => 'Add an approval step or a per-consultant exception', 'e' => 'Change a step, an exception or the options', 'd' => 'Delete a step or an exception'],
        'general.external.contracts'          => ['c' => 'Create an external contract', 'e' => 'Edit an external contract / change its status', 'd' => 'Delete a Draft external contract'],
        'general.external.assignment-letters' => ['c' => 'Generate an assignment letter'],
        'general.external.employment-certificates' => ['c' => 'Generate an employment certificate'],
        'general.external.reference-letters'  => ['c' => 'Generate a reference letter'],
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
        'general.external.consultants' => ['c' => 'Add a consultant'],
        'calendar.events'                     => ['c' => 'Create a company-wide event', 'e' => 'Edit a company-wide event', 'd' => 'Delete a company-wide event'],
        'management.holidays'                 => ['c' => 'Add a holiday', 'e' => 'Edit a holiday', 'd' => 'Delete a holiday'],
        'external.section.address.view' => ['c' => 'Add a record to this tab', 'd' => 'Delete a record from this tab'],
        'external.section.identification.view' => ['c' => 'Add a record to this tab', 'd' => 'Delete a record from this tab'],
        'external.section.education.view' => ['c' => 'Add a record to this tab', 'd' => 'Delete a record from this tab'],
        'external.section.qualification.view' => ['c' => 'Add a record to this tab', 'd' => 'Delete a record from this tab'],
        'external.section.bank.view' => ['c' => 'Add a record to this tab', 'd' => 'Delete a record from this tab'],
        'external.section.attachment.view' => ['c' => 'Add a record to this tab', 'd' => 'Delete a record from this tab'],
    ],

    /*
     * hidden — menu yang TIDAK ditampilkan di halaman Menu Access karena tak punya halaman yang bisa dibuka:
     * baris induk `general` (payung; tak ada layar "HR & General") serta stub "Coming soon" Financial, Business Dev
     * dan Legal. Barisnya tetap ada di tabel `menu` dan hibah yang sudah tersimpan tidak disentuh; hapus slug dari
     * daftar ini begitu halamannya sungguhan dibangun.
     */
    'hidden' => ['general', 'financial', 'financial.*', 'business', 'business.*', 'legal', 'legal.*'],

    // Urutan tampil di kolom kiri, dan NAMA GRUP sama dengan judul seksi di sidebar (partials/sidebar.blade.php).
    // `match` = pola slug (Str::is). Pola pertama yang cocok menang.
    // `group` (opsional) = modul dengan group yang sama dilipat jadi satu blok di kolom kiri halaman Menu Access;
    // `short` = nama pendeknya di dalam blok itu. `about` (opsional) = penjelasan satu kalimat, tampil di atas daftar baris modul itu.
    'modules' => [
        // My Workspace
        ['key' => 'ess',         'label' => 'My Workspace (ESS)',        'group' => 'My Workspace', 'short' => 'Self-service (ESS)', 'match' => ['ess', 'ess.*', 'general.my-*', 'my-leave-permit', 'profile.my', 'my-profile', 'my-profile.*', 'general.my-kpi*']],
        ['key' => 'calendar',    'label' => 'Calendar & Timesheet',      'group' => 'My Workspace', 'short' => 'Calendar & Timesheet', 'match' => ['calendar', 'calendar.*', 'timesheet', 'timesheet.*']],
        // Human Capital
        ['key' => 'hr-attendance', 'label' => 'HR · Attendance',         'group' => 'Human Capital', 'short' => 'Attendance', 'match' => ['general.attendance', 'general.attendance.*', 'general.settings.shifts*', 'general.settings.attendance*']],
        ['key' => 'hr-overtime', 'label' => 'HR · Overtime',             'group' => 'Human Capital', 'short' => 'Overtime', 'match' => ['general.overtime', 'general.overtime.*', 'general.settings.overtime*', 'general.approval-workflow.overtime']],
        ['key' => 'hr-recruit',  'label' => 'HR · Recruitment & Offering', 'group' => 'Human Capital', 'short' => 'Recruitment & Offering', 'match' => [
            'general.recruitment', 'general.recruitment.*', 'general.offering-letter', 'general.offering-letter.*',
        ]],
        ['key' => 'hr-contract', 'label' => 'HR · Contract',             'group' => 'Human Capital', 'short' => 'Contract', 'match' => ['general.contracts', 'general.contracts.*']],
        ['key' => 'hr-docs',     'label' => 'HR · Docs & Letters',       'group' => 'Human Capital', 'short' => 'Docs & Letters', 'match' => [
            'general.letter-templates', 'general.letter-templates.*', 'general.letters', 'general.letters.*',
        ]],
        ['key' => 'hr-kpi',      'label' => 'HR · KPI',                  'group' => 'Human Capital', 'short' => 'KPI', 'match' => ['general.kpi-evaluation', 'general.kpi-evaluation.*']],
        ['key' => 'hr-onboard',  'label' => 'HR · Onboarding & Command Center', 'group' => 'Human Capital', 'short' => 'Onboarding & Command Center', 'match' => ['general.onboarding', 'general.onboarding.*', 'general.command-center*']],
        ['key' => 'hr-leave',    'label' => 'HR · Leave & Permit',       'group' => 'Human Capital', 'short' => 'Leave & Permit', 'match' => ['hr_general', 'hr_general.*']],
        // General Affairs — directly under Human Capital
        ['key' => 'hr-offboarding', 'label' => 'HR · Offboarding', 'group' => 'Human Resources', 'short' => 'Offboarding', 'match' => ['general.offboarding', 'general.offboarding.*']],
        ['key' => 'ga-inventory', 'label' => 'General Affairs · Inventory & Assets', 'group' => 'General Affairs', 'short' => 'Inventory & Assets', 'match' => ['general.inventory', 'general.inventory.*']],
        // External — one entry per sidebar sub menu
        ['key' => 'ext-consultants', 'label' => 'External · Consultants', 'group' => 'External', 'short' => 'External Consultants',
            'about' => "The consultant list and every tab of a consultant's detail page (Basic Data, Address, Bank, ...).",
            'match' => ['general.external', 'general.external.consultants', 'general.external.consultants.*', 'external.section.*']],
        ['key' => 'ext-timesheet', 'label' => 'External · Timesheet & Payment', 'group' => 'External', 'short' => 'Timesheet & Payment', 'match' => ['general.external.timesheet', 'general.external.timesheet.*', 'general.external.my-timesheet']],
        ['key' => 'ext-contract', 'label' => 'External · Contract',      'group' => 'External', 'short' => 'External Contract', 'match' => ['general.external.contracts', 'general.external.contracts.*']],
        ['key' => 'ext-assignment', 'label' => 'External · Assignment Letter', 'group' => 'External', 'short' => 'Assignment Letter', 'match' => ['general.external.assignment-letters', 'general.external.assignment-letters.*']],
        ['key' => 'ext-certificate', 'label' => 'External · Employment Certificate', 'group' => 'External', 'short' => 'Employment Certificate', 'match' => ['general.external.employment-certificates', 'general.external.employment-certificates.*']],
        ['key' => 'ext-reference', 'label' => 'External · Reference Letter', 'group' => 'External', 'short' => 'Reference Letter', 'match' => ['general.external.reference-letters', 'general.external.reference-letters.*']],
        // Finance
        ['key' => 'hr-reimb',    'label' => 'Finance · Reimbursement',   'group' => 'Finance', 'short' => 'Reimbursement', 'match' => ['general.reimbursement', 'general.reimbursement.*', 'general.settings.reimbursement*', 'general.approval-workflow.reimbursement']],
        ['key' => 'hr-pr',       'label' => 'Finance · Purchase Management', 'group' => 'Finance', 'short' => 'Purchase', 'match' => ['general.purchase-request', 'general.purchase-request.*', 'general.purchase-order', 'general.purchase-order.*', 'general.settings.purchase-request*', 'general.approval-workflow.purchase-request']],
        ['key' => 'hr-ca',       'label' => 'Finance · Cash Advance',    'group' => 'Finance', 'short' => 'Cash Advance', 'match' => ['general.cash-advance', 'general.cash-advance.*', 'general.cash-advance-dashboard', 'general.cash-advance-report', 'general.cash-advance-report.*', 'management.cash-advance-settings*', 'management.approval-workflow.cash-advance*']],
        ['key' => 'finance',     'label' => 'Finance · Payroll, BPJS & PPh 21', 'group' => 'Finance', 'short' => 'Payroll, BPJS & PPh 21', 'match' => ['finance', 'finance.*']],
        // Master
        ['key' => 'employee',    'label' => 'Master · Employee',         'group' => 'Master', 'short' => 'Employee', 'match' => ['employee', 'employee.*', 'master.employee*']],
        ['key' => 'customer',    'label' => 'Master · Customer',         'group' => 'Master', 'short' => 'Customer', 'match' => ['customer', 'customer.*', 'master', 'master.customer*']],
        // Work & Service
        ['key' => 'ticketing',   'label' => 'Ticketing',                 'group' => 'Work & Service', 'short' => 'Ticketing', 'match' => ['tickets', 'tickets.*', 'ticket', 'ticket.*', 'room-chat', 'room-chat.*', 'ui', 'ui.*', 'staging', 'staging.*']],
        ['key' => 'delivery',    'label' => 'Delivery',                  'group' => 'Work & Service', 'short' => 'Delivery', 'match' => ['delivery', 'delivery.*', 'delivery-project.*', 'delivery-support.*']],
        ['key' => 'sla-rpmo',    'label' => 'SLA & RPMO',                'group' => 'Work & Service', 'short' => 'SLA & RPMO', 'match' => ['sla', 'sla.*', 'rpmo', 'rpmo.*']],
        ['key' => 'reporting',   'label' => 'Reporting',                 'match' => ['reporting', 'reporting.*']],
        // Administration
        ['key' => 'management',  'label' => 'Management',                'group' => 'Administration', 'short' => 'Management', 'match' => ['management', 'management.*']],
        ['key' => 'control',     'label' => 'Control Center',            'group' => 'Administration', 'short' => 'Control Center', 'match' => ['control-center', 'control-center.*']],
        ['key' => 'apps',        'label' => 'Dashboard & AI Tools',      'match' => ['dashboard', 'ai-assistant', 'ai-research']],
    ],
    'other_module' => ['key' => 'other', 'label' => 'Lainnya (belum dikelompokkan)'],
];
