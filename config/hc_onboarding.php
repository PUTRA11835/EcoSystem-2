<?php

/**
 * ============================================================================
 * HUMAN CAPITAL — ONBOARDING: aturan "data master sudah lengkap"
 * ============================================================================
 *
 * Progres Onboarding TIDAK disimpan; dihitung dari data master employee setiap
 * kali halaman dibuka (App\Services\Onboarding\OnboardingProgressService).
 * Berkas ini satu-satunya tempat mengubah apa yang dianggap "lengkap".
 *
 * Bentuk mengikuti panel "Status Kesiapan Data" pada aplikasi acuan (ESH):
 * Tiap butir punya `hint` (satu baris cara mengisi, HC-D31) yang tampil di banner My Profile
 * dan halaman detail Onboarding. Ubah teks di sini tanpa migrasi.
 *
 * 17 item inti dalam 4 kelompok kesiapan — Profile, Payroll, Contract, BPJS.
 * Daftar butir ESH tidak terlihat utuh di layar; pemetaan di bawah adalah
 * USULAN awal (HC-D14, sinkronisasi/07 §4) dan boleh diubah tanpa migrasi.
 *
 * Sumber butir (`source`):
 *   basic            kolom di employee_basic_data                (column)
 *   address_any      salah satu baris employee_address berisi kolom (column)
 *   identification   baris employee_identification bertipe tertentu (types)
 *   bank             salah satu baris employee_bank berisi kolom  (column)
 *   contract_active  ada kontrak aktif yang punya start_date
 *
 * `applies` = jenis karyawan yang dikenai butir. Default: butir identitas resmi,
 * payroll, BPJS, dan kontrak HANYA untuk Internal — karyawan External (konsultan,
 * saat ini 0% memiliki KTP/rekening di sistem) hanya dinilai pada data profil dasar.
 * Ini nilai bawaan sampai pemilik memutuskan (keputusan M5).
 *
 * `section` = kunci tab pada halaman master employee (?section=...) untuk tautan
 * "lengkapi sekarang".
 */
$both     = ['Internal', 'External'];
$internal = ['Internal'];

return [

    'groups' => [
        'profile'  => 'Profile',
        'payroll'  => 'Payroll',
        'bpjs'     => 'BPJS',
        'contract' => 'Contract',
    ],

    'items' => [
        // ── Profile ─────────────────────────────────────────────────────────
        ['key' => 'gender',        'group' => 'profile', 'label' => 'Gender',
         'hint' => 'Basic Data tab → Gender.',
         'source' => 'basic', 'column' => 'gender', 'section' => 'basic-data', 'applies' => $both],
        ['key' => 'birth_place',   'group' => 'profile', 'label' => 'Place of birth',
         'hint' => 'Basic Data tab → Birth Place (city, as on your ID card).',
         'source' => 'basic', 'column' => 'birth_place', 'section' => 'basic-data', 'applies' => $both],
        ['key' => 'birth_date',    'group' => 'profile', 'label' => 'Date of birth',
         'hint' => 'Basic Data tab → Birth Date.',
         'source' => 'basic', 'column' => 'birth_date', 'section' => 'basic-data', 'applies' => $both],
        ['key' => 'religion',      'group' => 'profile', 'label' => 'Religion',
         'hint' => 'Basic Data tab → Religion.',
         'source' => 'basic', 'column' => 'religion', 'section' => 'basic-data', 'applies' => $both],
        ['key' => 'marital',       'group' => 'profile', 'label' => 'Marital status',
         'hint' => 'Basic Data tab → Marital Status.',
         'source' => 'basic', 'column' => 'marital_status', 'section' => 'basic-data', 'applies' => $both],
        ['key' => 'cell_phone',    'group' => 'profile', 'label' => 'Mobile phone',
         'hint' => 'Address tab → Cell Phone (an active number, e.g. 0812…).',
         'source' => 'address_any', 'column' => 'cell_phone', 'section' => 'address', 'applies' => $both],
        ['key' => 'email_work',    'group' => 'profile', 'label' => 'Work email',
         'hint' => 'Address tab → Email (Work): your company email address.',
         'source' => 'address_any', 'column' => 'email_work', 'section' => 'address', 'applies' => $both],
        ['key' => 'address',       'group' => 'profile', 'label' => 'Home address',
         'hint' => 'Address tab → Street: your home address (as on your ID card or where you live).',
         'source' => 'address_any', 'column' => 'street', 'section' => 'address', 'applies' => $both],
        ['key' => 'nik',           'group' => 'profile', 'label' => 'National ID (NIK / KTP)',
         'hint' => 'Identification tab → Type: ID Card (KTP), then the 16-digit NIK printed on your KTP.',
         'source' => 'identification', 'types' => ['KTP'], 'section' => 'identification', 'applies' => $internal],

        // ── Payroll ─────────────────────────────────────────────────────────
        ['key' => 'bank_name',     'group' => 'payroll', 'label' => 'Bank name',
         'hint' => 'Bank Account tab → Bank Name.',
         'source' => 'bank', 'column' => 'bank_name', 'section' => 'bank', 'applies' => $internal],
        ['key' => 'bank_account',  'group' => 'payroll', 'label' => 'Bank account number',
         'hint' => 'Bank Account tab → Account Number (digits only, as on your passbook or banking app).',
         'source' => 'bank', 'column' => 'account_number', 'section' => 'bank', 'applies' => $internal],
        ['key' => 'bank_holder',   'group' => 'payroll', 'label' => 'Account holder name',
         'hint' => 'Bank Account tab → Account Holder (name exactly as registered at the bank).',
         'source' => 'bank', 'column' => 'account_holder', 'section' => 'bank', 'applies' => $internal],
        ['key' => 'npwp',          'group' => 'payroll', 'label' => 'Tax ID (NPWP)',
         'hint' => 'Identification tab → Type: Tax ID (NPWP), then your 15–16 digit number. Pick this exact type — “Other” is not counted.',
         'source' => 'identification', 'types' => ['NPWP'], 'section' => 'identification', 'applies' => $internal],

        // ── BPJS ────────────────────────────────────────────────────────────
        ['key' => 'bpjs_health',   'group' => 'bpjs', 'label' => 'BPJS Kesehatan number',
         'hint' => 'Identification tab → Type: BPJS Kesehatan, then the number on your BPJS Kesehatan card. Pick this exact type — “Other” is not counted.',
         'source' => 'identification', 'types' => ['BPJS_KESEHATAN'], 'section' => 'identification', 'applies' => $internal],
        ['key' => 'bpjs_employ',   'group' => 'bpjs', 'label' => 'BPJS Ketenagakerjaan (KPJ) number',
         'hint' => 'Identification tab → Type: BPJS Ketenagakerjaan, then your KPJ number. Pick this exact type — “Other” is not counted.',
         'source' => 'identification', 'types' => ['BPJS_KETENAGAKERJAAN'], 'section' => 'identification', 'applies' => $internal],

        // ── Contract ────────────────────────────────────────────────────────
        ['key' => 'contract',      'group' => 'contract', 'label' => 'Active employment contract',
         'hint' => 'Maintained by HR. Contact HR if your active contract is missing.',
         'source' => 'contract_active', 'section' => 'contract', 'applies' => $internal],
        ['key' => 'join_date',     'group' => 'contract', 'label' => 'Join date',
         'hint' => 'Maintained by HR (Basic Data → Since Date). Contact HR if it is missing.',
         'source' => 'basic', 'column' => 'since_date', 'section' => 'basic-data', 'applies' => $both],
    ],
];
