---
key: guide-rpmo-head
chapter: reference
order: 83
title: Panduan Delivery RPMO Head
summary: Rutinitas bulanan RPMO Head: membuat dan membuka periode, memantau tim, memutuskan akses terlambat, dan menutup periode.
requires: ["@role:Delivery RPMO Head"]
updated: 2026-10-28
---
Panduan ini untuk role Delivery RPMO Head. Tugas utama Anda di EcoSystem adalah menjalankan periode pelaporan bulanan agar setiap tim dapat mencatat timesheet tepat waktu.

## Siapa yang menggunakan

Orang yang memegang role Delivery RPMO Head. Jika Anda juga memegang role lain, Anda ikut melihat panduan role tersebut.

## Sebelum Anda mulai

- Semua langkah di bawah dilakukan di `RPMO` › `Period Management`. Petunjuk lengkapnya ada di [[period-management]].
- Keputusan Anda memengaruhi apa yang dapat dikirim orang lain. Baca tiap permintaan sebelum memutuskan dan tulis catatan yang jelas.
- Data karyawan dan timesheet yang Anda lihat di sini hanya untuk pekerjaan Anda. Jangan menyalinnya ke chat atau email.

## Langkah demi langkah

### Setiap bulan

1. **Buat periode berikutnya** dengan `New Period`. Periksa `Start Date` dan `End Date`, yang biasanya berjalan dari tanggal 21 sampai tanggal 20.
2. **Buka secara global** dengan `Open Period Globally`.
3. **Biarkan para Head membuka bagiannya.** Project Head dan Support Head membuka sendiri `Project Domain` dan `Support Domain`, agar timnya dapat mencatat timesheet.
4. **Putuskan permintaan akses terlambat** di `Exception Requests`. Permintaan sampai kepada Anda setelah Head menyetujuinya dan berstatus `Waiting for RPMO Approval`. Tetapkan `Access Deadline`, tulis `RPMO Notes`, lalu setujui atau tolak.
5. **Tutup periode secara global** dengan `Close Period Globally` setelah bagian Project dan Support sama-sama ditutup.

### Bila ada hal mendesak

- Pakai `Force Close Project` atau `Force Close Support` untuk menutup bagian tim yang tidak dapat menunggu. Ini tidak dapat dibatalkan.
- Pakai `Audit Log` untuk melihat siapa yang membuka atau menutup apa dan kapan.

## Apa yang terjadi selanjutnya

Saat Anda membuka periode, tim dapat mencatat timesheet untuk periode itu. Saat Anda menutupnya, tidak ada yang dapat menambah timesheet kecuali permintaan akses terlambat sudah disetujui. Persetujuan di `Calendar` › `Timesheets` lalu dilanjutkan oleh para Head.

## Pertanyaan umum

**Mengapa saya tidak dapat membuka bagian Project atau Support?** Tombol itu milik Project Head dan Support Head. Anda membuka periode secara global, dan dapat menutup paksa sebuah bagian dalam kasus mendesak.

**`Close Period Globally` berwarna abu-abu.** Kedua bagian harus ditutup lebih dulu.

## Topik terkait

- [[period-management]]

<!-- sources: routes/web.php, app/Http/Controllers/PeriodManagementController.php, resources/views/rpmo/periods/index.blade.php -->
