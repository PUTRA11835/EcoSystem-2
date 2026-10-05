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
 * `field` = selektor kolom isian pada halaman master/My Profile (kartu kesiapan data memakainya untuk
 * "klik → langsung ke kolom"); `prefill` = nilai dropdown yang diisi otomatis (mis. tipe identitas NPWP).
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
 * `applies` = jenis karyawan yang dikenai butir.
 *   Internal (17): semua butir.
 *   External (8, HC-D46 / keputusan E2): kontak (HP, email kerja, alamat), identitas (KTP),
 *   NPWP, dan rekening (bank, nomor, pemilik) — mengikuti form konsultan ESH. Data pribadi
 *   (tanggal/tempat lahir, gender, agama, status nikah), tanggal bergabung, BPJS, dan
 *   kontrak karyawan tidak diminta dari konsultan. (Aturan lama 9 butir — HC-D17 — keliru:
 *   diturunkan dari data yang masih kosong, bukan dari kebutuhan.)
 *
 * `hr_only` = butir yang HANYA diisi HR (mis. join date dari offering letter/kontrak). Pegawai tidak bisa
 *   mengisinya, jadi kartu Data readiness menampilkannya sebagai "HR" (tidak bisa diklik) dan penanda
 *   "needs attention" di Command Center tidak menghitungnya untuk pegawai.
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
         'hint' => 'Choose your gender on the Basic Data tab.',
         'field' => '#gender',
         'source' => 'basic', 'column' => 'gender', 'section' => 'basic-data', 'applies' => $internal],
        ['key' => 'birth_place',   'group' => 'profile', 'label' => 'Place of birth',
         'hint' => 'Enter your city of birth, as shown on your ID card, on the Basic Data tab.',
         'field' => '#birthPlace',
         'source' => 'basic', 'column' => 'birth_place', 'section' => 'basic-data', 'applies' => $internal],
        ['key' => 'birth_date',    'group' => 'profile', 'label' => 'Date of birth',
         'hint' => 'Enter your date of birth on the Basic Data tab.',
         'field' => '#birthDate',
         'source' => 'basic', 'column' => 'birth_date', 'section' => 'basic-data', 'applies' => $internal],
        ['key' => 'religion',      'group' => 'profile', 'label' => 'Religion',
         'hint' => 'Choose your religion on the Basic Data tab.',
         'field' => '#religion',
         'source' => 'basic', 'column' => 'religion', 'section' => 'basic-data', 'applies' => $internal],
        ['key' => 'marital',       'group' => 'profile', 'label' => 'Marital status',
         'hint' => 'Choose your marital status on the Basic Data tab.',
         'field' => '#maritalStatus',
         'source' => 'basic', 'column' => 'marital_status', 'section' => 'basic-data', 'applies' => $internal],
        ['key' => 'cell_phone',    'group' => 'profile', 'label' => 'Mobile phone',
         'hint' => 'Add an active mobile number (for example 0812…) on the Address tab.',
         'field' => '#cellPhone',
         'source' => 'address_any', 'column' => 'cell_phone', 'section' => 'address', 'applies' => $both],
        ['key' => 'email_work',    'group' => 'profile', 'label' => 'Work email',
         'hint' => 'Add your company email address on the Address tab.',
         'field' => '#emailWork',
         'source' => 'address_any', 'column' => 'email_work', 'section' => 'address', 'applies' => $both],
        ['key' => 'address',       'group' => 'profile', 'label' => 'Home address',
         'hint' => 'Enter your home address, as on your ID card or where you live, on the Address tab.',
         'field' => '#street',
         'source' => 'address_any', 'column' => 'street', 'section' => 'address', 'applies' => $both],
        ['key' => 'nik',           'group' => 'profile', 'label' => 'National ID (NIK / KTP)',
         'hint' => 'On the Identification tab, select ID Card (KTP) and enter the 16-digit NIK printed on it.',
         'field' => '#identificationNumber', 'prefill' => ['identificationType' => 'KTP'],
         'source' => 'identification', 'types' => ['KTP'], 'section' => 'identification', 'applies' => $both],

        // ── Payroll ─────────────────────────────────────────────────────────
        ['key' => 'bank_name',     'group' => 'payroll', 'label' => 'Bank name',
         'hint' => 'Select your bank on the Bank Account tab.',
         'field' => '#bankName',
         'source' => 'bank', 'column' => 'bank_name', 'section' => 'bank', 'applies' => $both],
        ['key' => 'bank_account',  'group' => 'payroll', 'label' => 'Bank account number',
         'hint' => 'Enter your account number (digits only) on the Bank Account tab.',
         'field' => '#bankAccountNumber',
         'source' => 'bank', 'column' => 'account_number', 'section' => 'bank', 'applies' => $both],
        ['key' => 'bank_holder',   'group' => 'payroll', 'label' => 'Account holder name',
         'hint' => 'Enter the account holder name exactly as registered at your bank, on the Bank Account tab.',
         'field' => '#bankAccountHolder',
         'source' => 'bank', 'column' => 'account_holder', 'section' => 'bank', 'applies' => $both],
        ['key' => 'npwp',          'group' => 'payroll', 'label' => 'Tax ID (NPWP)',
         'hint' => 'On the Identification tab, select Tax ID (NPWP) and enter your 15 or 16 digit number. The type “Other” is not counted.',
         'field' => '#identificationNumber', 'prefill' => ['identificationType' => 'NPWP'],
         'source' => 'identification', 'types' => ['NPWP'], 'section' => 'identification', 'applies' => $both],

        // ── BPJS ────────────────────────────────────────────────────────────
        ['key' => 'bpjs_health',   'group' => 'bpjs', 'label' => 'BPJS Kesehatan number',
         'hint' => 'On the Identification tab, select BPJS Kesehatan and enter the number on your card. The type “Other” is not counted.',
         'field' => '#identificationNumber', 'prefill' => ['identificationType' => 'BPJS_KESEHATAN'],
         'source' => 'identification', 'types' => ['BPJS_KESEHATAN'], 'section' => 'identification', 'applies' => $internal],
        ['key' => 'bpjs_employ',   'group' => 'bpjs', 'label' => 'BPJS Ketenagakerjaan (KPJ) number',
         'hint' => 'On the Identification tab, select BPJS Ketenagakerjaan and enter your KPJ number. The type “Other” is not counted.',
         'field' => '#identificationNumber', 'prefill' => ['identificationType' => 'BPJS_KETENAGAKERJAAN'],
         'source' => 'identification', 'types' => ['BPJS_KETENAGAKERJAAN'], 'section' => 'identification', 'applies' => $internal],

        // ── Contract ────────────────────────────────────────────────────────
        ['key' => 'contract',      'group' => 'contract', 'label' => 'Active employment contract',
         'hint' => 'Managed by HR. Please contact HR if your active contract is missing.',
         'hr_only' => true,
         'source' => 'contract_active', 'section' => 'contract', 'applies' => $internal],
        ['key' => 'join_date',     'group' => 'contract', 'label' => 'Join date',
         'hint' => 'Set by HR when your offer is accepted. Please contact HR if it is missing.',
         'field' => '#sinceDate',
         'hr_only' => true,
         'source' => 'basic', 'column' => 'since_date', 'section' => 'basic-data', 'applies' => $internal],
    ],
];
