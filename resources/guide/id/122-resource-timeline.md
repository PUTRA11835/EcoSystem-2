---
key: resource-timeline
chapter: reporting
order: 122
title: Resource Timeline
summary: Lihat penempatan setiap konsultan hari demi hari, dan catat atau ubah penugasan konsultan ke customer.
requires: [reporting.resource-timeline]
updated: 2026-10-28
---
Resource Timeline adalah kisi kalender para konsultan. Setiap baris adalah seorang konsultan dan setiap kolom adalah satu hari, sehingga Anda dapat melihat siapa ditempatkan di customer mana dan kapan.

## Siapa yang menggunakan

Pimpinan delivery operation, RPMO, dan administrator yang aksesnya mencakup `Reporting` › `Resource Timeline`.

## Sebelum Anda mulai

- Penugasan dicatat menurut kode customer, jadi ketahui kode customernya lebih dulu.
- Mengubah timeline mengubah apa yang dilihat semua orang yang punya akses, jadi periksa tanggalnya sebelum menyimpan.

## Langkah demi langkah

### Baca kisinya

1. Buka `Reporting` › `Resource Timeline`.
2. Pilih `Monthly` atau `Yearly`, lalu bulan dan tahun.
3. Persempit baris dengan `Search customer code` dan filter `Home Base` (`Jakarta`, `Yogyakarta`, `Surabaya` atau `Others`).
4. Baca tiap baris: `Consultant`, `Module`, `Status` dan satu sel per hari. Hari akhir pekan diberi warna.

### Catat penugasan

1. Klik baris seorang konsultan, lalu `Create Timeline`.
2. Periksa `Consultant`, lalu isi `Start Date`, `End Date` dan `Customer Code`.
3. Pilih `Save`.

### Ubah atau hapus penugasan

1. Klik baris konsultan. `Existing entries` menampilkan tanggal yang sudah ditugaskan.
2. Hapus sebuah entri dengan ikon hapusnya, lalu buat yang benar.

## Apa yang terjadi selanjutnya

Kisi menampilkan penugasan baru pada hari yang Anda pilih, bagi semua orang yang dapat membuka halaman ini.

## Pertanyaan umum

**Kisinya kosong.** Tidak ada konsultan yang cocok dengan filter, atau belum ada yang diatur untuk bulan itu. Hapus filter lebih dulu.

**Saya tidak menemukan kode customer.** Tanyakan kepada pemilik data customer. Jangan mengarang kode.

<!-- sources: routes/web.php, app/Http/Controllers/ResourceTimelineController.php, resources/views/reporting/resource-timeline.blade.php -->
