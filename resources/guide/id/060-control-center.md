---
key: control-center
chapter: administration
order: 60
title: Control Center - Pemantauan dan Log
summary: Periksa kesehatan sistem dan tinjau siapa yang masuk, apa yang diakses, dan apa yang dibuat, diubah, atau dihapus.
requires: [control-center, control-center.overview, control-center.activity-log, control-center.login-log, control-center.audit-log]
updated: 2026-10-27
---
Control Center adalah area administrator EcoSystem. Topik ini membahas halaman pemantauan: `Overview`, `Activity Log`, `Login Log`, dan `Audit Log`. Alat sistem seperti sesi, Backup, dan model AI ada di [[control-center-system]].

## Siapa yang menggunakan

Hanya administrator. Setiap halaman memiliki akses sendiri, jadi Anda hanya melihat halaman yang diizinkan untuk Role Anda di bawah `Control Center`. Halaman `Overview` dibatasi untuk Role administrator tertinggi.

> [!IMPORTANT]
> Halaman ini menampilkan informasi aktivitas orang. Gunakan hanya untuk keperluan administrasi, keamanan, atau audit, dan jangan bagikan kepada pihak yang tidak berhak.

## Sebelum Anda mulai

- Ketahui apa yang Anda cari: seseorang, rentang tanggal, atau jenis tindakan. Halaman ini memiliki filter, jadi persempit daftar terlebih dahulu.

## Langkah demi langkah

### Memeriksa Overview

1. Buka `Control Center` › `Overview`.
2. Baca kartu ringkasan, misalnya `System Health`, `Queue`, `Failed Jobs`, dan jumlah sesi aktif.
3. Gunakan kartu pintasan untuk membuka `Activity Log`, `Backup & Export`, `Notification Sounds`, `AI Settings`, dan alat lainnya.

Bila kartu antrean melaporkan galat, buka `Failed Jobs` (lihat [[control-center-system]]).

### Meninjau Activity Log

`Activity Log` mencatat sign-in, sign-out, dan upaya akses.

1. Buka `Control Center` › `Activity Log`.
2. Atur `Date From` dan `Date To`, status (`Success` atau `Failed`), tipe pengguna, atau kata pencarian, lalu klik `Apply`.
3. Baca kartu (`Total Records`, `Successful Logins`, `Failed Attempts`, `Logouts`) dan tabel berisi perangkat, browser, alamat IP, dan lokasi.
4. Klik `Clear` untuk menghapus filter.

Banyaknya percobaan gagal pada satu akun dapat berarti ada yang menebak kata sandi. Beri tahu kontak keamanan Anda.

### Meninjau Login Log

1. Buka `Control Center` › `Login Log`.
2. Cari seseorang atau pilih status.
3. Baca login dan logout terakhir setiap Employee, lengkap dengan waktu (WIB) dan perangkat.

### Meninjau Audit Log

`Audit Log` mencatat tindakan buat, ubah, dan hapus di berbagai modul, beserta nilai sebelum dan sesudahnya.

1. Buka `Control Center` › `Audit Log`.
2. Pilih modul (misalnya `Ticket`, `Customer`, `Employee`), peristiwa (`Created`, `Updated`, `Deleted`), dan rentang tanggal, lalu klik `Apply`.
3. Buka catatan untuk melihat kolom yang berubah: `Field`, `Before`, dan `After`. Untuk catatan yang dihapus Anda melihat `Value Before Deletion`.

> [!NOTE]
> Log hanya untuk dilihat. Anda tidak dapat mengubah atau menghapus catatan log dari halaman ini.

## Apa yang terjadi selanjutnya

Temuan dari log ditangani di luar halaman ini, misalnya dengan memperbaiki data di modul, mengubah akses di [[roles-and-menu-access]], atau mengakhiri sesi di [[control-center-system]].

## Pertanyaan umum

**Mengapa saya tidak melihat `Overview`?** Halaman itu dibatasi untuk Role administrator tertinggi.

**Tindakan yang saya harapkan tidak ada di Audit Log.** Log hanya mencakup modul yang tercantum pada filter modulnya.

## Topik terkait

- [[control-center-system]]
- [[roles-and-menu-access]]
- [[access-and-menus]]

<!-- sources: routes/web.php, resources/views/admin/index.blade.php, resources/views/admin/activity-log.blade.php, resources/views/admin/login-log.blade.php, resources/views/admin/audit-log.blade.php, resources/views/partials/sidebar.blade.php -->
