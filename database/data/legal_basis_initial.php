<?php

/**
 * Isi awal Register Dasar Hukum (tabel `legal_basis_register`).
 *
 * ⚠ DITULIS DARI PENGETAHUAN PENYUSUN TANPA PEMERIKSAAN DARING: SEMUA baris berstatus `needs_review` dan harus diulas HR / Legal / Tax
 * terhadap naskah resmi (JDIH Kemnaker, peraturan.go.id, DJP, BPJS) sebelum ditandai "Reviewed". Kolom `confidence` = keyakinan penyusun
 * (high / medium / low), BUKAN pengganti ulasan. `source_url` sengaja kosong — isi tautan resmi saat mengulas.
 * `payload` dibaca kalkulator (Offboarding); mengubahnya = mengubah perhitungan, karena itu setiap perubahan dibuat sebagai VERSI baru.
 */
return [
    // ── PPh 21 ──────────────────────────────────────────────────────────
    [
        'rule_key' => 'pph21.ter', 'module' => 'pph21', 'regulation_type' => 'PP', 'number' => '58', 'year' => 2023,
        'title' => 'Tarif pemotongan PPh Pasal 21 atas penghasilan sehubungan dengan pekerjaan (tarif efektif rata-rata, TER)',
        'article' => 'PP 58/2023 dan PMK 168/2023 (lampiran tabel TER kategori A, B, C)', 'effective_date' => '2024-01-01', 'last_amended_date' => null,
        'amended_by' => null, 'confidence' => 'high', 'payload' => null,
        'notes' => 'TER dipakai untuk Januari–November; Desember memakai tarif Pasal 17 (true-up). Tabel TER tersimpan di pph21_ter_rates.',
    ],
    [
        'rule_key' => 'pph21.progressive', 'module' => 'pph21', 'regulation_type' => 'UU', 'number' => '7', 'year' => 2021,
        'title' => 'Harmonisasi Peraturan Perpajakan (UU HPP) — perubahan tarif Pasal 17 UU PPh',
        'article' => 'Pasal 17 ayat (1) huruf a UU PPh sebagaimana diubah UU 7/2021', 'effective_date' => '2022-01-01', 'last_amended_date' => '2021-10-29',
        'amended_by' => 'UU 36/2008 (UU PPh) diubah oleh UU 7/2021', 'confidence' => 'high',
        'payload' => ['brackets' => [['up_to' => 60000000, 'rate' => 0.05], ['up_to' => 250000000, 'rate' => 0.15], ['up_to' => 500000000, 'rate' => 0.25], ['up_to' => 5000000000, 'rate' => 0.30], ['up_to' => null, 'rate' => 0.35]]],
        'notes' => 'Lapisan 5% / 15% / 25% / 30% / 35% sampai Rp60 jt / 250 jt / 500 jt / 5 M / di atasnya. Tersimpan di pph21_progressive_brackets.',
    ],
    [
        'rule_key' => 'pph21.ptkp', 'module' => 'pph21', 'regulation_type' => 'PMK', 'number' => '101/PMK.010', 'year' => 2016,
        'title' => 'Penyesuaian besarnya Penghasilan Tidak Kena Pajak (PTKP)',
        'article' => 'Pasal 1 PMK 101/PMK.010/2016 (WP orang pribadi Rp54 jt; tambahan kawin Rp4,5 jt; tanggungan Rp4,5 jt maks. 3)', 'effective_date' => '2016-01-01',
        'last_amended_date' => null, 'amended_by' => null, 'confidence' => 'high', 'payload' => null,
        'notes' => 'Kode saat ini tidak mengutip PMK ini; angka PTKP tersimpan di pph21_ptkp (per tahun).',
    ],
    [
        'rule_key' => 'pph21.occupational_cost', 'module' => 'pph21', 'regulation_type' => 'PMK', 'number' => '250/PMK.03', 'year' => 2008,
        'title' => 'Biaya jabatan dan biaya pensiun: 5% dari penghasilan bruto, maksimum Rp500.000 per bulan (Rp6.000.000 per tahun)',
        'article' => 'PMK 250/PMK.03/2008 (turunan Pasal 21 UU PPh)', 'effective_date' => '2009-01-01', 'last_amended_date' => null, 'amended_by' => null,
        'confidence' => 'medium', 'payload' => ['rate' => 0.05, 'max_monthly' => 500000],
        'notes' => 'Seed PPh 21 hanya menyebut PP 58/2023 / PMK 168/2023 / UU HPP; sumber pokok biaya jabatan perlu dikonfirmasi Tax.',
    ],
    [
        'rule_key' => 'pph21.non_npwp', 'module' => 'pph21', 'regulation_type' => 'UU', 'number' => '36', 'year' => 2008,
        'title' => 'Tarif PPh Pasal 21 lebih tinggi 20% bagi yang tidak memiliki NPWP',
        'article' => 'Pasal 21 ayat (5a) UU PPh', 'effective_date' => '2009-01-01', 'last_amended_date' => null, 'amended_by' => 'Perubahan administrasi NPWP/NIK (PMK 112/2022) perlu dicek',
        'confidence' => 'medium', 'payload' => ['surcharge' => 0.20],
        'notes' => 'Kode Payroll memakai penambahan 20% tanpa sitasi; perlakuan sejak NPWP = NIK perlu dikonfirmasi Tax.',
    ],

    // ── BPJS ────────────────────────────────────────────────────────────
    [
        'rule_key' => 'bpjs.kesehatan', 'module' => 'bpjs', 'regulation_type' => 'Perpres', 'number' => '82', 'year' => 2018,
        'title' => 'Jaminan Kesehatan — iuran pekerja penerima upah 5% (4% pemberi kerja + 1% pekerja) dengan batas upah',
        'article' => 'Perpres 82/2018 sebagaimana diubah Perpres 64/2020 (iuran dan batas atas upah)', 'effective_date' => '2020-07-01', 'last_amended_date' => '2020-05-05',
        'amended_by' => 'Perpres 64/2020; periksa juga Perpres 59/2024', 'confidence' => 'medium', 'payload' => ['employer' => 0.04, 'employee' => 0.01],
        'notes' => 'Batas atas upah Rp12 juta dan tanggal berlakunya perlu dicek (Perpres 75/2019 dan 64/2020). Kode hanya mengutip seed BPJS, tanpa Perpres.',
    ],
    [
        'rule_key' => 'bpjs.jht', 'module' => 'bpjs', 'regulation_type' => 'PP', 'number' => '46', 'year' => 2015,
        'title' => 'Penyelenggaraan Program Jaminan Hari Tua — iuran 5,7% (3,7% pemberi kerja + 2% pekerja)',
        'article' => 'PP 46/2015 (iuran JHT)', 'effective_date' => '2015-07-01', 'last_amended_date' => null, 'amended_by' => null,
        'confidence' => 'high', 'payload' => ['employer' => 0.037, 'employee' => 0.02], 'notes' => null,
    ],
    [
        'rule_key' => 'bpjs.jp', 'module' => 'bpjs', 'regulation_type' => 'PP', 'number' => '45', 'year' => 2015,
        'title' => 'Penyelenggaraan Program Jaminan Pensiun — iuran 3% (2% pemberi kerja + 1% pekerja) dengan batas upah tahunan',
        'article' => 'PP 45/2015 (iuran JP); batas upah ditetapkan BPJS Ketenagakerjaan setiap tahun', 'effective_date' => '2015-07-01', 'last_amended_date' => null,
        'amended_by' => 'Penyesuaian batas upah tahunan lewat surat BPJS Ketenagakerjaan', 'confidence' => 'medium', 'payload' => ['employer' => 0.02, 'employee' => 0.01],
        'notes' => 'Angka batas upah 2026 dan nomor surat rujukannya tidak dapat diverifikasi penyusun — konfirmasi ke dokumen BPJS.',
    ],
    [
        'rule_key' => 'bpjs.jkk_jkm', 'module' => 'bpjs', 'regulation_type' => 'PP', 'number' => '44', 'year' => 2015,
        'title' => 'Jaminan Kecelakaan Kerja (0,24%–1,74% sesuai kelompok risiko) dan Jaminan Kematian (0,30%)',
        'article' => 'PP 44/2015 sebagaimana diubah PP 82/2019', 'effective_date' => '2015-07-01', 'last_amended_date' => '2019-11-01', 'amended_by' => 'PP 82/2019',
        'confidence' => 'medium', 'payload' => ['jkk_min' => 0.0024, 'jkk_max' => 0.0174, 'jkm' => 0.003], 'notes' => 'Perubahan PP 82/2019 belum dikutip di kode.',
    ],

    // ── Lembur & PKWT ───────────────────────────────────────────────────
    [
        'rule_key' => 'overtime.multipliers', 'module' => 'overtime', 'regulation_type' => 'Kepmenaker', 'number' => 'KEP.102/MEN/VI', 'year' => 2004,
        'title' => 'Waktu kerja lembur dan upah kerja lembur — upah sejam = 1/173 upah sebulan; pengali 1,5 / 2 / 3 / 4',
        'article' => 'Kepmenakertrans 102/2004 dan PP 35/2021 Pasal 31–33 (hari kerja 5/6 hari)', 'effective_date' => '2004-06-25', 'last_amended_date' => '2021-02-02',
        'amended_by' => 'PP 35/2021 mengatur ulang pengali', 'confidence' => 'medium', 'payload' => ['monthly_divisor' => 173],
        'notes' => 'Kode mengutip PP 35/2021 saja; pembagi 173 berasal dari Kepmen. Pengali dikeraskan di OvertimeRateService (tidak dapat diubah dari Settings).',
    ],
    [
        'rule_key' => 'pkwt.max_term', 'module' => 'contract', 'regulation_type' => 'PP', 'number' => '35', 'year' => 2021,
        'title' => 'Perjanjian Kerja Waktu Tertentu — jangka waktu paling lama 5 (lima) tahun termasuk perpanjangan',
        'article' => 'Pasal 8 PP 35/2021', 'effective_date' => '2021-02-02', 'last_amended_date' => null, 'amended_by' => null, 'confidence' => 'high',
        'payload' => ['max_years' => 5], 'notes' => 'Dibaca config/hc_contract.php (pkwt_max_months = 60).',
    ],

    // ── Offboarding ─────────────────────────────────────────────────────
    [
        'rule_key' => 'offboarding.pkwt_compensation', 'module' => 'offboarding', 'regulation_type' => 'PP', 'number' => '35', 'year' => 2021,
        'title' => 'Uang kompensasi PKWT: masa kerja ÷ 12 × 1 bulan upah (dihitung proporsional bagi masa kerja kurang dari 12 bulan)',
        'article' => 'Pasal 15–17 PP 35/2021', 'effective_date' => '2021-02-02', 'last_amended_date' => null, 'amended_by' => null, 'confidence' => 'medium',
        'payload' => ['months_per_year' => 12, 'min_service_months' => 1], 'notes' => 'Berlaku untuk PKWT yang berakhir; bila pemberi kerja mengakhiri lebih awal, lihat risiko penalti (sisa masa kontrak).',
    ],
    [
        'rule_key' => 'offboarding.severance_table', 'module' => 'offboarding', 'regulation_type' => 'PP', 'number' => '35', 'year' => 2021,
        'title' => 'Tabel uang pesangon dan uang penghargaan masa kerja (UPMK) menurut masa kerja',
        'article' => 'Pasal 40 ayat (2) dan (3) PP 35/2021', 'effective_date' => '2021-02-02', 'last_amended_date' => '2023-03-31',
        'amended_by' => 'UU 6/2023 (penetapan Perppu 2/2022); periksa Putusan MK 168/PUU-XXII/2024 tentang UU Ketenagakerjaan baru', 'confidence' => 'medium',
        'payload' => [
            // pesangon: [masa kerja tahun sampai (tidak termasuk), pengali bulan upah]; baris terakhir = batas atas null
            'severance' => [[1, 1], [2, 2], [3, 3], [4, 4], [5, 5], [6, 6], [7, 7], [8, 8], [null, 9]],
            'service_award' => [[3, 0], [6, 2], [9, 3], [12, 4], [15, 5], [18, 6], [21, 7], [24, 8], [null, 10]],
        ],
        'notes' => 'Keputusan MK 2024 mewajibkan UU Ketenagakerjaan baru — pantau perubahan. Kolom pertama = batas atas masa kerja (tahun, tidak termasuk); kolom kedua = pengali upah sebulan.',
    ],
    [
        'rule_key' => 'offboarding.reason_multipliers', 'module' => 'offboarding', 'regulation_type' => 'PP', 'number' => '35', 'year' => 2021,
        'title' => 'Pengali pesangon / UPMK menurut alasan pemutusan hubungan kerja',
        'article' => 'Pasal 43–57 PP 35/2021 (mis. Pasal 56 pensiun; Pasal 57 meninggal dunia)', 'effective_date' => '2021-02-02', 'last_amended_date' => '2023-03-31',
        'amended_by' => 'UU 6/2023', 'confidence' => 'medium',
        'payload' => ['reasons' => [
            'resignation'          => ['label' => 'Resignation', 'severance' => 0, 'service_award' => 0, 'leave' => true, 'article' => 'Pasal 36 huruf i, Pasal 50'],
            'end_of_pkwt'          => ['label' => 'End of PKWT', 'severance' => 0, 'service_award' => 0, 'leave' => true, 'pkwt_compensation' => true, 'article' => 'Pasal 15–17'],
            'retirement'           => ['label' => 'Retirement', 'severance' => 1.75, 'service_award' => 1, 'leave' => true, 'article' => 'Pasal 56'],
            'death'                => ['label' => 'Death', 'severance' => 2, 'service_award' => 1, 'leave' => true, 'article' => 'Pasal 57'],
            'termination_misconduct' => ['label' => 'Termination — breach of agreement', 'severance' => 0.5, 'service_award' => 1, 'leave' => true, 'article' => 'Pasal 52'],
            'termination_efficiency' => ['label' => 'Termination — efficiency', 'severance' => 1, 'service_award' => 1, 'leave' => true, 'article' => 'Pasal 43–44'],
            'mutual_agreement'     => ['label' => 'Mutual agreement', 'severance' => 0, 'service_award' => 0, 'leave' => true, 'article' => 'Pasal 36 huruf g; nilai disepakati (Separation pay manual)'],
        ]],
        'notes' => 'Daftar alasan dibatasi pada yang umum; alasan lain dihitung manual lewat kolom Separation pay / Other compensation.',
    ],
    [
        'rule_key' => 'offboarding.severance_tax', 'module' => 'offboarding', 'regulation_type' => 'PP', 'number' => '68', 'year' => 2009,
        'title' => 'PPh atas uang pesangon, uang manfaat pensiun, tunjangan hari tua dan jaminan hari tua yang dibayarkan sekaligus',
        'article' => 'Pasal 2 PP 68/2009 (lapisan: sampai Rp50 jt 0%; >50–100 jt 5%; >100–500 jt 15%; >500 jt 25%)', 'effective_date' => '2009-01-01',
        'last_amended_date' => null, 'amended_by' => null, 'confidence' => 'medium',
        'payload' => ['brackets' => [['up_to' => 50000000, 'rate' => 0.0], ['up_to' => 100000000, 'rate' => 0.05], ['up_to' => 500000000, 'rate' => 0.15], ['up_to' => null, 'rate' => 0.25]]],
        'notes' => 'Tarif progresif final; berlaku atas pesangon, UPMK dan uang pisah. Perlakuan pengganti cuti dan kompensasi PKWT perlu dikonfirmasi Tax.',
    ],
    [
        'rule_key' => 'offboarding.leave_divisor', 'module' => 'offboarding', 'regulation_type' => 'PP', 'number' => '35', 'year' => 2021,
        'title' => 'Upah sehari: upah sebulan ÷ 25 (enam hari kerja) atau ÷ 21 (lima hari kerja) — dasar uang penggantian cuti tahunan yang belum diambil',
        'article' => 'Pasal 40 ayat (4) PP 35/2021; perhitungan upah harian Pasal 21', 'effective_date' => '2021-02-02', 'last_amended_date' => null, 'amended_by' => null,
        'confidence' => 'low', 'payload' => ['six_day' => 25, 'five_day' => 21],
        'notes' => 'Pembagi yang dipakai di Offboarding Settings; ESH memakai 22. Konfirmasi HR/Legal.',
    ],
];
