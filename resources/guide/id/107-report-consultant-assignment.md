---
key: report-consultant-assignment
chapter: reporting
order: 107
title: Laporan Consultant Assignment
summary: Lihat konsultan mana ditempatkan di delivery project mana, berapa lama, dan seberapa terpakai.
requires: [reporting.consultant-assignment]
updated: 2026-10-28
---
Consultant Assignment mengumpulkan anggota tim setiap delivery project menjadi satu gambaran per konsultan.

## Siapa yang menggunakan

Siapa pun yang punya menu `Reporting` › `Consultant Assignment`, misalnya para head proyek, administrator, dan direktur.

## Sebelum Anda mulai

- Datanya berasal dari tab `Team` pada setiap delivery project. Untuk mengubah penugasan, ubah di proyeknya.
- Orang yang berada di beberapa proyek punya satu kelompok dengan satu baris per penugasan.

## Langkah demi langkah

![Laporan Consultant Assignment dengan kartu ringkasan dan daftar konsultan](images/guide/report-consultant-assignment.png)

*Tangkapan layar dengan data contoh.*

1. Buka `Reporting` › `Consultant Assignment`.
2. Baca kartunya: `Assignments`, `Consultants`, `Projects`, `Active`, `Upcoming`, `Ended`, `Internal`, `External / Vendor`, `Planned MD` dan `Actual MD`.
3. Cari menurut konsultan, proyek, atau customer, atau pakai filter di judul kolom: konsultan, posisi, proyek, nomor IO, customer, kategori proyek, modul, peran di proyek, tipe karyawan, dan vendor.
4. Pakai filter periode (`From`, `To`) untuk menampilkan penugasan yang beririsan dengan sebuah rentang, dan `Sort by start date` untuk mengurutkannya. Pilih `Apply`.
5. Pilih seorang konsultan, atau `Expand All`, untuk melihat tiap penugasan: `Duration`, `Status`, `Utilization` dan `Notes`.
6. Pilih `Export` untuk mengunduh daftar dengan filter yang aktif, dan `Reset Filters` untuk mengulang.

## Apa yang terjadi selanjutnya

Hasil ekspor berisi persis apa yang ditampilkan halaman dengan filter Anda. Tidak ada yang berubah pada proyek.

## Pertanyaan umum

**Seorang konsultan tidak ada.** Ia bukan anggota tim di delivery project mana pun.

**Sebuah penugasan menampilkan `Inactive employee`.** Orangnya sudah tidak aktif di data karyawan.

<!-- sources: routes/web.php, app/Http/Controllers/ReportingController.php, resources/views/reporting/consultant-assignment.blade.php -->
