---
key: report-md-validation
chapter: reporting
order: 100
title: Laporan MD Validation
summary: Bandingkan mandays yang terpakai di timesheet dengan kuota yang disetujui, per tiket dan per orang.
requires: [reporting.validation]
updated: 2026-10-28
---
MD Validation menampilkan, untuk setiap entri timesheet pada sebuah tiket, berapa mandays yang terpakai dibanding kuota yang sudah disetujui. Laporan ini cepat menunjukkan di mana waktu melebihi atau kurang dari yang disepakati.

## Siapa yang menggunakan

Siapa pun yang punya menu `Reporting` › `MD Validation`. Role dengan izin `View All Employees` (secara bawaan administrator dan Delivery Support Head) melihat entri semua orang. Yang lain hanya melihat entri miliknya sendiri.

## Sebelum Anda mulai

- Kuota berasal dari mandays terbaru yang disetujui pada setiap tiket, per orang. Tanpa kuota yang disetujui, barisnya menampilkan tanda hubung.
- Halaman ini memuat entri yang berstatus `Draft`, `Submitted` atau `Approved`. Hanya waktu yang disetujui yang dianggap final di laporan lain.

## Langkah demi langkah

![Laporan MD Validation dengan kartu ringkasan dan tabel entri](images/guide/report-md-validation.png)

*Tangkapan layar dengan data contoh.*

1. Buka `Reporting` › `MD Validation`. Label di kanan atas menampilkan periode pelaporan yang sedang terbuka, misalnya `October 2026 (Open)`.
2. Baca kartunya: `Total Entries`, `Match` (terpakai sama dengan kuota), `Less` (terpakai di bawah kuota) dan `Over` (terpakai di atas kuota).
3. Baca tabelnya: `Employee`, `Date`, `Ticket`, `Customer`, `MD Input`, `Quota MD`, `Remain`, `MD Status` dan `Approval`. `Remain` mengurangi hari yang terpakai dari kuota menurut urutan tanggal, entri terlama lebih dulu, sehingga menunjukkan sisa pada titik itu.
4. Persempit daftar dengan rentang tanggal (`From` dan `Apply`) dan kotak pencarian di judul kolom. Pilih nomor tiket untuk membuka tiketnya.
5. Pilih `Export MD` untuk mengunduh daftar sebagai spreadsheet, bila role Anda punya izin ekspor. Ekspor mengikuti cakupan yang sama dengan halamannya.

## Apa yang terjadi selanjutnya

Tidak ada data yang berubah. Bila sebuah entri `Over`, bicarakan dengan ticket lead atau Head: mungkin perlu mengusulkan mandays tambahan pada tiket.

## Pertanyaan umum

**Saya hanya melihat entri saya sendiri.** Melihat semua orang membutuhkan izin `MD Validation: View All Employees`. Tanyakan kepada administrator.

**`Quota MD` menampilkan tanda hubung.** Tiket belum punya mandays yang disetujui untuk orang itu.

**Tidak ada tombol `Export MD`.** Role Anda tidak punya izin ekspor. Tanyakan kepada administrator.

<!-- sources: routes/web.php, app/Http/Controllers/ReportingController.php, resources/views/reporting/reporting.blade.php -->
