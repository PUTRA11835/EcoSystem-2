---
key: report-ticket-by-module
chapter: reporting
order: 104
title: Laporan Ticket by Modul
summary: Lihat setiap tiket yang dikelompokkan menurut modul, termasuk tiket tanpa modul, lalu ekspor daftarnya.
requires: [reporting.ticket-by-module]
updated: 2026-10-28
---
Ticket by Modul mengelompokkan semua tiket di bawah modulnya. Ini membantu module lead dan para Head melihat di mana pekerjaan berada.

## Siapa yang menggunakan

Siapa pun yang punya menu `Reporting` › `Ticket by Modul`.

## Sebelum Anda mulai

- Setiap tiket ditampilkan di bawah modul utamanya.
- Tiket tanpa modul dikumpulkan dalam kelompok bernama `No Modul Assign`, yang selalu paling akhir.

## Langkah demi langkah

![Laporan Ticket by Modul dengan kelompok tiket dan filter](images/guide/report-ticket-by-module.png)

*Tangkapan layar dengan data contoh.*

1. Buka `Reporting` › `Ticket by Modul`. Baris di bawah filter menyebut berapa modul dan tiket yang tampil.
2. Persempit daftar dengan filter: `No Tiket`, `Description`, `Status`, `Ticket Lead` dan `Modul`.
3. Pakai `Expand All` dan `Collapse All` untuk membuka atau menutup semua kelompok, atau pilih sebuah kelompok untuk membukanya.
4. Baca tabel tiap kelompok: `No Tiket`, `Description`, `Status`, `Ticket Lead`, `Modul`, `Created At` dan `Day Not Close`. Pilih nomor tiket untuk membukanya.
5. Pilih `Export` untuk mengunduh daftar.

## Apa yang terjadi selanjutnya

Hasil ekspor berisi tiket sesuai filter pada saat itu.

## Pertanyaan umum

**Sebagian besar tiket ada di `No Modul Assign`.** Modulnya belum pernah diisi. Minta ticket lead mengisinya.

<!-- sources: routes/web.php, app/Http/Controllers/ReportingController.php, resources/views/reporting/ticket-by-module.blade.php -->
