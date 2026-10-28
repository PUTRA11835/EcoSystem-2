---
key: customer-md
chapter: reporting
order: 121
title: Laporan Customer MD
summary: Lihat semua Change Request dan semua tiket dengan usulan Customer Mandays dalam satu daftar, lalu ekspor.
requires: [reporting.customer-md]
updated: 2026-10-28
---
Customer MD menampilkan tiket yang membawa mandays customer: semua tiket bertipe Change Request dan semua tiket dengan usulan Customer Mandays yang aktif.

## Siapa yang menggunakan

Pimpinan delivery operation, para Head proyek dan support, serta administrator yang aksesnya mencakup `Reporting` › `Customer MD`.

## Sebelum Anda mulai

- Laporan ini menampilkan data semua customer yang boleh Anda lihat. Jangan menyalinnya ke chat atau email.
- Laporan hanya membaca data tiket. Untuk mengubah usulan, buka tiketnya.

## Langkah demi langkah

1. Buka `Reporting` › `Customer MD`.

![Laporan Customer MD dengan daftar tiket](images/guide/customer-md.png)

*Tangkapan layar dengan data contoh.*

2. Baca kolomnya: `No Tiket`, `Description`, `Type`, `Customer`, `Delivery`, `Ticket Lead`, `Customer MD Status`, `Customer Mandays` dan `Created At`.
3. Persempit daftar dengan kontrol filter dan urutan di judul kolom, misalnya menurut customer, delivery, atau status.
4. Pakai `Show` untuk memilih jumlah baris per halaman, dan tombol halaman untuk berpindah.
5. Pilih nomor tiket untuk membuka tiketnya.
6. Pilih `Export` untuk mengunduh daftar sebagai spreadsheet.

## Apa yang terjadi selanjutnya

Hasil ekspor berisi tampilan yang tersaring pada saat itu. Tidak ada yang berubah pada tiket.

## Pertanyaan umum

**`Customer MD Status` menampilkan `None`.** Tiket itu Change Request yang belum punya usulan Customer Mandays.

**Tiket yang saya harapkan tidak ada.** Tiket harus berupa Change Request atau punya usulan Customer Mandays yang aktif. Periksa juga filternya.

<!-- sources: routes/web.php, app/Http/Controllers/ReportingController.php, resources/views/reporting/customer-md.blade.php -->
