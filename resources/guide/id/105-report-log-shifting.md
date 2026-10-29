---
key: report-log-shifting
chapter: reporting
order: 105
title: Laporan Log Shifting
summary: Kumpulkan catatan SLA yang ditulis antar-shift untuk rentang tanggal dan jam, lalu ekspor ke Excel.
requires: [reporting.log-shifting]
updated: 2026-10-28
---
Log Shifting adalah log serah terima tim support. Isinya catatan SLA yang ditulis pada tiket, supaya shift berikutnya tahu apa yang terjadi.

## Siapa yang menggunakan

Helpdesk, para support head, module lead, dan lainnya yang punya menu `Reporting` › `Log Shifting`.

## Sebelum Anda mulai

- Label di halaman memakai bahasa Indonesia, seperti di layar: `Tampilkan` berarti tampilkan, `Dari Tanggal` dan `Sampai Tanggal` berarti dari dan sampai tanggal.
- Hanya tiket yang punya sedikitnya satu catatan SLA yang muncul. Tiket yang disembunyikan tidak ikut.

## Langkah demi langkah

![Halaman Log Shifting dengan rentang tanggal, tombol cepat, dan daftar catatan SLA](images/guide/report-log-shifting.png)

*Tangkapan layar dengan data contoh.*

1. Buka `Reporting` › `Log Shifting`.
2. Pilih rentang cepat dengan `Hari Ini`, `Kemarin`, `7 Hari Terakhir` atau `Bulan Ini`. Atau isi `Dari Tanggal` dan `Sampai Tanggal`, dan bila perlu jamnya untuk mempersempit ke satu shift.
3. Pilih `Tampilkan`. Halaman menyebut berapa catatan ditemukan untuk periode itu dan mencantumkannya: `No Tiket`, `Deskripsi`, `Tanggal`, `Jam`, `SLA Note` dan `PIC`. Pilih sebuah baris untuk membuka tiketnya di tab baru.
4. Pilih `Export Excel` untuk mengunduh rentang yang sama sebagai laporan. `Reset Semua` menghapus filter.
5. Di bawah `Ringkasan per Tiket` Anda melihat setiap tiket yang pernah punya catatan SLA. Pilih sebuah baris untuk membaca semua catatannya, tanpa filter tanggal.

## Apa yang terjadi selanjutnya

Hasil ekspor berisi catatan pada rentang yang Anda pilih, siap dilampirkan ke laporan shift.

## Pertanyaan umum

**Tabel menulis `Belum ada data`.** Pilih rentang tanggal lalu pilih `Tampilkan` terlebih dahulu.

**Rentang itu tidak menampilkan catatan.** Tidak ada catatan SLA yang ditulis pada rentang itu. Lebarkan tanggal atau jamnya.

<!-- sources: routes/web.php, app/Http/Controllers/ReportingController.php, resources/views/reporting/log-shifting.blade.php -->
