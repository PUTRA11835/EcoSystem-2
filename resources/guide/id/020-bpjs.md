---
key: bpjs
chapter: finance
order: 20
title: BPJS
summary: Kelola tarif dan batas upah BPJS, periksa iuran per periode payroll, dan buat surat penonaktifan.
requires: [finance.bpjs, finance.bpjs.settings, finance.bpjs.report, finance.bpjs.letters]
updated: 2026-10-27
---
Halaman BPJS berisi tarif yang dipakai [[payroll]] untuk BPJS Kesehatan dan BPJS Ketenagakerjaan, laporan iuran per periode payroll, serta alat untuk membuat surat penonaktifan BPJS.

## Siapa yang menggunakan

Staf Finance dan payroll. Halaman ini memiliki tiga tab: `Settings`, `Report`, dan `Letters`. Anda hanya melihat tab yang diizinkan untuk Role Anda.

## Sebelum Anda mulai

- Data BPJS Employee harus terisi di `Master` › `Employee`, dan BPJS aktif pada data kompensasinya. Bila tidak, Employee tidak ikut dihitung.
- Untuk surat, Employee harus memiliki nomor BPJS Kesehatan pada data `Identification`.

## Langkah demi langkah

### Memperbarui tarif (Settings)

1. Buka `Commercial & Finance` › `BPJS` › `Settings`.
2. Baca `Active rates and new settings`. Formulir dimulai dengan tarif yang berlaku saat ini.
3. Isi `Effective date` untuk versi baru.
4. Ubah tarif `BPJS Health` (`Employer (%)`, `Employee (%)`, `Minimum base (IDR)`, `Cap (IDR)`) dan tarif `BPJS Employment` (JHT, JP, JKK, JKM, serta `JP cap (IDR)`).
5. Gunakan dua saklar `BPJS Health calculated in payroll` dan `BPJS Employment calculated in payroll` untuk menyertakan atau mengecualikan tiap kelompok.
6. Isi `Notes` untuk menjelaskan perubahan, lalu klik `Save settings`.
7. Periksa bahwa baris baru muncul di `Settings history`.

> [!IMPORTANT]
> Menyimpan akan membuat versi baru. Versi lama tetap ada di `Settings history` dan tidak diubah. Periode payroll memakai versi terbaru yang tanggal berlakunya sama dengan atau sebelum periode tersebut, jadi pilih tanggal berlaku dengan cermat. Minimum base 0 berarti tidak ada batas minimum.

Gunakan `BPJS base simulation` (`Sample wage base (IDR)`, `As of date`, `Extra family members`, lalu `Preview`) untuk melihat iuran sebelum mengandalkan sebuah versi.

### Memeriksa iuran (Report)

1. Buka tab `Report`.
2. Pilih `Payroll period`.
3. Baca totalnya: `Employees`, `Total BPJS base`, `Employee deduction`, dan `Company contribution`.
4. Periksa tabel per Employee. Klik `Export Excel` untuk mengunduhnya.

### Membuat surat penonaktifan (Letters)

1. Buka tab `Letters`.
2. Gunakan `Search employee`, `Branch`, dan `Status`, lalu klik `Filter` untuk mencari Employee yang memiliki nomor BPJS Kesehatan.
3. Pilih Employee yang akan disertakan.
4. Pada `Letter parameters`, isi `Letter date`, `Signer name`, `Signer position`, kontak, `Staff name`, dan `City`.
5. Klik `Generate`. Sistem membuat PDF dua halaman: surat pernyataan dan daftar lampiran. Surat juga tercatat di Letter Register dengan nomornya sendiri.

Bila surat dibuat keliru, klik `Void` di `Generated letters` dan isi alasannya. Nomor surat tetap terpakai.

## Apa yang terjadi selanjutnya

Payroll memakai versi BPJS yang sesuai untuk tiap periode. Tab `Report` terisi seiring periode payroll dihitung.

## Pertanyaan umum

**Mengapa seorang Employee tidak ada di laporan?** BPJS mungkin nonaktif untuk Employee tersebut, atau periode belum dihitung.

**Bisakah tarif lama diubah?** Tidak. Tambahkan versi baru dengan tanggal berlaku baru.

## Topik terkait

- [[payroll]]
- [[pph21]]
- [[legal-basis]]
- [[employee-data]]

<!-- sources: routes/finance.php, app/Http/Controllers/Finance/BpjsSettingsController.php, app/Http/Controllers/Finance/FinanceReportController.php, app/Http/Controllers/Finance/BpjsLetterController.php, resources/views/finance/bpjs/*.blade.php -->
