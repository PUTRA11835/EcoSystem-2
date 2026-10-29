---
key: report-md-recap
chapter: reporting
order: 101
title: Laporan MD Recap
summary: Lihat mandays yang disetujui menurut karyawan, delivery, dan mode (onsite atau remote), lalu ekspor rekapnya.
requires: [reporting.md-recap]
updated: 2026-10-28
---
MD Recap menjumlahkan hari timesheet yang disetujui untuk setiap karyawan, dikelompokkan menurut delivery dan cara kerjanya: di lokasi atau remote.

## Siapa yang menggunakan

Siapa pun yang rolenya punya menu `Reporting` › `MD Recap` di Menu Access, misalnya Delivery Support Head dan para head proyek. Aksesnya hanya ditentukan oleh Menu Access.

## Sebelum Anda mulai

- Hanya timesheet yang disetujui yang dihitung.
- Periode pelaporan berjalan dari tanggal 21 satu bulan sampai tanggal 20 bulan berikutnya. Memilih bulan dan tahun berarti memilih periode itu.

## Langkah demi langkah

![Laporan MD Recap dengan kartu ringkasan dan entri yang dikelompokkan per karyawan](images/guide/report-md-recap.png)

*Tangkapan layar dengan data contoh.*

1. Buka `Reporting` › `MD Recap`. Label di bagian atas menampilkan periode yang sedang terbuka.
2. Pilih `Month` dan `Year`, atau biarkan pada `All Months` dan `All Years`.
3. Baca kartunya: `Total MD`, `Employees` (yang punya timesheet disetujui), `OnSite MD` (kunjungan lapangan) dan `Remote MD`.
4. Baca tabelnya. Setiap karyawan punya satu kelompok, dan di bawahnya baris per delivery dan mode (`OnSite` atau `Remote`) beserta `Mandays`. Pakai filter `Name` dan `Delivery` serta daftar `Mode` untuk mempersempit.
5. Ekspor sesuai kebutuhan:
   - `Export MD Recap (Detailed)`: satu baris per entri.
   - `Export MD Recap (Summary)`: dikelompokkan menurut karyawan dan mode.
   - `Export Resolution Days`: daftar tiket yang Resolution Days-nya belum disetujui. Tombol ini hanya muncul bila role Anda juga punya menu `Resolution Days`.

## Apa yang terjadi selanjutnya

Hasil ekspor memakai filter yang Anda pilih. Tidak ada data yang berubah.

## Pertanyaan umum

**Seseorang tidak ada di rekap.** Ia tidak punya timesheet yang disetujui pada periode yang Anda pilih.

**Bulan menampilkan tanggal yang berbeda dari dugaan saya.** Satu bulan di sini adalah satu periode pelaporan, dari tanggal 21 sampai 20.

<!-- sources: routes/web.php, app/Http/Controllers/ReportingController.php, resources/views/reporting/md-recap.blade.php -->
