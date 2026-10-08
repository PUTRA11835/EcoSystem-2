<?php

/**
 * Data awal PPh 21 tahun 2026 — SUMBER: PP 58/2023 (Lampiran: Tarif Efektif Rata-rata bulanan kategori A/B/C),
 * PMK 168/2023, UU HPP (lapisan Pasal 17), PMK 101/PMK.010/2016 (PTKP).
 *
 * TER: pasangan [batas atas (termasuk), tarif %]. Batas bawah tiap baris = batas atas baris sebelumnya
 * (tidak termasuk), baris pertama dari 0; baris terakhir `null` = tanpa batas. Tidak ada celah/tumpang tindih
 * — diuji di Pph21RatesDataTest.
 *
 * ⚠ Tabel disalin dari sumber terbuka dan dicocokkan dengan dua sumber pada batas-batasnya. Sebelum payroll
 * diaktifkan, Accounting/pemilik wajib memeriksanya terhadap lampiran PP 58/2023 asli (halaman Settings
 * mengizinkan koreksi per baris tanpa deploy).
 */
return [
    'year' => 2026,

    // Penghasilan tidak kena pajak setahun (Rp). Tambahan per tanggungan Rp4.500.000, maksimal 3.
    'ptkp' => [
        ['TK/0', 'Tidak Kawin tanpa tanggungan', 54_000_000],
        ['TK/1', 'Tidak Kawin 1 tanggungan', 58_500_000],
        ['TK/2', 'Tidak Kawin 2 tanggungan', 63_000_000],
        ['TK/3', 'Tidak Kawin 3 tanggungan', 67_500_000],
        ['K/0',  'Kawin tanpa tanggungan', 58_500_000],
        ['K/1',  'Kawin 1 tanggungan', 63_000_000],
        ['K/2',  'Kawin 2 tanggungan', 67_500_000],
        ['K/3',  'Kawin 3 tanggungan', 72_000_000],
    ],

    // Pasal 17 ayat (1) huruf a UU PPh (UU HPP): [batas atas PKP setahun (termasuk), tarif %]
    'progressive' => [
        [60_000_000, 5],
        [250_000_000, 15],
        [500_000_000, 25],
        [5_000_000_000, 30],
        [null, 35],
    ],

    'ter' => [
        'A' => [
            [5_400_000, 0], [5_650_000, 0.25], [5_950_000, 0.5], [6_300_000, 0.75], [6_750_000, 1],
            [7_500_000, 1.25], [8_550_000, 1.5], [9_650_000, 1.75], [10_050_000, 2], [10_350_000, 2.25],
            [10_700_000, 2.5], [11_050_000, 3], [11_600_000, 3.5], [12_500_000, 4], [13_750_000, 5],
            [15_100_000, 6], [16_950_000, 7], [19_750_000, 8], [24_150_000, 9], [26_450_000, 10],
            [28_000_000, 11], [30_050_000, 12], [32_400_000, 13], [35_400_000, 14], [39_100_000, 15],
            [43_850_000, 16], [47_800_000, 17], [51_400_000, 18], [56_300_000, 19], [62_200_000, 20],
            [68_600_000, 21], [77_500_000, 22], [89_000_000, 23], [103_000_000, 24], [125_000_000, 25],
            [157_000_000, 26], [206_000_000, 27], [337_000_000, 28], [454_000_000, 29], [550_000_000, 30],
            [695_000_000, 31], [910_000_000, 32], [1_400_000_000, 33], [null, 34],
        ],
        'B' => [
            [6_200_000, 0], [6_500_000, 0.25], [6_850_000, 0.5], [7_300_000, 0.75], [9_200_000, 1],
            [10_750_000, 1.5], [11_250_000, 2], [11_600_000, 2.5], [12_600_000, 3], [13_600_000, 4],
            [14_950_000, 5], [16_400_000, 6], [18_450_000, 7], [21_850_000, 8], [26_000_000, 9],
            [27_700_000, 10], [29_350_000, 11], [31_450_000, 12], [33_950_000, 13], [37_100_000, 14],
            [41_100_000, 15], [45_800_000, 16], [49_500_000, 17], [53_800_000, 18], [58_500_000, 19],
            [64_000_000, 20], [71_000_000, 21], [80_000_000, 22], [93_000_000, 23], [109_000_000, 24],
            [129_000_000, 25], [163_000_000, 26], [211_000_000, 27], [374_000_000, 28], [459_000_000, 29],
            [555_000_000, 30], [704_000_000, 31], [957_000_000, 32], [1_405_000_000, 33], [null, 34],
        ],
        'C' => [
            [6_600_000, 0], [6_950_000, 0.25], [7_350_000, 0.5], [7_800_000, 0.75], [8_850_000, 1],
            [9_800_000, 1.25], [10_950_000, 1.5], [11_200_000, 1.75], [12_050_000, 2], [12_950_000, 3],
            [14_150_000, 4], [15_550_000, 5], [17_050_000, 6], [19_500_000, 7], [22_700_000, 8],
            [26_600_000, 9], [28_100_000, 10], [30_100_000, 11], [32_600_000, 12], [35_400_000, 13],
            [38_900_000, 14], [43_000_000, 15], [47_400_000, 16], [51_200_000, 17], [55_800_000, 18],
            [60_400_000, 19], [66_700_000, 20], [74_500_000, 21], [83_200_000, 22], [95_600_000, 23],
            [110_000_000, 24], [134_000_000, 25], [169_000_000, 26], [221_000_000, 27], [390_000_000, 28],
            [463_000_000, 29], [561_000_000, 30], [709_000_000, 31], [965_000_000, 32], [1_419_000_000, 33],
            [null, 34],
        ],
    ],

    // Biaya jabatan: 5% dari penghasilan bruto, maksimal Rp500.000/bulan (Rp6.000.000/tahun) — PMK 168/2023.
    'settings' => [
        'occupational_cost_rate'        => 5.00,
        'occupational_cost_monthly_max' => 500_000,
        // Premi JKK/JKM/Kesehatan yang dibayar pemberi kerja termasuk penghasilan bruto (default sesuai ketentuan);
        // dapat diubah di Settings bila Accounting memutuskan lain.
        'include_employer_premiums'     => true,
    ],
];
