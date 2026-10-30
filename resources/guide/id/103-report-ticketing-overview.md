---
key: report-ticketing-overview
chapter: reporting
order: 103
title: Laporan Ticketing Overview
summary: Lihat tiket tiap customer menurut status, dengan total mandays customer, dan buka tiket terbuka di balik sebuah angka.
requires: [reporting.ticketing-overview]
updated: 2026-10-28
---
Ticketing Overview memberi satu baris per customer, dengan jumlah tiket yang terbuka, sedang diproses, selesai, dan dalam status lain, di samping mandays yang dibeli customer.

## Siapa yang menggunakan

Siapa pun yang punya menu `Reporting` › `Ticketing Overview`, misalnya para Head, Helpdesk, dan direktur.

## Sebelum Anda mulai

- Tiket bertipe EWA dan tiket yang disembunyikan tidak dihitung.
- `Total Mandays` adalah jumlah mandays dari semua delivery project milik customer.

## Langkah demi langkah

![Laporan Ticketing Overview dengan satu baris per customer dan jumlah tiket menurut status](images/guide/report-ticketing-overview.png)

*Tangkapan layar dengan data contoh.*

1. Buka `Reporting` › `Ticketing Overview`.
2. Cari customer dengan kotak pencarian (`Cari nama customer...`).
3. Baca tiap baris: `Customer Name`, `Total Mandays` dan batang di bawah `Ticket Status`: `Open`, `In Process`, `Close`, `Other` dan `Wait Close`. Panjang batang membandingkan status itu dengan yang lain pada baris yang sama.
4. Pilih panah di depan customer untuk melihat tiket terbukanya: `Tiket`, `Description`, `Status`, `Lead`, `Create Date` dan `Day Not Close`. Pilih sebuah tiket untuk membukanya.
5. Pakai filter di judul kolom dan `Clear sort` untuk mengembalikan urutan.

## Apa yang terjadi selanjutnya

Angkanya mengikuti tiket secara langsung. Tidak ada data yang berubah dari halaman ini.

## Pertanyaan umum

**Sebuah customer tidak punya tiket terbuka.** Barisnya lalu menyatakan tidak ada tiket terbuka untuk customer itu.

**Sebuah tiket tidak ada.** Mungkin tiket itu bertipe EWA atau disembunyikan, yang tidak diikutkan laporan ini.

<!-- sources: routes/web.php, app/Http/Controllers/ReportingController.php, resources/views/reporting/ticketing-overview.blade.php -->
