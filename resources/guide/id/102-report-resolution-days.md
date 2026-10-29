---
key: report-resolution-days
chapter: reporting
order: 102
title: Laporan Resolution Days
summary: Daftar tiket yang resolution days-nya belum pernah diusulkan atau masih menunggu persetujuan Head.
requires: [reporting.resolution-days]
updated: 2026-10-28
---
Resolution Days adalah daftar tugas untuk Head: setiap tiket di perusahaan yang resolution days-nya belum disetujui.

## Siapa yang menggunakan

Siapa pun yang rolenya punya menu `Reporting` › `Resolution Days` di Menu Access, misalnya Delivery Support Head. Aksesnya hanya ditentukan oleh Menu Access. Halaman ini menampilkan tiket seluruh perusahaan, jadi berikan menunya dengan hati-hati.

## Sebelum Anda mulai

- Tiket yang disembunyikan tidak ikut.
- Sebuah tiket muncul di sini selama resolution days-nya `None` (belum pernah diusulkan) atau `Pending Head` (sudah diusulkan, menunggu persetujuan).

## Langkah demi langkah

![Laporan Resolution Days dengan kartu ringkasan dan daftar tiket](images/guide/report-resolution-days.png)

*Tangkapan layar dengan data contoh.*

1. Buka `Reporting` › `Resolution Days`.
2. Baca kartunya: `Total Unapproved` (None ditambah Pending Head), `None` (belum pernah diusulkan) dan `Pending Head` (menunggu persetujuan).
3. Baca tabelnya: `Ticket`, `Description`, `Customer`, `PIC`, `Status`, `Proposed By`, `Proposed MD` dan `Proposed At`. Tiket Pending Head tampil lebih dulu, usulan terbaru di atas.
4. Saring menurut tiket, customer, atau PIC dengan kotak pencarian di judul kolom.
5. Pilih sebuah baris untuk membuka `Review Resolution Days`. Isinya usulan per orang: `Days`, hari tambahan (`Add.`), catatan, dan angka yang disetujui, beserta totalnya.
6. Setujui atau tolak di tiketnya sendiri, pada bagian `Mandays`.

## Apa yang terjadi selanjutnya

Saat Head menyetujui sebuah usulan, tiket keluar dari daftar ini. Hari-harinya menjadi kuota yang dibandingkan MD Validation dengan timesheet.

## Pertanyaan umum

**Sebuah tiket tetap `None`.** Belum ada yang mengusulkan resolution days untuknya. Minta ticket lead mengusulkannya.

**Daftarnya panjang.** Semua tiket tanpa hari yang disetujui ada di sini. Saring menurut customer atau PIC lalu kerjakan satu per satu.

<!-- sources: routes/web.php, app/Http/Controllers/ReportingController.php, resources/views/reporting/resolution-days.blade.php -->
