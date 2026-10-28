---
key: guide-modul-lead
chapter: reference
order: 87
title: Panduan Modul Lead
summary: Tugas modul lead: ikuti tiket modul Anda, jaga tim tetap bergerak, dan buat laporan mingguan serta grafik.
requires: ["@role:Modul Lead"]
updated: 2026-10-28
---
Panduan ini untuk role Modul Lead. Anda memimpin satu atau lebih modul SAP atau support dan menjaga tiketnya tetap bergerak.

## Siapa yang menggunakan

Karyawan yang memimpin sebuah modul. Menu Anda mencakup `Ticket`, `My Tasks`, `Consultant Workload`, `Calendar` (`Events` dan `Timesheets`), `Reporting` (`Log Shifting`, `Diagram Report` dan `Weekly Consolidation`) dan `Master` › `Employee`.

## Sebelum Anda mulai

- Sebagian halaman hanya menampilkan modul yang Anda pimpin. Misalnya, di `Weekly Consolidation` Anda hanya dapat bekerja dengan modul Anda sendiri.
- Tiket dapat memuat informasi kredensial customer. Jangan pernah menyalinnya ke chat, catatan, email, atau dokumen lain.

## Langkah demi langkah

### Setiap hari

1. Buka `My Tasks` untuk tiket yang Anda pimpin.
2. Buka `Ticket` dan ikuti tiket modul Anda. Balas customer atau tinggalkan `Internal Note` saat ada perkembangan.
3. Buka `Consultant Workload` untuk melihat siapa di modul Anda yang masih punya ruang.
4. Catat jam kerja Anda sendiri di `Calendar` › `Timesheets`. Lihat [[calendar-timesheet]].

### Setiap minggu

1. Buka `Reporting` › `Weekly Consolidation`, pilih modul Anda, lalu buat rekap mingguan. Lihat [[weekly-consolidation]].
2. Tambahkan catatan singkat per tiket supaya rekap menjelaskan apa yang terjadi, lalu pakai `Export Excel` untuk membagikannya.

### Pelajari tren

1. Buka `Reporting` › `Diagram Report`. Halaman ini menampilkan sepuluh grafik data tiket.
2. Pakai filter `Customer` dan `Period` di atas. Grafik tetap kosong sampai Anda mengisi tanggal mulai dan tanggal akhir di `Period`.

### Catatan shift

`Reporting` › `Log Shifting` menampilkan catatan SLA antar-shift. Pilih rentang tanggal, pilih `Tampilkan`, dan `Export Excel` bila perlu.

## Apa yang terjadi selanjutnya

Rekap mingguan Anda menjadi catatan tersimpan yang dapat dibuka dan diekspor ulang oleh Anda dan pihak lain yang punya akses. Grafik diperbarui dari data tiket langsung.

## Pertanyaan umum

**Saya tidak dapat memilih sebagian modul di `Weekly Consolidation`.** Anda hanya dapat memilih modul yang Anda pimpin. Tanyakan kepada administrator bila ada yang kurang.

**Grafik `Diagram Report` meminta memilih tanggal.** Isi kedua tanggal di `Period`.

## Topik terkait

- [[weekly-consolidation]]
- [[calendar-timesheet]]

<!-- sources: routes/web.php, app/Http/Controllers/WeeklyConsolidationController.php, resources/views/reporting/diagram-report.blade.php, resources/views/reporting/log-shifting.blade.php, resources/views/ticket/task.blade.php -->
