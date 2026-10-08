<?php

/**
 * Pengaturan awal BPJS (Payroll Fase 2). Tiap baris = satu VERSI berlaku sejak `effective_date`
 * (append-only: versi lama tidak pernah diubah, versi baru dibuat dengan tanggal berlaku baru).
 *
 * Sumber: Perpres 64/2020 (Kesehatan: 4% pemberi kerja + 1% pekerja, batas upah Rp12.000.000),
 * PP 46/2015 (JHT 3,7% + 2%), PP 45/2015 (JP 2% + 1%; batas upah disesuaikan tiap 1 Maret —
 * Rp10.547.400 sejak 1 Mar 2025, Rp11.086.300 sejak 1 Mar 2026 per surat BPJS Ketenagakerjaan
 * B/1226/022026), PP 44/2015 (JKK 0,24% kelas risiko I, JKM 0,30%).
 *
 * ⚠ Batas bawah upah BPJS Kesehatan (UMK lokasi kerja) sengaja 0 = tidak diterapkan: nilainya berbeda
 * per kota dan harus diisi pemilik. JKK 0,24% = kelas risiko I (kantor); ubah bila kelas risikonya lain.
 */
return [
    [
        'effective_date' => '2026-01-01',
        'health_employer_rate' => 4, 'health_employee_rate' => 1, 'health_min_base' => 0, 'health_cap' => 12_000_000,
        'jht_employer_rate' => 3.7, 'jht_employee_rate' => 2,
        'jp_employer_rate' => 2, 'jp_employee_rate' => 1, 'jp_cap' => 10_547_400,
        'jkk_rate' => 0.24, 'jkm_rate' => 0.3,
        'health_in_payroll' => true, 'employment_in_payroll' => true,
        'notes' => 'Initial setting. Health minimum base (UMK) not set — fill it in. JKK 0.24% = risk class I.',
    ],
    [
        'effective_date' => '2026-03-01',
        'health_employer_rate' => 4, 'health_employee_rate' => 1, 'health_min_base' => 0, 'health_cap' => 12_000_000,
        'jht_employer_rate' => 3.7, 'jht_employee_rate' => 2,
        'jp_employer_rate' => 2, 'jp_employee_rate' => 1, 'jp_cap' => 11_086_300,
        'jkk_rate' => 0.24, 'jkm_rate' => 0.3,
        'health_in_payroll' => true, 'employment_in_payroll' => true,
        'notes' => 'JP wage cap raised to Rp11,086,300 from 1 March 2026 (BPJS Ketenagakerjaan circular B/1226/022026).',
    ],
];
