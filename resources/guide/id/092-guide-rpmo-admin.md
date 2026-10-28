---
key: guide-rpmo-admin
chapter: reference
order: 92
title: Panduan Delivery RPMO Administrator
summary: Yang dipelihara RPMO administrator: catatan delivery, data master, laporan, dan ikhtisar konsultan.
requires: ["@role:Delivery RPMO Administrator"]
updated: 2026-10-28
---
Panduan ini untuk role Delivery RPMO Administrator. Anda mendukung kantor RPMO dengan catatan dan laporan.

## Siapa yang menggunakan

Delivery RPMO Administrator. Menu Anda mencakup `Calendar`, `Reporting`, `Master` (`Employee` dan `Business Partner`), `Delivery` (`Project` dan `Support`), `SLA` dan `RPMO`.

## Sebelum Anda mulai

- Anda bekerja dengan data pribadi dan informasi kontrak. Pakai hanya untuk tugas Anda dan jangan pernah menyalinnya ke chat atau email.
- Menjalankan periode pelaporan bulanan dilakukan oleh Delivery RPMO Head.

## Langkah demi langkah

### Catatan delivery

1. Buka `Delivery` › `Project` atau `Delivery` › `Support`. Pakai `Add New` atau `Add Delivery Support` untuk membuat item, dan pilih sebuah baris untuk memelihara tab-tabnya.
2. Pakai `Export Excel` untuk mengunduh daftar.

### Data master

1. Buka `Master` › `Employee` dan pakai `Create Employee` untuk menambah orang. `Export Excel` mengunduh daftarnya.
2. Buka `Master` › `Business Partner` dan pakai `Create Business Partner` untuk menambah customer. Isi `Customer Code` paling banyak 4 huruf atau angka dan `Company Name`.

### Laporan

1. `Reporting` › `Collection Outlook` dan `Collection Outlook (Support)` menampilkan perkiraan penagihan per bulan.
2. `Reporting` › `Ticket by Modul`, `Ticketing Overview` dan `Diagram Report` menampilkan angka tiket.
3. `Reporting` › `Consultant Assignment` dan `Resource Timeline` menampilkan penempatan konsultan. Lihat [[resource-timeline]].
4. `Reporting` › `Customer MD` mencantumkan change request dan mandays customer. Lihat [[customer-md]].
5. `SLA` › `SLA Report` menampilkan waktu respons dan penyelesaian.

### Waktu

Pakai `Calendar` untuk event dan `Timesheets`. Lihat [[calendar-timesheet]].

### RPMO

`RPMO` membuka halaman modul. Halaman itu menyatakan bahwa modul masih dalam pengerjaan dan mengarah ke `Period Management`.

## Apa yang terjadi selanjutnya

Catatan yang Anda simpan muncul di halaman yang memakainya. Laporan diperbarui dari data langsung.

## Pertanyaan umum

**Sebuah menu membuka halaman bertuliskan `Forbidden`.** Role Anda punya menunya, tetapi tidak punya izin untuk halaman itu, misalnya `Period Management` atau `Resolution Days`. Tanyakan kepada administrator.

**Saya tidak dapat membuka atau menutup periode pelaporan.** Itu dilakukan oleh Delivery RPMO Head.

## Topik terkait

- [[customer-md]]
- [[resource-timeline]]
- [[calendar-timesheet]]
- [[home-dashboard]]

<!-- sources: routes/web.php, resources/views/rpmo/rpmo.blade.php, resources/views/delivery, resources/views/master, resources/views/reporting -->
