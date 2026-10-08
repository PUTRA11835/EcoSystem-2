<?php

/**
 * Setelan menu Contract (HR & General → Contract) — HC-D66.
 *
 * Semua nilai di sini DIPAKAI oleh App\Services\Contracts\ContractService / App\Support\Contracts\ContractRules;
 * tidak ada setelan mati.
 */
return [

    /*
     * Nomor kontrak: {PREFIX}/{TAHUN}/{BULAN}/{KODE}{URUT}, mis. SPKWT/2026/10/010001.
     * URUT berjalan per (jenis, tahun) dan tidak pernah dipakai ulang. KODE = 2 digit kode unit.
     * ⚠ Arti dua digit setelah bulan di ESH tidak dapat dipastikan dari tangkapan layar (nilai 01–07 pada PKWT tidak
     * cocok dengan departemen/tanggal) — nilai bawaan "01" menunggu konfirmasi pemilik.
     */
    'number' => [
        'unit_code' => '01',
        'digits'    => 4,
        'prefixes'  => ['PKWT' => 'SPKWT', 'PKWTT' => 'SPKWTT', 'EXTERNAL' => 'PKS'],
    ],

    /*
     * Kapan data induk karyawan + dokumen minimum (daftar `readiness` di bawah) harus lengkap:
     *   activate  Draft bebas dibuat; status Active hanya bila data 100% (keputusan pemilik 7 Okt 2026, HC-D66)
     *   create    seperti ESH: Add Contract tertutup sampai data 100%
     *   off       kartu kesiapan hanya informasi
     * Dibaca ContractService (server); layar hanya mengikuti.
     */
    'readiness_gate' => 'activate',

    /**
     * Meterai (bea meterai Rp 10.000). Placeholder {{meterai}} mencetak kotak bergaris putus-putus tempat meterai ditempel
     * (metode 1: unduh → tempel → unggah salinan). Ukuran kotak dan teksnya diatur di sini; letaknya diatur di template
     * (letakkan {{meterai}} di sel/paragraf mana pun, rata kiri/tengah/kanan).
     */
    'stamp' => [
        'label'         => 'MATERAI',
        'value'         => 'Rp 10.000',
        'width_cm'      => 3.4,
        'height_cm'     => 2.2,
        'upload_max_kb' => 10240,
    ],

    /** Kontrak aktif yang berakhir dalam sekian hari ditandai "Ending soon" di daftar. */
    'expiring_soon_days' => 30,

    /** Kontrak PKWT maksimal 5 tahun (PP 35/2021) — dihitung dalam bulan; server menolak lebih panjang. */
    'pkwt_max_months' => 60,

    /**
     * Syarat minimum sebelum kontrak dibuat. `check` dipahami ContractService::readinessFacts():
     *   identification:<TIPE> · address:<kolom> · hr:<kolom> · bank:<kolom> · attachment:<kata kunci>
     */
    'readiness' => [
        ['key' => 'nik',            'label' => 'NIK',                     'check' => 'identification:KTP',          'section' => 'identification'],
        ['key' => 'cell_phone',     'label' => 'Mobile number',           'check' => 'address:cell_phone',          'section' => 'address'],
        ['key' => 'email_personal', 'label' => 'Personal email',          'check' => 'address:email_personal',      'section' => 'address'],
        ['key' => 'street',         'label' => 'Domicile address',        'check' => 'address:street',              'section' => 'address'],
        ['key' => 'city',           'label' => 'City',                    'check' => 'address:city',                'section' => 'address'],
        ['key' => 'region',         'label' => 'Province',                'check' => 'address:region',              'section' => 'address'],
        ['key' => 'emergency_name', 'label' => 'Emergency contact name',  'check' => 'hr:emergency_contact_name',   'section' => 'family'],
        ['key' => 'emergency_rel',  'label' => 'Emergency contact relation', 'check' => 'hr:emergency_contact_relation', 'section' => 'family'],
        ['key' => 'bank_name',      'label' => 'Bank name',               'check' => 'bank:bank_name',              'section' => 'bank'],
        ['key' => 'bank_account',   'label' => 'Account number',          'check' => 'bank:account_number',         'section' => 'bank'],
        ['key' => 'bank_holder',    'label' => 'Account holder name',     'check' => 'bank:account_holder',         'section' => 'bank'],
        ['key' => 'doc_ktp',        'label' => 'KTP document',            'check' => 'attachment:KTP',              'section' => 'attachment'],
        ['key' => 'doc_kk',         'label' => 'KK document',             'check' => 'attachment:KK',               'section' => 'attachment'],
    ],
];
