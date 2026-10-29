---
key: report-collection-outlook
chapter: reporting
order: 106
title: Laporan Collection Outlook
summary: Lihat rencana penagihan per bulan dari termin pembayaran proyek dan item support, dan perbarui status pembayarannya.
requires: [reporting.collection-outlook, reporting.collection-outlook-support]
updated: 2026-10-28
---
Collection Outlook mengubah Terms Of Payment pada delivery project menjadi gambaran penagihan bulan demi bulan. `Collection Outlook` mencakup proyek, dan `Collection Outlook (Support)` mencakup item support.

## Siapa yang menggunakan

Sales, para head proyek dan support, administrator, dan direktur yang punya menu `Reporting` › `Collection Outlook` atau `Collection Outlook (Support)`.

## Sebelum Anda mulai

- Setiap tagihan berada di bulan tanggal perkiraannya. Bila kosong, dipakai tanggal bayar atau tanggal invoice.
- Jumlah pada outlook mengikuti termin pembayaran yang tersimpan di setiap proyek atau item support. Jumlah yang hilang biasanya berarti termin itu belum diisi di sana.
- Periodenya dibatasi: dari 3 tahun ke belakang sampai 15 tahun ke depan, dan paling banyak 240 bulan dalam satu tampilan.

## Langkah demi langkah

![Laporan Collection Outlook dengan total dan tabel bulanan](images/guide/collection-outlook.png)

*Tangkapan layar dengan data contoh.*

1. Buka `Reporting` › `Collection Outlook` untuk proyek, atau `Collection Outlook (Support)` untuk item support.
2. Isi `From` dan `To` (bulan dan tahun). Pilih `Account Executive` (`All AE` untuk semua orang) pada laporan proyek, atau `Type` pada laporan support. Pilih `Apply`.
3. Baca kartunya: `Total Outlook`, `Paid`, `Invoiced`, `Not Invoiced` dan `Payment Terms`.
4. Di tabel, setiap baris adalah satu termin pembayaran (`TOP No.`) milik sebuah proyek atau item support, dengan satu kolom per bulan. Hijau berarti sudah dibayar, biru berarti sudah di-invoice dan menunggu pembayaran, dan merah berarti belum di-invoice.
5. Klik sebuah jumlah untuk membuka `Term Of Payment Details`: jumlah tagihan, informasi termin (proyek, klien, nomor IO, nama termin, persentase, pendapatan, persyaratan), dan informasi invoice.
6. Pilih `Export Excel` untuk mengunduh tampilan ini.

### Perbarui status pembayaran

Bila role Anda boleh mengubahnya, `Edit Payment Status` muncul di jendela detail.

1. Pilih `Status`: `Open`, `Invoiced`, `Paid` atau `Delay`.
2. `Invoiced` membutuhkan `Submit Invoice Date` dan `Invoice Number`. `Paid` membutuhkan `Paid Date`.
3. Simpan.

## Apa yang terjadi selanjutnya

Perubahan disimpan pada termin pembayaran, sehingga proyek atau item support menampilkan status yang sama.

## Pertanyaan umum

**Outlook menampilkan Rp0 atau tanda hubung.** Termin belum punya jumlah atau tanggal. Isi di proyek atau item support.

**Periode ditolak.** Periode melewati batas di atas. Pilih rentang yang lebih pendek.

**Saya tidak dapat mengubah status.** Mengubah adalah izin tersendiri pada role Anda.

<!-- sources: routes/web.php, app/Http/Controllers/ReportingController.php, resources/views/reporting/collection-outlook.blade.php, resources/views/reporting/collection-outlook-support.blade.php -->
