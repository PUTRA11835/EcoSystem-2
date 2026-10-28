---
key: ess-settings
chapter: administration
order: 20
title: ESS Settings
summary: Tampilkan atau sembunyikan menu layanan mandiri untuk semua orang, dan kelompokkan menjadi dropdown sidebar.
requires: [management.ess-settings]
updated: 2026-10-27
---
ESS Settings mengatur menu layanan mandiri (ESS) seperti My Attendance atau Paystub yang dilihat setiap Employee di sidebar. Anda dapat mematikan sebuah item untuk seluruh perusahaan dan melipat item yang berkaitan ke dalam dropdown bernama. Topik ini ditujukan untuk administrator.

## Siapa yang menggunakan

Administrator. Halamannya adalah `Management` › `ESS Settings`.

> [!WARNING]
> Saklar ini bersifat global. Mematikan sebuah item menyembunyikannya dari semua Employee, apa pun Role mereka. Untuk mengatur siapa yang melihat sebuah halaman, gunakan [[roles-and-menu-access]].

## Sebelum Anda mulai

- Ketahui item layanan mandiri mana yang dipakai perusahaan Anda. Matikan hanya item yang memang belum boleh dipakai siapa pun.
- Akses menu dan saklar ini bekerja bersama: seseorang melihat item layanan mandiri hanya bila item itu menyala di sini dan aksesnya mengizinkan.

## Langkah demi langkah

### Menampilkan atau menyembunyikan item

1. Buka `Management` › `ESS Settings`.
2. Temukan item pada daftar. Setiap item memiliki saklar.
3. Nyalakan untuk menampilkan, matikan untuk menyembunyikan. `Select All` dan `Deselect All` mengubah semua saklar sekaligus.
4. Klik `Save Changes`.

### Mengelompokkan item ke dalam dropdown

1. Di `Menu Groups`, klik `Create Group`.
2. Isi `Group name`, misalnya "My Activity", dan `Icon` (kelas Font Awesome seperti `fas fa-briefcase`).
3. Klik `Add`. Gunakan `Save Name` untuk mengganti nama nanti, atau ikon hapus untuk menghapus grup.
4. Untuk setiap item yang ingin dimasukkan ke grup, pilih grupnya pada baris item tersebut.
5. Klik `Save Changes`.

Item yang tidak Anda masukkan ke grup tetap tampil di sidebar seperti biasa. Beberapa item tidak dapat dikelompokkan karena sudah memiliki dropdown sendiri atau selalu tampil (misalnya item kalender dan Logout).

## Apa yang terjadi selanjutnya

Sidebar setiap Employee diperbarui saat mereka memuat halaman berikutnya.

## Pertanyaan umum

**Seorang Employee tidak menemukan My Attendance.** Periksa bahwa item itu menyala di sini dan Role orang tersebut memiliki aksesnya. Lihat [[access-and-menus]].

**Apakah menghapus grup juga menghapus itemnya?** Tidak. Item kembali ke daftar biasa di sidebar.

## Topik terkait

- [[roles-and-menu-access]]
- [[access-and-menus]]
- [[navigating-ecosystem]]

<!-- sources: routes/web.php, app/Http/Controllers/Management/EssSettingsController.php, resources/views/management/ess-settings/index.blade.php, resources/views/partials/sidebar.blade.php -->
