---
key: weekly-consolidation
chapter: reporting
order: 120
title: Weekly Consolidation
summary: Simpan rekap mingguan tiket terbuka dari satu atau lebih modul, tambahkan catatan per tiket, dan ekspor ke Excel.
requires: [reporting.weekly-consolidation]
updated: 2026-10-28
---
Weekly Consolidation mengubah tiket terbuka sebuah modul menjadi rekap mingguan tersimpan yang dapat Anda segarkan, beri catatan, dan ekspor.

## Siapa yang menggunakan

Module lead, Helpdesk, dan para Head. Module lead hanya dapat bekerja dengan modul yang dipimpinnya. Helpdesk, Head, dan administrator dapat memakai semua modul. Menggabungkan beberapa modul, serta mengubah atau menghapus rekap tersimpan, bergantung pada izin yang diberikan administrator untuk role Anda.

## Sebelum Anda mulai

- Sebuah rekap terikat pada periode. Tentukan tanggal mulai dan akhir lebih dulu, misalnya Senin sampai Minggu.
- Rekap memuat tiket dari modul yang dipilih. Tiket yang tidak cocok tidak ikut.

## Langkah demi langkah

### Buat rekap

1. Buka `Reporting` › `Weekly Consolidation`.
2. Di bawah `Module`, pilih modul Anda (atau `All Modules` bila boleh). Lalu pilih `View Tickets` untuk melihat pratinjau tiket terbuka. Belum ada yang tersimpan.
3. Di bilah kuning, isi `Period Start` dan `Period End`. Tambahkan `Period Label` bila mau, misalnya `Week 43, October 2026`.
4. Pilih `Generate Recon`. Rekap tersimpan dan bilah hijau mengonfirmasinya.

![Rekap tersimpan dengan bilah aksi hijau dan tabel tiket](images/guide/weekly-consolidation.png)

*Tangkapan layar dengan data contoh.*

### Bekerja dengan rekap tersimpan

1. Baca tabelnya: `Last Update`, `Ticket`, `Description`, `Start Date`, `Type`, `Status`, `Module`, `Lead & Member`, `PIC`, `Progress`, `Deliverable` dan `Notes`.
2. Pilih sebuah baris tiket untuk membaca detailnya, dan tambahkan catatan di `Notes` untuk menjelaskan keadaannya.
3. Pilih `Refresh` untuk membawa perubahan tiket terbaru.
4. Pilih `Export Excel` untuk mengunduh rekap, dan `Close` untuk keluar.
5. Bila role Anda mengizinkan, pakai `Edit` untuk mengubah modul atau periode dan `Delete` untuk menghapus rekap.

### Cari rekap lama

`Recon History` di bagian atas menampilkan setiap rekap dengan modul, periode, jumlah tiket, dan siapa yang membuatnya. Pilih `Open` untuk melanjutkan atau `Export` untuk mengunduhnya.

## Apa yang terjadi selanjutnya

Rekap tetap tersimpan beserta catatannya. Membukanya lagi menampilkan data tiket terkini setelah Anda memilih `Refresh`.

## Pertanyaan umum

**Saya tidak melihat modul saya.** Anda hanya dapat memilih modul yang Anda pimpin. Tanyakan kepada administrator bila ada yang kurang.

**`All Modules` tidak ditawarkan.** Menggabungkan modul adalah izin tersendiri pada role Anda.

**Angkanya berubah setelah beberapa hari.** Pilih `Refresh` untuk memperbarui rekap, atau buat yang baru.

<!-- sources: app/Http/Controllers/WeeklyConsolidationController.php, resources/views/reporting/weekly-consolidation.blade.php -->
